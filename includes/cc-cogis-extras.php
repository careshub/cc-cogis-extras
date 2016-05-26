<?php
/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-plugin-name-admin.php`
 *
 * @TODO: Rename this class to a proper name for your plugin.
 *
 * @package CC Group Narratives
 * @author  David Cavins
 */
class CC_Cogis_Extras {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 *
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'cc-cogis-extras';

	/**
	 *
	 * The ID for the COGIS group on www.
	 *
	 *
	 *
	 * @since    1.0.0
	 *
	 * @var      int
	 */
	protected $cogis_id = 54; //54 on staging and www, 26 on local

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		// add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Add filter to catch removal of a story from a group
		// add_action( 'bp_init', array( $this, 'remove_story_from_group'), 75 );

		// Activate plugin when new blog is added
		// add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_registration_styles') );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_chosen_js') );

		/* Define custom functionality.
		 * Refer To http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		// add_action( '@TODO', array( $this, 'action_method_name' ) );
		// add_filter( '@TODO', array( $this, 'filter_method_name' ) );
		add_action( 'bp_before_group_request_membership_content', array( $this, 'print_descriptive_text') );
		add_action('bp_group_request_membership_content', array( $this, 'print_grantee_list' ) );
		add_filter( 'groups_member_comments_before_save', array( $this, 'append_grantee_comment' ), 25, 2 );

		// Registration form additions
		add_action( 'bp_before_registration_submit_buttons', array( $this, 'registration_section_output' ), 60 );
		add_action( 'bp_core_signup_user', array( $this, 'registration_extras_processing'), 1, 71 );
		// Add "cogis" as an interest if the registration originates from an SA page
		// Filters array provided by registration_form_interest_query_string
		// @returns array with new element (or not)
		add_filter( 'registration_form_interest_query_string', array( $this, 'add_registration_interest_parameter' ), 12, 1 );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue scripts and styles for chosen js select list helper.
	 *
	 * @since    1.1.0
	 */
	public function enqueue_chosen_js() {
		if ( ( bp_get_current_group_id() == $this->cogis_id && bp_is_current_action( 'request-membership' ) ) || ( bp_is_register_page() && isset( $_GET['cogis'] ) && $_GET['cogis'] ) ) {
			wp_enqueue_style( 'chosen-js-styles', 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.4.2/chosen.min.css', array(), '1.4.2' );
			wp_enqueue_script( 'chosen-js-script', 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.4.2/chosen.jquery.min.js', array( 'jquery' ), '1.4.2' );
		}
	}

	public function enqueue_registration_styles() {
	    if ( bp_is_register_page() && isset( $_GET['cogis'] ) && $_GET['cogis'] )
	      wp_enqueue_style( 'cogis-section-register-css', plugin_dir_url( __FILE__ ) . 'cogis_registration_extras.css', array(), '0.1', 'screen' );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}

	/**
	 * Output descriptive text above the request form.
	 *
	 * @since    1.0.0
	 */
	//
	public function print_descriptive_text() {
		//If this isn't the COGIS group or the registration page, don't bother.
		if ( ( bp_get_current_group_id() != $this->cogis_id ) &&
		! ( bp_is_register_page() && ( isset( $_GET['cogis'] ) && $_GET['cogis'] ) ) )
			return false;

		echo '<p class="description">The Robert Wood Johnson Foundation is offering access to the Childhood Obesity GIS collaborative group space to all current Childhood Obesity grantees free of charge. Within this space you can create maps, reports and documents collaboratively on the Commons. If you are interested in accessing this collaborative space, select your grant name from the list below. We&rsquo;ll respond with access within 24 hours.</p>';
	}

	/**
	 * Output extra form field on COGIS membership request form - add dropdown with possible group associations to add to membership request comment.
	 *
	 * @since    1.0.0
	 */
	//
	public function print_grantee_list() {
		//If this isn't the COGIS group or the registration page, don't bother.
		if ( ( bp_get_current_group_id() != $this->cogis_id ) &&
			! ( bp_is_register_page() && ( isset( $_GET['cogis'] ) && $_GET['cogis'] ) ) )
			return;
		?>
		<div class="content-row" style="margin-bottom:2em;">
			<h5>RWJF Childhood Obesity Grant Name</h5>
			<select name="cogis_affiliation" id="cogis_affiliation" class="chosen-select" data-placeholder="Select your affiliation." style="width:100%">
				<!-- Include an empty option for chosen.js support-->
				<option></option>
				<option value="Affiliation not selected">No affiliation</option>
				<option value="AcademyHealth">AcademyHealth</option>
				<option value="ACEs Too High">ACEs Too High</option>
				<option value="Active Living By Design">Active Living By Design</option>
				<option value="Alliance for a Healthier Generation, Inc.">Alliance for a Healthier Generation, Inc.</option>
				<option value="Alliance for a Just Society">Alliance for a Just Society</option>
				<option value="Alliance for Children and Families Inc.">Alliance for Children and Families Inc.</option>
				<option value="Alliance for Educational Justice">Alliance for Educational Justice</option>
				<option value="American Academy of Pediatrics, Inc.">American Academy of Pediatrics, Inc.</option>
				<option value="American Heart Association Inc.">American Heart Association Inc.</option>
				<option value="American Institutes for Research in the Behavioral Sciences">American Institutes for Research in the Behavioral Sciences</option>
				<option value="Arizona State University">Arizona State University</option>
				<option value="Ashoka">Ashoka</option>
				<option value="Asian and Pacific Islander American Health Forum">Asian and Pacific Islander American Health Forum</option>
				<option value="Aspen Institute Inc.">Aspen Institute Inc.</option>
				<option value="Auckland University of Technology">Auckland University of Technology</option>
				<option value="Berkeley Media Studies Group">Berkeley Media Studies Group</option>
				<option value="Bipartisan Policy Center, Inc.">Bipartisan Policy Center, Inc.</option>
				<option value="Boston College">Boston College</option>
				<option value="Boston Medical Center">Boston Medical Center</option>
				<option value="Brookline Community Mental Health Center Inc.">Brookline Community Mental Health Center Inc.</option>
				<option value="Build Initiative">Build Initiative</option>
				<option value="Building Changes">Building Changes</option>
				<option value="Capital Impact Partners">Capital Impact Partners</option>
				<option value="Center for Digital Democracy">Center for Digital Democracy</option>
				<option value="Center for Global Policy Solutions">Center for Global Policy Solutions</option>
				<option value="Center for Health Care Strategies Supporting Organization Inc.">Center for Health Care Strategies Supporting Organization Inc.</option>
				<option value="Center for Innovation, Inc.">Center for Innovation, Inc.</option>
				<option value="Center for the Collaborative Classroom">Center for the Collaborative Classroom</option>
				<option value="ChangeLab Solutions">ChangeLab Solutions</option>
				<option value="Child and Family Policy Center">Child and Family Policy Center</option>
				<option value="Child First, Inc.">Child First, Inc.</option>
				<option value="Child Trends, Inc.">Child Trends, Inc.</option>
				<option value="CHN Nebraska dba Gretchen Swanson Center for Nutrition">CHN Nebraska dba Gretchen Swanson Center for Nutrition</option>
				<option value="City of Philadelphia Department of Public Health">City of Philadelphia Department of Public Health</option>
				<option value="City University of New York, John Jay College of Criminal Justice">City University of New York, John Jay College of Criminal Justice</option>
				<option value="Collaborative For Academic, Social, and Emotional Learning (CASEL)">Collaborative For Academic, Social, and Emotional Learning (CASEL)</option>
				<option value="ColorOfChange">ColorOfChange</option>
				<option value="Columbia University Mailman School of Public Health">Columbia University Mailman School of Public Health</option>
				<option value="Communities in Schools">Communities in Schools</option>
				<option value="Community Foundation of Northwest Mississippi">Community Foundation of Northwest Mississippi</option>
				<option value="Concord Evaluation Group">Concord Evaluation Group</option>
				<option value="Cooper's Ferry Partnership, Inc.">Cooper's Ferry Partnership, Inc.</option>
				<option value="Corporation for Supportive Housing">Corporation for Supportive Housing</option>
				<option value="Crisis Text Line">Crisis Text Line</option>
				<option value="Crittenton Center d/b/a Crittenton Children's Center">Crittenton Center d/b/a Crittenton Children's Center</option>
				<option value="Cumberland Cape Atlantic YMCA">Cumberland Cape Atlantic YMCA</option>
				<option value="Design Studio for Social Intervention">Design Studio for Social Intervention</option>
				<option value="Drexel University School of Public Health">Drexel University School of Public Health</option>
				<option value="Duke University Center for Child and Family Policy">Duke University Center for Child and Family Policy</option>
				<option value="Duke University Global Health Institute">Duke University Global Health Institute</option>
				<option value="Echo Hawk Consulting">Echo Hawk Consulting</option>
				<option value="Fair Food Network">Fair Food Network</option>
				<option value="Family Health International (FHI 360)">Family Health International (FHI 360)</option>
				<option value="First Place for Youth">First Place for Youth</option>
				<option value="Food Research and Action Center Inc.">Food Research and Action Center Inc.</option>
				<option value="Food Trust">Food Trust</option>
				<option value="Foraker Group">Foraker Group</option>
				<option value="Funders' Collaborative on Youth Organizing">Funders' Collaborative on Youth Organizing</option>
				<option value="George Mason University College of Health and Human Services">George Mason University College of Health and Human Services</option>
				<option value="Georgetown University">Georgetown University</option>
				<option value="Georgetown University McCourt School of Public Policy">Georgetown University McCourt School of Public Policy</option>
				<option value="GirlTrek">GirlTrek</option>
				<option value="Health Federation of Philadelphia">Health Federation of Philadelphia</option>
				<option value="Healthy Schools Campaign">Healthy Schools Campaign</option>
				<option value="Hope Street Group">Hope Street Group</option>
				<option value="Hudson Institute Inc.">Hudson Institute Inc.</option>
				<option value="Humanim, Inc.">Humanim, Inc.</option>
				<option value="Institute for People, Place and Possibilities (DBA Institute for People, Place and Possibility)">Institute for People, Place and Possibilities (DBA Institute for People, Place and Possibility)</option>
				<option value="Institute on Violence, Abuse and Trauma, Family Violence and Sexual Assault Institute">Institute on Violence, Abuse and Trauma, Family Violence and Sexual Assault Institute</option>
				<option value="Johns Hopkins University Bloomberg School of Public Health">Johns Hopkins University Bloomberg School of Public Health</option>
				<option value="Loyola Marymount University Bellarmine College of Liberal Arts">Loyola Marymount University Bellarmine College of Liberal Arts</option>
				<option value="LTG Associates, Inc.">LTG Associates, Inc.</option>
				<option value="Mary Ann Scheirer">Mary Ann Scheirer</option>
				<option value="Media Management Services Inc. dba MMS Education">Media Management Services Inc. dba MMS Education</option>
				<option value="Medicaid Health Plans of America">Medicaid Health Plans of America</option>
				<option value="Merrimack College School of Science and Engineering">Merrimack College School of Science and Engineering</option>
				<option value="Milken Institute School of Public Health at George Washington University">Milken Institute School of Public Health at George Washington University</option>
				<option value="MomsRising Education Fund">MomsRising Education Fund</option>
				<option value="NACCRRA DBA Child Care Aware of America">NACCRRA DBA Child Care Aware of America</option>
				<option value="National 4-H Council">National 4-H Council</option>
				<option value="National Academy of Sciences, Institute of Medicine">National Academy of Sciences, Institute of Medicine</option>
				<option value="National Association for the Advancement of Colored People (NAACP)">National Association for the Advancement of Colored People (NAACP)</option>
				<option value="National CARES Mentoring Movement">National CARES Mentoring Movement</option>
				<option value="National Council of La Raza (NCLR)">National Council of La Raza (NCLR)</option>
				<option value="National Governors Association Center for Best Practices (NGA)">National Governors Association Center for Best Practices (NGA)</option>
				<option value="National League of Cities Institute Inc.">National League of Cities Institute Inc.</option>
				<option value="Nemours Foundation">Nemours Foundation</option>
				<option value="New Brunswick Tomorrow">New Brunswick Tomorrow</option>
				<option value="New Jersey Coalition Against Sexual Assault (NJCASA)">New Jersey Coalition Against Sexual Assault (NJCASA)</option>
				<option value="New Jersey YMCA State Alliance Inc.">New Jersey YMCA State Alliance Inc.</option>
				<option value="New Teacher Center">New Teacher Center</option>
				<option value="Notah Begay III Foundation">Notah Begay III Foundation</option>
				<option value="Ounce of Prevention Fund">Ounce of Prevention Fund</option>
				<option value="Partnership for a Healthier America, Inc.">Partnership for a Healthier America, Inc.</option>
				<option value="Pennsylvania State University">Pennsylvania State University</option>
				<option value="Pennsylvania State University Bennett Pierce Prevention Research Center">Pennsylvania State University Bennett Pierce Prevention Research Center</option>
				<option value="Pennsylvania State University College of Health and Human Development">Pennsylvania State University College of Health and Human Development</option>
				<option value="Pew Charitable Trusts">Pew Charitable Trusts</option>
				<option value="PICO National Network">PICO National Network</option>
				<option value="PolicyLink">PolicyLink</option>
				<option value="Portland State University School of Social Work">Portland State University School of Social Work</option>
				<option value="Practical Parenting Consulting, LLC">Practical Parenting Consulting, LLC</option>
				<option value="Praxis Project, Inc.">Praxis Project, Inc.</option>
				<option value="Prevention Institute">Prevention Institute</option>
				<option value="Princeton University">Princeton University</option>
				<option value="Public Health Institute">Public Health Institute</option>
				<option value="Public Health Law Center, Inc.">Public Health Law Center, Inc.</option>
				<option value="Rand Corporation">Rand Corporation</option>
				<option value="Raritan Valley YMCA">Raritan Valley YMCA</option>
				<option value="Reinvestment Fund, Inc.">Reinvestment Fund, Inc.</option>
				<option value="Research Triangle Institute (RTI)">Research Triangle Institute (RTI)</option>
				<option value="Roca, Inc.">Roca, Inc.</option>
				<option value="Root Cause Institute, Inc.">Root Cause Institute, Inc.</option>
				<option value="Rural Support Partners">Rural Support Partners</option>
				<option value="Rutgers, The State University of New Jersey, Institute for Health, Health Care Policy, and Aging Research">Rutgers, The State University of New Jersey, Institute for Health, Health Care Policy, and Aging Research</option>
				<option value="Save the Children Action Network">Save the Children Action Network</option>
				<option value="School Food FOCUS">School Food FOCUS</option>
				<option value="St. Vincent de Paul Society of Lane County, Inc.">St. Vincent de Paul Society of Lane County, Inc.</option>
				<option value="Stanford University School of Medicine">Stanford University School of Medicine</option>
				<option value="State of Massachusetts Department of Public Health">State of Massachusetts Department of Public Health</option>
				<option value="Strategic Concepts in Organizing and Policy Education (SCOPE)">Strategic Concepts in Organizing and Policy Education (SCOPE)</option>
				<option value="StriveTogether, LLC">StriveTogether, LLC</option>
				<option value="Texas Health Institute">Texas Health Institute</option>
				<option value="Tides Foundation">Tides Foundation</option>
				<option value="Transtria LLC">Transtria LLC</option>
				<option value="Tufts University Friedman School of Nutrition Science and Policy">Tufts University Friedman School of Nutrition Science and Policy</option>
				<option value="Tulane University School of Medicine">Tulane University School of Medicine</option>
				<option value="University of Arkansas, School of Law">University of Arkansas, School of Law</option>
				<option value="University of California, Berkeley, College of Natural Resources">University of California, Berkeley, College of Natural Resources</option>
				<option value="University of California, Davis, Medical Center">University of California, Davis, Medical Center</option>
				<option value="University of California, Division of Agriculture and Natural Resources, Nutrition Policy Institute">University of California, Division of Agriculture and Natural Resources, Nutrition Policy Institute</option>
				<option value="University of California, Los Angeles, David Geffen School of Medicine">University of California, Los Angeles, David Geffen School of Medicine</option>
				<option value="University of California, San Diego, School of Medicine">University of California, San Diego, School of Medicine</option>
				<option value="University of California, San Francisco, School of Medicine, Philip R. Lee Institute for Health Policy Studies">University of California, San Francisco, School of Medicine, Philip R. Lee Institute for Health Policy Studies</option>
				<option value="University of Connecticut Rudd Center for Food Policy and Obesity">University of Connecticut Rudd Center for Food Policy and Obesity</option>
				<option value="University of Illinois at Chicago Institute for Health Research and Policy">University of Illinois at Chicago Institute for Health Research and Policy</option>
				<option value="University of Illinois at Chicago School of Public Health">University of Illinois at Chicago School of Public Health</option>
				<option value="University of Massachusetts Medical School Worcester">University of Massachusetts Medical School Worcester</option>
				<option value="University of Minnesota School of Public Health">University of Minnesota School of Public Health</option>
				<option value="University of New England School of Community and Population Health">University of New England School of Community and Population Health</option>
				<option value="University of North Carolina at Chapel Hill Gillings School of Global Public Health">University of North Carolina at Chapel Hill Gillings School of Global Public Health</option>
				<option value="University of Pennsylvania Perelman School of Medicine">University of Pennsylvania Perelman School of Medicine</option>
				<option value="University of Southern California School of Social Work">University of Southern California School of Social Work</option>
				<option value="University of Texas Health Science Center San Antonio School of Medicine">University of Texas Health Science Center San Antonio School of Medicine</option>
				<option value="Urban Institute">Urban Institute</option>
				<option value="Vanderbilt University Peabody College of Education and Human Development">Vanderbilt University Peabody College of Education and Human Development</option>
				<option value="Virginia Polytechnic Institute and State University, College of Agriculture and Life Sciences">Virginia Polytechnic Institute and State University, College of Agriculture and Life Sciences</option>
				<option value="Washington University in St. Louis, George Warren Brown School of Social Work">Washington University in St. Louis, George Warren Brown School of Social Work</option>
				<option value="Westat, Inc.">Westat, Inc.</option>
				<option value="WestEd">WestEd</option>
				<option value="Wildflower Foundation">Wildflower Foundation</option>
				<option value="Yale University Center for Emotional Intelligence">Yale University Center for Emotional Intelligence</option>
				<option value="YMCA of the USA">YMCA of the USA</option>
				<option value="Young Men's and Women's Christian Association of Newark and Vicinity (YMCA)">Young Men's and Women's Christian Association of Newark and Vicinity (YMCA)</option>
				<option value="Youth Transition Funders Group">Youth Transition Funders Group</option>
			</select>
			<script type="text/javascript">
				jQuery( '.chosen-select' ).chosen({});
			</script>
		</div>
		<?php
	}

	public function append_grantee_comment( $comments, $membership_id ) {
		//If this isn't the COGIS group or the registration page, don't bother.
		if ( ( bp_get_current_group_id() != $this->cogis_id ) &&
		! ( bp_is_register_page() && ( isset( $_GET['cogis'] ) && $_GET['cogis'] ) ) )
			return false;

		if ( isset( $_POST['cogis_affiliation'] ) ) {
			 $comments .= ' <em>User selected RWJF Childhood Obesity Grant Name: ' . $_POST['cogis_affiliation'] . '</em>';
		}
		return $comments;
	}

	// Registration form additions
	function registration_section_output() {
	  if ( isset( $_GET['cogis'] ) && $_GET['cogis'] ) :
	  ?>
	    <div id="cogis-interest-opt-in" class="register-section checkbox">
		    <?php  $avatar = bp_core_fetch_avatar( array(
				'item_id' => $this->cogis_id,
				'object'  => 'group',
				'type'    => 'thumb',
				'class'   => 'registration-logo',

			) );
			echo $avatar; ?>
	      <h4 class="registration-headline">Join the Group: <em>Childhood Obesity GIS</em></h4>

   	      <?php $this->print_descriptive_text(); ?>

	      <label><input type="checkbox" name="cogis_interest_group" id="cogis_interest_group" value="agreed" <?php $this->determine_checked_status_default_is_checked( 'cogis_interest_group' ); ?> /> Yes, I’d like to request membership in the group.</label>

	      <label for="group-request-membership-comments">Comments for the group admin (optional)</label>
	      <textarea name="group-request-membership-comments" id="group-request-membership-comments"><?php
	      	if ( isset($_POST['group-request-membership-comments']) )
	      		echo $_POST['group-request-membership-comments'];
	      ?></textarea>

   	      <?php $this->print_grantee_list(); ?>

	    </div>
	    <?php
	    endif;
	}

	/**
	* Update usermeta with custom registration data
	* @since 0.1
	*/
	public function registration_extras_processing( $user_id ) {

	  if ( isset( $_POST['cogis_interest_group'] ) ) {
	  	// Create the group request
	  	$request = groups_send_membership_request( $user_id, $this->cogis_id );
	  }

	  return $user_id;
	}

	public function determine_checked_status_default_is_checked( $field_name ){
		  // In its default state, no $_POST should exist. If this is a resubmit effort, $_POST['signup_submit'] will be set, then we can trust the value of the checkboxes.
		  if ( isset( $_POST['signup_submit'] ) && !isset( $_POST[ $field_name ] ) ) {
		    // If the user specifically unchecked the box, don't make them do it again.
		  } else {
		    // Default state, $_POST['signup_submit'] isn't set. Or, it is set and the checkbox is also set.
		    echo 'checked="checked"';
		  }
	}
	public function add_registration_interest_parameter( $interests ) {

	    if ( bp_is_groups_component() && ( bp_get_current_group_id() == $this->cogis_id ) ) {
	    	$interests[] = 'cogis';
		}

	    return $interests;
	}

}