<?php
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( 'LP_Profile' ) ) {
	/**
	 * Class LP_Profile
	 *
	 * Main class to controls the profile of a user
	 */
	class LP_Profile {
		/**
		 * The instances of all users has initialed a profile
		 *
		 * @var array
		 */
		protected static $_instances = array();

		/**
		 * @var LP_User
		 */
		protected $_user = false;

		/**
		 *  Constructor
		 */
		public function __construct( $user ) {
			$this->_user = $user;
			$this->get_user();
			add_action( 'wp_enqueue_scripts', array($this, 'load_date_picker_script') ,10 );
		}

		public function load_date_picker_script() {
			$url = RWMB_CSS_URL . 'jqueryui';
		wp_register_style( 'jquery-ui-core', "$url/jquery.ui.core.css", array(), '1.8.17' );
		wp_register_style( 'jquery-ui-theme', "$url/jquery.ui.theme.css", array(), '1.8.17' );
		wp_register_style( 'jquery-ui-datepicker', "$url/jquery.ui.datepicker.css", array( 'jquery-ui-core', 'jquery-ui-theme' ), '1.8.17' );
			wp_enqueue_style( 'jquery-ui-datepicker' );
		    wp_enqueue_script( 'learn-press-date-picker',LP_JS_URL . 'chart.min.js', array( 'jquery', 'jquery-ui-datepicker' ) );
		  }

		public function get_user() {
			if ( is_numeric( $this->_user ) ) {
				$this->_user = learn_press_get_user( $this->_user );
			} elseif ( empty( $this->_user ) ) {
				$this->_user = learn_press_get_current_user();
			}
			return $this->_user;
		}

		public function get_tabs() {
			$course_endpoint = LP()->settings->get( 'profile_endpoints.profile-courses' );
			if ( !$course_endpoint ) {
				$course_endpoint = 'profile-courses';
			}

			$quiz_endpoint = LP()->settings->get( 'profile_endpoints.profile-quizzes' );
			if ( !$quiz_endpoint ) {
				$quiz_endpoint = 'profile-quizzes';
			}

			$order_endpoint = LP()->settings->get( 'profile_endpoints.profile-orders' );
			if ( !$order_endpoint ) {
				$order_endpoint = 'profile-orders';
			}

			$view_order_endpoint = LP()->settings->get( 'profile_endpoints' );
			if ( !$view_order_endpoint ) {
				$view_order_endpoint = 'order';
			}

			$defaults = array(

				$course_endpoint => array(
					'title'    => __( 'Courses', 'learnpress' ),
					'base'     => 'courses',
					'callback' => 'learn_press_profile_tab_courses_content'
				)
			);

			if ( $this->_user->id == get_current_user_id() ) {
				$defaults[$order_endpoint] = array(
					'title'    => __( 'Orders', 'learnpress' ),
					'base'     => 'orders',
					'callback' => 'learn_press_profile_tab_orders_content'
				);
			}

			$tabs = apply_filters( 'learn_press_user_profile_tabs', $defaults, $this->_user );
			if ( $this->_user->id == get_current_user_id() ) {
				$tabs['settings'] = array(
					'title'    => apply_filters( 'learn_press_user_profile_tab_edit_title', __( 'Settings', 'learnpress' ) ),
					'base'     => 'settings',
					'callback' => 'learn_press_profile_tab_edit_content'
				);
			}

			foreach ( $tabs as $slug => $opt ) {
				if ( !empty( $defaults[$slug] ) ) {
					continue;
				}
				LP()->query_vars[$slug] = $slug;
				add_rewrite_endpoint( $slug, EP_PAGES );
			}

			return $tabs;
		}

		/**
		 * Get an instance of LP_Profile for a user id
		 *
		 * @param $user_id
		 *
		 * @return LP_Profile mixed
		 */
		public static function instance( $user_id = 0 ) {
			if ( !$user_id ) {
				$user_id = get_current_user_id();
			}
			if ( empty( self::$_instances[$user_id] ) ) {
				self::$_instances[$user_id] = new self( $user_id );
			}
			return self::$_instances[$user_id];
		}
	}
}