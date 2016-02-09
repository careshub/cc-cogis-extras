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
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	public function enqueue_registration_styles() {
	    if( bp_is_register_page() && isset( $_GET['cogis'] ) && $_GET['cogis'] )
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
			return false;
		?>
		<div class="content-row" style="margin-bottom:2em;">
			<h5>RWJF Childhood Obesity Grant Name</h5>
			<select name="cogis_affiliation" id="cogis_affiliation" style="width:100%">
				<option value="Affiliation not selected">No affiliation</option>
				<option value="Afterschool Alliance: Building support for expanded physical activity through out-of-school programs for disadvantaged youths to reverse childhood obesity">Afterschool Alliance: Building support for expanded physical activity through out-of-school programs for disadvantaged youths to reverse childhood obesity</option>
				<option value="Alliance for a Healthier Generation: Expanding the Healthy Schools Program in states with the highest prevalence of obesity, 2011-2014">Alliance for a Healthier Generation: Expanding the Healthy Schools Program in states with the highest prevalence of obesity, 2011-2014</option>
				<option value="Alliance for a Just Society: Building on the capacity of Communities Creating Healthy Environments to address childhood obesity issues in communities of color, 2014">Alliance for a Just Society: Building on the capacity of Communities Creating Healthy Environments to address childhood obesity issues in communities of color, 2014</option>
				<option value="American Association of School Administrators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">American Association of School Administrators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="American Heart Association Inc.: Supporting the American Heart Association as lead advocacy organization in reversing the childhood obesity epidemic, Year 1">American Heart Association Inc.: Supporting the American Heart Association as lead advocacy organization in reversing the childhood obesity epidemic, Year 1</option>
				<option value="Arizona State University School of Nutrition and Health Promotion: Tracking the impact on children of changes in the food and physical activity environments in five New Jersey cities over five years">Arizona State University School of Nutrition and Health Promotion: Tracking the impact on children of changes in the food and physical activity environments in five New Jersey cities over five years</option>
				<option value="Bend the Arc: A Jewish Partnership for Justice: Empowering young people from communities most affected to address root causes of childhood obesity, Phase 2">Bend the Arc: A Jewish Partnership for Justice: Empowering young people from communities most affected to address root causes of childhood obesity, Phase 2</option>
				<option value="Bikes Belong Foundation: Advancing street-scale improvements and joint-use agreements in underserved communities to reduce childhood obesity, 2013-2014">Bikes Belong Foundation: Advancing street-scale improvements and joint-use agreements in underserved communities to reduce childhood obesity, 2013-2014</option>
				<option value="Bill, Hillary, and Chelsea Clinton Foundation: Supporting a Clinton Health Matters Initiative forum on health disparities and inequities in the childhood obesity epidemic">Bill, Hillary, and Chelsea Clinton Foundation: Supporting a Clinton Health Matters Initiative forum on health disparities and inequities in the childhood obesity epidemic</option>
				<option value="Boston Foundation: Supporting development of the pilot location of the Urban Food Initiative for proof of concept and national replication">Boston Foundation: Supporting development of the pilot location of the Urban Food Initiative for proof of concept and national replication</option>
				<option value="Cascade Harvest Coalition: Addressing barriers in the food-supply chain in the Puget Sound, WA, region with the goal of reducing childhood obesity">Cascade Harvest Coalition: Addressing barriers in the food-supply chain in the Puget Sound, WA, region with the goal of reducing childhood obesity</option>
				<option value="Center for Digital Democracy: Advocating safeguards to the digital marketing of unhealthy foods to children and adolescents, 2013-2015">Center for Digital Democracy: Advocating safeguards to the digital marketing of unhealthy foods to children and adolescents, 2013-2015</option>
				<option value="Center for Global Policy Solutions: Technical assistance and direction for RWJF's Leadership for Healthy Communities national program, 2014-2015">Center for Global Policy Solutions: Technical assistance and direction for RWJF's Leadership for Healthy Communities national program, 2014-2015</option>
				<option value="ChangeLab Solutions: Developing a system to track technical assistance for RWJF's initiative to prevent childhood obesity">ChangeLab Solutions: Developing a system to track technical assistance for RWJF's initiative to prevent childhood obesity</option>
				<option value="ChangeLab Solutions: National Policy and Legal Analysis Network to Prevent Childhood Obesity, 2014-2015">ChangeLab Solutions: National Policy and Legal Analysis Network to Prevent Childhood Obesity, 2014-2015</option>
				<option value="Chattanooga - Hamilton County Health Department: Identifying and implementing evidence-based strategies to promote children's health and reduce obesity through Grow Healthy Together Chattanooga">Chattanooga - Hamilton County Health Department: Identifying and implementing evidence-based strategies to promote children's health and reduce obesity through Grow Healthy Together Chattanooga</option>
				<option value="CHN Nebraska dba Gretchen Swanson Center for Nutrition: Developing a toolkit to measure access to healthy foods at urban and rural corner stores">CHN Nebraska dba Gretchen Swanson Center for Nutrition: Developing a toolkit to measure access to healthy foods at urban and rural corner stores</option>
				<option value="City of Rancho Cucamonga: Developing a policy, environmental and systems change agenda for decreasing childhood obesity in Rancho Cucamonga, Calif.">City of Rancho Cucamonga: Developing a policy, environmental and systems change agenda for decreasing childhood obesity in Rancho Cucamonga, Calif.</option>
				<option value="Columbia University Mailman School of Public Health: Understanding participation in the Women, Infants, and Children nutrition program and other early-childhood obesity prevention programs in New York">Columbia University Mailman School of Public Health: Understanding participation in the Women, Infants, and Children nutrition program and other early-childhood obesity prevention programs in New York</option>
				<option value="Columbia University Mailman School of Public Health: Using a bid database to study the nutritional quality of competitive foods in schools and establish a baseline for evaluating new USDA guidelines">Columbia University Mailman School of Public Health: Using a bid database to study the nutritional quality of competitive foods in schools and establish a baseline for evaluating new USDA guidelines</option>
				<option value="Columbia University: Assessing the youth 'energy gap' to benchmark progress in reversing childhood obesity and inform the Healthy Weight Commitment evaluation">Columbia University: Assessing the youth 'energy gap' to benchmark progress in reversing childhood obesity and inform the Healthy Weight Commitment evaluation</option>
				<option value="Community Foundation of Northwest Mississippi: Supporting the position of Northwest Mississippi Health Council coordinator in reducing childhood obesity in a 10-county region">Community Foundation of Northwest Mississippi: Supporting the position of Northwest Mississippi Health Council coordinator in reducing childhood obesity in a 10-county region</option>
				<option value="Community Growth Educational Foundation: Engaging the business community in childhood obesity prevention">Community Growth Educational Foundation: Engaging the business community in childhood obesity prevention</option>
				<option value="Convergence Center for Policy Resolution: Supporting the Convergence Center's Project on Nutrition and Wellness to increase U.S. consumers' demand for healthful dietary choices">Convergence Center for Policy Resolution: Supporting the Convergence Center's Project on Nutrition and Wellness to increase U.S. consumers' demand for healthful dietary choices</option>
				<option value="Council for a Strong America: Supporting Mission: Readiness in engaging retired generals and admirals to advocate nationally for childhood obesity prevention, 2013-2015">Council for a Strong America: Supporting Mission: Readiness in engaging retired generals and admirals to advocate nationally for childhood obesity prevention, 2013-2015</option>
				<option value="Cumberland Cape Atlantic YMCA: Supporting the local partnership to prevent childhood obesity in Vineland, N.J., 2013-2015">Cumberland Cape Atlantic YMCA: Supporting the local partnership to prevent childhood obesity in Vineland, N.J., 2013-2015</option>
				<option value="DataCenter: Challenging the marketing of unhealthy foods in low-income communities of color, 2014">DataCenter: Challenging the marketing of unhealthy foods in low-income communities of color, 2014</option>
				<option value="Down East Partnership for Children: Creating environmental changes that influence the nutrition and physical activity habits of young children in Edgecombe and Nash counties, N.C.">Down East Partnership for Children: Creating environmental changes that influence the nutrition and physical activity habits of young children in Edgecombe and Nash counties, N.C.</option>
				<option value="Duval County Health Department: Creating communities in Jacksonville, Fla., where all children have access to healthy foods and safe places to play">Duval County Health Department: Creating communities in Jacksonville, Fla., where all children have access to healthy foods and safe places to play</option>
				<option value="Emory University, Rollins School of Public Health: Studying the impact of physical activity and cardiovascular fitness on academic achievement">Emory University, Rollins School of Public Health: Studying the impact of physical activity and cardiovascular fitness on academic achievement</option>
				<option value="Fair Food Network: Increasing low-income consumers' access to the Fair Food Network's Double Up Food Bucks nutrition program">Fair Food Network: Increasing low-income consumers' access to the Fair Food Network's Double Up Food Bucks nutrition program</option>
				<option value="Food Research and Action Center Inc.: Facilitating rapid implementation of the Healthy, Hunger-Free Kids Act to broaden impact of the Child and Adult Care Food Program">Food Research and Action Center Inc.: Facilitating rapid implementation of the Healthy, Hunger-Free Kids Act to broaden impact of the Child and Adult Care Food Program</option>
				<option value="Food Trust: Evaluating the health and economic impact of the New Jersey Food Access Initiative">Food Trust: Evaluating the health and economic impact of the New Jersey Food Access Initiative</option>
				<option value="Food Trust: Increasing access to healthy foods in New Jersey through an initiative to transform corner stores">Food Trust: Increasing access to healthy foods in New Jersey through an initiative to transform corner stores</option>
				<option value="Food Trust: Supporting the Food Trust's national campaign to increase the number of healthy food outlets in underserved areas, 2013-2014">Food Trust: Supporting the Food Trust's national campaign to increase the number of healthy food outlets in underserved areas, 2013-2014</option>
				<option value="Gutman Research Associates: Planning a networked model for childhood obesity research programs to support more effective advocacy and sustainability">Gutman Research Associates: Planning a networked model for childhood obesity research programs to support more effective advocacy and sustainability</option>
				<option value="Harvard Pilgrim Health Care Inc.: Evaluating the impact of menu labeling on fast-food choices by children and adolescents">Harvard Pilgrim Health Care Inc.: Evaluating the impact of menu labeling on fast-food choices by children and adolescents</option>
				<option value="Harvard University School of Public Health: Evaluating the effectiveness of new nutrition standards for competitive foods and beverages in Massachusetts schools">Harvard University School of Public Health: Evaluating the effectiveness of new nutrition standards for competitive foods and beverages in Massachusetts schools</option>
				<option value="Hudson Institute, Inc.: Assessing the business case for supermarket chains to market and sell lower-calorie foods and beverages">Hudson Institute, Inc.: Assessing the business case for supermarket chains to market and sell lower-calorie foods and beverages</option>
				<option value="Interfaith Center on Corporate Responsibility, Inc.: Encouraging corporate and investor engagement to reduce childhood obesity, Year 2">Interfaith Center on Corporate Responsibility, Inc.: Encouraging corporate and investor engagement to reduce childhood obesity, Year 2</option>
				<option value="Johns Hopkins University Bloomberg School of Public Health: Developing a legal review and toolkit for reviewing the health claims for food marketed to children and their families">Johns Hopkins University Bloomberg School of Public Health: Developing a legal review and toolkit for reviewing the health claims for food marketed to children and their families</option>
				<option value="Johns Hopkins University Bloomberg School of Public Health: Simplifying caloric labeling on sugar-sweetened beverages to reduce consumption of excess calories">Johns Hopkins University Bloomberg School of Public Health: Simplifying caloric labeling on sugar-sweetened beverages to reduce consumption of excess calories</option>
				<option value="League of American Bicyclists: Increasing diversity and equity in bicycle advocacy">League of American Bicyclists: Increasing diversity and equity in bicycle advocacy</option>
				<option value="Local Government Commission: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">Local Government Commission: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="Loyola Marymount University Bellarmine College of Liberal Arts: Evaluating changes associated with advocacy networks within the Communities Creating Healthy Environments' national program">Loyola Marymount University Bellarmine College of Liberal Arts: Evaluating changes associated with advocacy networks within the Communities Creating Healthy Environments' national program</option>
				<option value="Loyola Marymount University Bellarmine College of Liberal Arts: Expanding the scale and capacity of the evaluation of Communities Creating Healthy Environments">Loyola Marymount University Bellarmine College of Liberal Arts: Expanding the scale and capacity of the evaluation of Communities Creating Healthy Environments</option>
				<option value="Loyola Marymount University Bellarmine College of Liberal Arts: Providing evaluation technical assistance and consultation to the national program office of RWJF's Communities Creating Healthy Environment program">Loyola Marymount University Bellarmine College of Liberal Arts: Providing evaluation technical assistance and consultation to the national program office of RWJF's Communities Creating Healthy Environment program</option>
				<option value="Loyola Marymount University Bellarmine College of Liberal Arts: Upgrading support for evaluation of the Communities Creating Healthy Environments initiative">Loyola Marymount University Bellarmine College of Liberal Arts: Upgrading support for evaluation of the Communities Creating Healthy Environments initiative</option>
				<option value="LTG Associates, Inc.: Exploring the facilitators and barriers in addressing childhood obesity in Asian American and Pacific Islander communities">LTG Associates, Inc.: Exploring the facilitators and barriers in addressing childhood obesity in Asian American and Pacific Islander communities</option>
				<option value="Mary Ann Scheirer: Conducting evaluability assessments and providing evaluation support for the five New Jersey Partnership for Healthy Kids communities">Mary Ann Scheirer: Conducting evaluability assessments and providing evaluation support for the five New Jersey Partnership for Healthy Kids communities</option>
				<option value="Mathematica Policy Research, Inc.: Tracking the performance indicators for RWJF's Childhood Obesity grantmaking team">Mathematica Policy Research, Inc.: Tracking the performance indicators for RWJF's Childhood Obesity grantmaking team</option>
				<option value="Media Management Services Inc. dba MMS Education: Supporting planning and implementation for the RWJF Early Childhood Obesity Prevention authorization">Media Management Services Inc. dba MMS Education: Supporting planning and implementation for the RWJF Early Childhood Obesity Prevention authorization</option>
				<option value="Medscape LLC: Building the advocacy skills of health care professionals to reduce childhood obesity">Medscape LLC: Building the advocacy skills of health care professionals to reduce childhood obesity</option>
				<option value="Meridian Institute: Supporting AGree, an initiative to transform food and agricultural policy, 2013-2015">Meridian Institute: Supporting AGree, an initiative to transform food and agricultural policy, 2013-2015</option>
				<option value="Merrimack College School of Science and Engineering: Advancing the Active Science initiative to improve physical activity and science competency for school-age children">Merrimack College School of Science and Engineering: Advancing the Active Science initiative to improve physical activity and science competency for school-age children</option>
				<option value="Movement Strategy Center: Supporting the Alliance for Educational Justice in leveraging education advocates to reverse the epidemic of childhood obesity">Movement Strategy Center: Supporting the Alliance for Educational Justice in leveraging education advocates to reverse the epidemic of childhood obesity</option>
				<option value="National Academy of Sciences, Institute of Medicine: Establishing the Roundtable on Obesity Solutions to accelerate progress in prevention and control">National Academy of Sciences, Institute of Medicine: Establishing the Roundtable on Obesity Solutions to accelerate progress in prevention and control</option>
				<option value="National Academy of Sciences, Institute of Medicine: Supporting the National Childhood Obesity Prevention Committee in accelerating action to prevent childhood obesity">National Academy of Sciences, Institute of Medicine: Supporting the National Childhood Obesity Prevention Committee in accelerating action to prevent childhood obesity</option>
				<option value="National Asian Pacific American Caucus of State Legislators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">National Asian Pacific American Caucus of State Legislators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="National Association for the Advancement of Colored People (NAACP): Mobilizing NAACP branches nationally to engage public leaders and communities in a coordinated effort to reduce childhood obesity">National Association for the Advancement of Colored People (NAACP): Mobilizing NAACP branches nationally to engage public leaders and communities in a coordinated effort to reduce childhood obesity</option>
				<option value="National Association of County and City Health Officials (NACCHO): Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">National Association of County and City Health Officials (NACCHO): Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="National Caucus of Native American State Legislators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">National Caucus of Native American State Legislators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="National Conference of State Legislatures: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">National Conference of State Legislatures: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="National Council of La Raza (NCLR): Improving access to affordable foods and reducing exposure to unhealthy-food marketing in the Latino community to reduce childhood obesity">National Council of La Raza (NCLR): Improving access to affordable foods and reducing exposure to unhealthy-food marketing in the Latino community to reduce childhood obesity</option>
				<option value="National Education Association Health Information Network: Engaging National Education Association leaders and members in advocating the elimination of unhealthy competitive foods in schools, 2014-2015">National Education Association Health Information Network: Engaging National Education Association leaders and members in advocating the elimination of unhealthy competitive foods in schools, 2014-2015</option>
				<option value="National Hispanic Caucus of State Legislators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">National Hispanic Caucus of State Legislators: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="National Initiative for Children's Healthcare Quality Inc. (NICHQ): Making healthy weight plans for families a standard of practice among health care professionals">National Initiative for Children's Healthcare Quality Inc. (NICHQ): Making healthy weight plans for families a standard of practice among health care professionals</option>
				<option value="National League of Cities Institute Inc.: Answering the call: Key strategies for municipal leaders, 2012-2014">National League of Cities Institute Inc.: Answering the call: Key strategies for municipal leaders, 2012-2014</option>
				<option value="New Jersey YMCA State Alliance Inc.: Supporting activities of the New Jersey YMCA State Alliance that contribute to the reduction of childhood obesity in the state">New Jersey YMCA State Alliance Inc.: Supporting activities of the New Jersey YMCA State Alliance that contribute to the reduction of childhood obesity in the state</option>
				<option value="New Jersey YMCA State Alliance Inc.: Technical assistance and direction for RWJF's New Jersey Partnership for Healthy Kids: Communities Making a Difference to Prevent Childhood Obesity">New Jersey YMCA State Alliance Inc.: Technical assistance and direction for RWJF's New Jersey Partnership for Healthy Kids: Communities Making a Difference to Prevent Childhood Obesity</option>
				<option value="New York University School of Medicine: Establishing a second evaluation baseline before implementation of New York City's policy to limit sugar-sweetened beverages">New York University School of Medicine: Establishing a second evaluation baseline before implementation of New York City's policy to limit sugar-sweetened beverages</option>
				<option value="Notah Begay III Foundation: Developing and implementing a national initiative to reduce childhood obesity in targeted American Indian communities">Notah Begay III Foundation: Developing and implementing a national initiative to reduce childhood obesity in targeted American Indian communities</option>
				<option value="Partnership for a Healthier America, Inc.: Developing a common vision, platform and plan of action to rally private-sector stakeholders behind reducing the childhood obesity epidemic, 2014-2015">Partnership for a Healthier America, Inc.: Developing a common vision, platform and plan of action to rally private-sector stakeholders behind reducing the childhood obesity epidemic, 2014-2015</option>
				<option value="Pew Charitable Trusts: Strengthening the Kids' Safe and Healthful Foods Project to ensure improved nutrition in schools">Pew Charitable Trusts: Strengthening the Kids' Safe and Healthful Foods Project to ensure improved nutrition in schools</option>
				<option value="PICO National Network: Supporting PICO National Network's initiative to prevent childhood obesity">PICO National Network: Supporting PICO National Network's initiative to prevent childhood obesity</option>
				<option value="PolicyLink: Expanding a Web portal to support projects improving access to healthy foods">PolicyLink: Expanding a Web portal to support projects improving access to healthy foods</option>
				<option value="Praxis Project, Inc.: Technical assistance and direction for Communities Creating Healthy Environments: Leveraging National Return on Local Investments program, 2014-2015">Praxis Project, Inc.: Technical assistance and direction for Communities Creating Healthy Environments: Leveraging National Return on Local Investments program, 2014-2015</option>
				<option value="Public Health Institute: Evaluating the public debate over fast-food zoning ordinances to inform efforts to prevent childhood obesity">Public Health Institute: Evaluating the public debate over fast-food zoning ordinances to inform efforts to prevent childhood obesity</option>
				<option value="Public Health Solutions: Pairing analysis of food-supply chains with market-development efforts to offer healthful, regionally sourced and sustainably produced school meals">Public Health Solutions: Pairing analysis of food-supply chains with market-development efforts to offer healthful, regionally sourced and sustainably produced school meals</option>
				<option value="Public Health Solutions: Supporting the 2014 School Food FOCUS National Gathering">Public Health Solutions: Supporting the 2014 School Food FOCUS National Gathering</option>
				<option value="Rand Corporation: Assessing the relative impact of home-food and local-supermarket environments on children's diets in low-resource African American neighborhoods">Rand Corporation: Assessing the relative impact of home-food and local-supermarket environments on children's diets in low-resource African American neighborhoods</option>
				<option value="Raritan Valley YMCA: Supporting the local partnership to prevent childhood obesity in New Brunswick, N.J., 2013-2015">Raritan Valley YMCA: Supporting the local partnership to prevent childhood obesity in New Brunswick, N.J., 2013-2015</option>
				<option value="Research Triangle Institute (RTI): Assessing the impact of food restrictions under the Supplemental Nutrition Assistance Program on food choices by children and families">Research Triangle Institute (RTI): Assessing the impact of food restrictions under the Supplemental Nutrition Assistance Program on food choices by children and families</option>
				<option value="Research Triangle Institute (RTI): Increasing peer-reviewed literature in the field of out-of-school-time programs to promote healthy eating and physical activity">Research Triangle Institute (RTI): Increasing peer-reviewed literature in the field of out-of-school-time programs to promote healthy eating and physical activity</option>
				<option value="Research Triangle Institute (RTI): Understanding how food pricing and access affect the diets of children and their families">Research Triangle Institute (RTI): Understanding how food pricing and access affect the diets of children and their families</option>
				<option value="Salvation Army: Supporting replication of the Kroc Fit Kids obesity prevention program and the study of its implementation">Salvation Army: Supporting replication of the Kroc Fit Kids obesity prevention program and the study of its implementation</option>
				<option value="Samuels and Associates dba The Sarah Samuels Center for Public Health Research &amp; Evaluation: Investigating nutrition standards in seven California counties to inform policy at local, state, and federal levels">Samuels and Associates dba The Sarah Samuels Center for Public Health Research &amp; Evaluation: Investigating nutrition standards in seven California counties to inform policy at local, state, and federal levels</option>
				<option value="Stanford University School of Medicine: Improving healthy eating among children through changes in Supplemental Nutrition Assistance Program (SNAP) policies: An economic microsimulation">Stanford University School of Medicine: Improving healthy eating among children through changes in Supplemental Nutrition Assistance Program (SNAP) policies: An economic microsimulation</option>
				<option value="Strategic Concepts in Organizing and Policy Education (SCOPE): Continuing Strategic Concepts in Organizing and Policy Education's work with CCHE to prevent childhood obesity, 2014">Strategic Concepts in Organizing and Policy Education (SCOPE): Continuing Strategic Concepts in Organizing and Policy Education's work with CCHE to prevent childhood obesity, 2014</option>
				<option value="Texas A&amp;M University Health Science Center School of Rural Public Health: Extending the collaborative evaluation of the Safe Routes to School and Women, Infants, and Children programs in Texas -- Texas A&amp;M University">Texas A&amp;M University Health Science Center School of Rural Public Health: Extending the collaborative evaluation of the Safe Routes to School and Women, Infants, and Children programs in Texas -- Texas A&amp;M University</option>
				<option value="Texas Health Institute: Working to unite 16 Southern states in support of strategies to reduce and prevent obesity">Texas Health Institute: Working to unite 16 Southern states in support of strategies to reduce and prevent obesity</option>
				<option value="Third Sector New England: Increasing capacity at the local level for social intervention to prevent childhood obesity (Year 5)">Third Sector New England: Increasing capacity at the local level for social intervention to prevent childhood obesity (Year 5)</option>
				<option value="Third Sector New England: Maintaining and growing the impact of Active Living by Design">Third Sector New England: Maintaining and growing the impact of Active Living by Design</option>
				<option value="Third Sector New England: Technical assistance and direction for RWJF's Healthy Kids, Healthy Communities program, 2014">Third Sector New England: Technical assistance and direction for RWJF's Healthy Kids, Healthy Communities program, 2014</option>
				<option value="Transtria LLC: Supporting and disseminating a Healthy Kids, Healthy Communities evaluation supplement to underscore the impact of the initiative">Transtria LLC: Supporting and disseminating a Healthy Kids, Healthy Communities evaluation supplement to underscore the impact of the initiative</option>
				<option value="Transtria LLC: Updating the review of research on environmental and policy interventions for childhood obesity prevention">Transtria LLC: Updating the review of research on environmental and policy interventions for childhood obesity prevention</option>
				<option value="Tufts University Friedman School of Nutrition Science and Policy: Supporting ChildObesity180 in having a measurable impact on reversing the trend in childhood obesity, 2012-2014">Tufts University Friedman School of Nutrition Science and Policy: Supporting ChildObesity180 in having a measurable impact on reversing the trend in childhood obesity, 2012-2014</option>
				<option value="Tulane University School of Public Health and Tropical Medicine: Increasing physical activity levels of lower-income children and families through the KidsWalk Coalition of New Orleans">Tulane University School of Public Health and Tropical Medicine: Increasing physical activity levels of lower-income children and families through the KidsWalk Coalition of New Orleans</option>
				<option value="U.S. Soccer Foundation: Supporting the 2014 Urban Soccer Symposium">U.S. Soccer Foundation: Supporting the 2014 Urban Soccer Symposium</option>
				<option value="U.S. Soccer Foundation: Supporting the U.S. Soccer Foundation's Safe Places to Play program in low-income urban communities">U.S. Soccer Foundation: Supporting the U.S. Soccer Foundation's Safe Places to Play program in low-income urban communities</option>
				<option value="United States Conference of Mayors: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">United States Conference of Mayors: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="United Way of Greater Philadelphia and Southern New Jersey: Supporting the local partnership to prevent childhood obesity in Camden, N.J., 2013-2015">United Way of Greater Philadelphia and Southern New Jersey: Supporting the local partnership to prevent childhood obesity in Camden, N.J., 2013-2015</option>
				<option value="University of California, Berkeley, School of Public Health: Studying the impact of the Healthy Schools Program on students' body mass index as a measure of obesity">University of California, Berkeley, School of Public Health: Studying the impact of the Healthy Schools Program on students' body mass index as a measure of obesity</option>
				<option value="University of California, San Francisco, School of Medicine, Philip R. Lee Institute for Health Policy Studies: Examining students' water intake in schools as a tactic to help prevent obesity">University of California, San Francisco, School of Medicine, Philip R. Lee Institute for Health Policy Studies: Examining students' water intake in schools as a tactic to help prevent obesity</option>
				<option value="University of Colorado School of Medicine: Providing leadership in designing and implementing new initiatives for childhood obesity and other areas, 2013-2015">University of Colorado School of Medicine: Providing leadership in designing and implementing new initiatives for childhood obesity and other areas, 2013-2015</option>
				<option value="University of Illinois at Chicago Institute for Health Research and Policy: Bridging the Gap: Research Informing Practice and Policy for Healthy Youth Behavior, 2012-2014">University of Illinois at Chicago Institute for Health Research and Policy: Bridging the Gap: Research Informing Practice and Policy for Healthy Youth Behavior, 2012-2014</option>
				<option value="University of Illinois at Chicago Institute for Health Research and Policy: Eye-tracking children's fast-food choices as influenced by television advertising">University of Illinois at Chicago Institute for Health Research and Policy: Eye-tracking children's fast-food choices as influenced by television advertising</option>
				<option value="University of Illinois at Chicago Institute for Health Research and Policy: Studying the 'health in all policies' impact on population health of changes in a range of state economic laws over the last 20 years">University of Illinois at Chicago Institute for Health Research and Policy: Studying the 'health in all policies' impact on population health of changes in a range of state economic laws over the last 20 years</option>
				<option value="University of Michigan Institute for Social Research: Bridging the Gap: Research Informing Practice and Policy for Healthy Youth Behavior">University of Michigan Institute for Social Research: Bridging the Gap: Research Informing Practice and Policy for Healthy Youth Behavior</option>
				<option value="University of Minnesota School of Public Health: Supporting Healthy Eating Research's 2012-2015 activities">University of Minnesota School of Public Health: Supporting Healthy Eating Research's 2012-2015 activities</option>
				<option value="University of Minnesota School of Public Health: Technical assistance and direction for RWJF's Healthy Eating Research: Building Evidence to Prevent Childhood Obesity program, 2013-2014">University of Minnesota School of Public Health: Technical assistance and direction for RWJF's Healthy Eating Research: Building Evidence to Prevent Childhood Obesity program, 2013-2014</option>
				<option value="University of Missouri-Columbia College of Agriculture, Food and Natural Resources, CARES: Developing and implementing the infrastructure for a national Web-based geographic information system for childhood obesity prevention (Phase 3)">University of Missouri-Columbia College of Agriculture, Food and Natural Resources, CARES: Developing and implementing the infrastructure for a national Web-based geographic information system for childhood obesity prevention (Phase 3)</option>
				<option value="University of New England School of Community and Population Health: Investigating how to align schools' marketing polices with federal standards for competitive foods">University of New England School of Community and Population Health: Investigating how to align schools' marketing polices with federal standards for competitive foods</option>
				<option value="University of North Carolina at Chapel Hill Center for Health Promotion and Disease Prevention: Evaluating the impact of the Veggie Van program in underserved communities on youths' dietary intake">University of North Carolina at Chapel Hill Center for Health Promotion and Disease Prevention: Evaluating the impact of the Veggie Van program in underserved communities on youths' dietary intake</option>
				<option value="University of North Carolina at Chapel Hill Gillings School of Global Public Health: Assessing nutrition and physical activity practices and policies of child-care centers in states with the highest obesity rates">University of North Carolina at Chapel Hill Gillings School of Global Public Health: Assessing nutrition and physical activity practices and policies of child-care centers in states with the highest obesity rates</option>
				<option value="University of North Carolina at Chapel Hill Gillings School of Global Public Health: Evaluating the impact of a Web-based intervention designed for child-care providers to improve their food and physical activity environments">University of North Carolina at Chapel Hill Gillings School of Global Public Health: Evaluating the impact of a Web-based intervention designed for child-care providers to improve their food and physical activity environments</option>
				<option value="University of Texas Health Science Center at Houston School of Public Health: Extending the collaborative evaluation of the Safe Routes to School and Women, Infants, and Children programs in Texas -- University of Texas">University of Texas Health Science Center at Houston School of Public Health: Extending the collaborative evaluation of the Safe Routes to School and Women, Infants, and Children programs in Texas -- University of Texas</option>
				<option value="University of Texas Health Science Center at San Antonio: Salud America! The RWJF Research Network to Prevent Obesity Among Latino Children">University of Texas Health Science Center at San Antonio: Salud America! The RWJF Research Network to Prevent Obesity Among Latino Children</option>
				<option value="University of Washington Center for Public Health Nutrition: Evaluating the long-term impact on obesity and nutrition of menu labeling in schools">University of Washington Center for Public Health Nutrition: Evaluating the long-term impact on obesity and nutrition of menu labeling in schools</option>
				<option value="University of Washington School of Public Health: Informing school policies and practices to ensure access to free high-quality drinking water to reduce children's consumption of sugary beverages">University of Washington School of Public Health: Informing school policies and practices to ensure access to free high-quality drinking water to reduce children's consumption of sugary beverages</option>
				<option value="Washington University in St. Louis, George Warren Brown School of Social Work: Analyzing influences on legislation to prevent childhood obesity">Washington University in St. Louis, George Warren Brown School of Social Work: Analyzing influences on legislation to prevent childhood obesity</option>
				<option value="Westat, Inc.: Using focus groups from vulnerable populations to explore cultural and community perspectives on healthy weight">Westat, Inc.: Using focus groups from vulnerable populations to explore cultural and community perspectives on healthy weight</option>
				<option value="Women in Government Foundation, Inc.: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living">Women in Government Foundation, Inc.: Leadership for Healthy Communities: Advancing Policies to Support Healthy Eating and Active Living</option>
				<option value="Yale University Rudd Center for Food Policy and Obesity: Encouraging industry and government action to reduce marketing unhealthy foods to children">Yale University Rudd Center for Food Policy and Obesity: Encouraging industry and government action to reduce marketing unhealthy foods to children</option>
				<option value="Yale University Rudd Center for Food Policy and Obesity: Examining student reactions to varied strategies  for presenting and promoting healthy and unhealthy school lunch offerings">Yale University Rudd Center for Food Policy and Obesity: Examining student reactions to varied strategies  for presenting and promoting healthy and unhealthy school lunch offerings</option>
				<option value="YMCA of the USA Chicago: Support for the YMCA's  Pioneering Healthier Communities local and state policy-change initiative around childhood obesity, Phase 1">YMCA of the USA Chicago: Support for the YMCA's  Pioneering Healthier Communities local and state policy-change initiative around childhood obesity, Phase 1</option>
				<option value="YMCA of the USA Chicago: Support for the YMCA's Pioneering Healthier Communities' state policy-change initiative around childhood obesity, Phase 2">YMCA of the USA Chicago: Support for the YMCA's Pioneering Healthier Communities' state policy-change initiative around childhood obesity, Phase 2</option>
				<option value="Young Men's and Women's Christian Association of Newark and Vicinity: Supporting the local partnership to prevent childhood obesity in the Central Ward of Newark, N.J., 2013-2015">Young Men's and Women's Christian Association of Newark and Vicinity: Supporting the local partnership to prevent childhood obesity in the Central Ward of Newark, N.J., 2013-2015</option>
				<option value="Young Men's Christian Association of Trenton NJ Inc. (YMCA): Supporting the local partnership to prevent childhood obesity in the North Ward of Trenton, N.J., 2013-2015">Young Men's Christian Association of Trenton NJ Inc. (YMCA): Supporting the local partnership to prevent childhood obesity in the North Ward of Trenton, N.J., 2013-2015</option>
			</select>
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