<?php
/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit;

define( 'LP_ADDON_CERTIFICATES_FILE', __FILE__ );
define( 'LP_ADDON_CERTIFICATES_PATH', dirname( __FILE__ ) );
define( 'LP_ADDON_CERTIFICATES_VER', '2.2.9' );
define( 'LP_ADDON_CERTIFICATES_REQUIRE_VER', '2.0' );

/**
 * Class LP_Addon_Certificates
 */
class LP_Addon_Certificates {
	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @var string
	 */
	public $tbl_user_items = '';

	/**
	 * @var array
	 */
	private $finish_course_data = array();

	function __construct() {
		$this->tbl_user_items = version_compare( LEARNPRESS_VERSION, '2.0', '<' ) ? 'learnpress_user_course' : 'learnpress_user_items';
		$this->includes();
		add_action( 'init', array( $this, 'register_post_type' ), 1000 );
		add_action( 'init', array( $this, 'download' ) );
		add_action( 'init', array( $this, 'process_export' ) );
		add_action( 'init', array( __CLASS__, 'load_text_domain' ) );

		add_action( 'admin_menu', array( $this, 'remove_meta_boxes' ) );
		add_action( 'admin_init', array( $this, 'process_import' ) );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_assets' ) );
		add_action( 'save_post', array( $this, 'update_cert' ) );
		add_action( 'manage_lp_cert_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );
		add_action( 'manage_lp_course_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );

		add_filter( 'manage_edit-lp_course_columns', array( $this, 'columns_head' ) );
		add_filter( 'manage_edit-lp_cert_columns', array( $this, 'columns_head' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );

		add_filter( 'learn_press_certificate_field_options', array( $this, 'field_options' ), 10, 2 );
		add_filter( 'learn_press_user_profile_tabs', array( $this, 'certificates_tab' ), 105, 2 );
		add_filter( 'learn_press_profile_tab_endpoints', array( $this, 'profile_tab_endpoints' ) );
		add_action( 'learn_press_user_finish_course', array( $this, 'on_finish_course' ), 100, 3 );

		add_action( 'template_include', array( $this, 'cert_preview' ), 9999999 );

		add_action( 'learn_press_settings_tabs_array', array( $this, 'add_tab_settings' ) );

		if ( version_compare( LEARNPRESS_VERSION, '2.0', '<' ) ) {
			add_action( 'learn_press_content_learning_summary', array( $this, 'popup_cert' ), 70 );
		} else {
			add_action( 'learn_press_after_course_buttons', array( $this, 'popup_cert' ), 70 );
		}
		add_action( 'wp_ajax_learn-press-cert-load-field', array( $this, 'load_field' ) );
		add_action( 'wp_ajax_learn-press-cert-download', array( $this, 'generate_download' ) );
		add_action( 'wp_ajax_certificate-import', array( $this, 'do_import' ) );

		add_action( 'learn_press_user_finish_course', array( $this, 'finished_course' ), 0, 3 );
		add_filter( 'learn_press_email_format_string_find', array( $this, 'email_format_string_find' ), 10, 2 );
		add_filter( 'learn_press_email_format_string_replace', array( $this, 'email_format_string_replace' ), 10, 2 );
		add_action( 'learn_press_emails_init', array( $this, 'add_emails' ), 10 );
		add_action( 'learn_press_user_finish_course_notification', array( $this, 'trigger' ), 10, 3 );

		$plugin_basename = $this->plugin_basename( __FILE__ );
		add_action( 'activate_' . $plugin_basename, array( $this, 'create_sample_cert' ) );

		add_filter( 'learn_press_lp_course_tabs', array( $this, 'add_tab' ) );
	}

	public static function load_text_domain() {

		if ( function_exists( 'learn_press_load_plugin_text_domain' ) ) {
			learn_press_load_plugin_text_domain( LP_ADDON_CERTIFICATES_PATH, true );
		}
	}

	public function add_tab( $tabs ) {
		$tabs[] = array(
			'callback' => array( $this, 'course_tab' ),
			'meta_box' => 'learn-press-certs-meta-box'
		);
		return $tabs;
	}

	public function trigger( $course_id, $user_id, $result ) {
		remove_action( 'learn_press_user_finish_course_notification', array( $this, 'trigger' ), 10, 3 );
		$user = LP_User_Factory::get_user( $user_id );
		if ( $user->has_passed_course( $course_id ) ) {
			LP_Emails::instance()->emails['LP_Email_Certificate_User']->trigger( $course_id, $user_id, $result );
		}
	}

	public function add_emails( $obj ) {
		$obj->emails['LP_Email_Certificate_User'] = include( 'incs/class-lp-email-certificate-user.php' );
	}

	public function finished_course( $course_id, $user_id, $result ) {
		$this->finish_course_data = array(
			'course_id' => $course_id,
			'user_id'   => $user_id,
			'data'      => $result
		);
	}

	public function email_format_string_find( $find, $email ) {
		remove_filter( 'learn_press_email_format_string_find', array( $this, 'email_format_string_find' ), 10, 2 );
		$find[] = '{{user_certificate_link}}';
		$find[] = '{{user_certificate_raw_link}}';
		$find[] = '{{user_certificate_preview}}';
		add_filter( 'learn_press_email_format_string_find', array( $this, 'email_format_string_find' ), 10, 2 );
		return $find;
	}

	/**
	 * @param array    $replace
	 * @param LP_Email $email
	 *
	 * @return array
	 */
	public function email_format_string_replace( $replace, $email ) {
		remove_filter( 'learn_press_email_format_string_replace', array( $this, 'email_format_string_replace' ), 10, 2 );

		$email_cert_link     = '';
		$email_cert_raw_link = '';
		$email_cert_preview  = '';
		if ( $this->finish_course_data ) {
			if ( $cert_id = get_post_meta( $this->finish_course_data['course_id'], '_lp_cert', true ) ) {
				$email_cert_raw_link = learn_press_certificate_permalink( $cert_id, $this->finish_course_data['course_id'] );
				if ( $email->get_content_format() == 'text/html' ) {
					$email_cert_link    = sprintf( '<a href="%s">%s</a>', $email_cert_raw_link, __( 'View', 'learnpress-certificates' ) );
					$email_cert_preview = sprintf( '<img src="%s" />', get_post_meta( $cert_id, '_lp_cert_preview', true ) );
				} else {
					$email_cert_link = $email_cert_raw_link;
				}
			}
		}
		$replace[] = $email_cert_link;
		$replace[] = $email_cert_raw_link;
		$replace[] = $email_cert_preview;
		add_filter( 'learn_press_email_format_string_replace', array( $this, 'email_format_string_replace' ), 10, 2 );

		return $replace;
	}

	public function add_tab_settings( $tabs ) {
		require_once LP_ADDON_CERTIFICATES_PATH . '/incs/class-admin-settings.php';

		$tabs['certificates'] = __( 'Certificates', 'lealearnpress-certificatesrnpress' );
		return $tabs;
	}

	public function create_sample_cert() {
		if ( !$certs = $this->exists_cert() ) {
			$zip_file = dirname( __FILE__ ) . '/incs/certificate-sample.zip';
			if ( file_exists( $zip_file ) ) {
				$return = $this->import_from_file( $zip_file );
			}
		}
		LP_Debug::instance()->add( $certs );

	}

	protected function exists_cert() {
		$query = array(
			'post_type'   => 'lp_cert',
			'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash' )
		);
		$q     = new WP_Query( $query );
		return $q->have_posts();
	}

	private function plugin_basename( $filepath ) {
		$file          = str_replace( '\\', '/', $filepath );
		$file          = preg_replace( '|/+|', '/', $file ); // remove any duplicate slash
		$plugin_dir    = str_replace( '\\', '/', WP_PLUGIN_DIR ); // sanitize for Win32 installs
		$plugin_dir    = preg_replace( '|/+|', '/', $plugin_dir ); // remove any duplicate slash
		$mu_plugin_dir = str_replace( '\\', '/', WPMU_PLUGIN_DIR ); // sanitize for Win32 installs
		$mu_plugin_dir = preg_replace( '|/+|', '/', $mu_plugin_dir ); // remove any duplicate slash
		$sp_plugin_dir = dirname( $filepath );
		$sp_plugin_dir = dirname( $sp_plugin_dir );

		$sp_plugin_dir = str_replace( '\\', '/', $sp_plugin_dir ); // sanitize for Win32 installs
		$sp_plugin_dir = preg_replace( '|/+|', '/', $sp_plugin_dir ); // remove any duplicate slash

		$file = preg_replace( '#^' . preg_quote( $sp_plugin_dir, '#' ) . '/|^' . preg_quote( $plugin_dir, '#' ) . '/|^' . preg_quote( $mu_plugin_dir, '#' ) . '/#', '', $file ); // get relative path from plugins dir
		$file = trim( $file, '/' );
		return strtolower( $file );
	}

	public function admin_head() {
		?>
		<script type="text/javascript">
			var certificate_localize = {
				import               : '<?php _e( 'Import from Zip', 'learnpress-certificates' );?>',
				import_sample        : '<?php _e( 'Import Sample', 'learnpress-certificates' );?>',
				upload_title         : '<?php _e( 'Select a zip file', 'learnpress-certificates' );?>',
				confirm_import_sample: '<?php _e( 'Do you want to import sample of certificate?', 'learnpress-certificates' );?>'
			}
		</script>
		<?php
	}

	public function process_import() {
		if ( empty( $_REQUEST['import-cert'] ) || $_REQUEST['import-cert'] != 'sample' ) {
			return;
		}
		$zip_file = dirname( __FILE__ ) . '/incs/certificate-sample.zip';
		if ( file_exists( $zip_file ) ) {
			$return = $this->import_from_file( $zip_file );
		}
		wp_redirect( admin_url( 'edit.php?post_type=lp_cert' ) );
		die();
	}

	public function process_export() {
		$cert_id = !empty( $_REQUEST['export'] ) ? $_REQUEST['export'] : 0;
		if ( get_post_type( $cert_id ) !== 'lp_cert' ) {
			return;
		}
		if ( !class_exists( 'PclZip' ) ) {
			require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
		}
		$cert       = $this->_create_cert_object( $cert_id );
		$upload_dir = learn_press_certificate_upload_dir( "_exports" );

		$zip = new PclZip( $upload_dir['userpath'] . "/certificate-{$cert_id}.zip" );

		$data_files = array(
			'cert.json' => serialize( $cert )
		);

		if ( !empty( $cert->_lp_cert_template ) ) {
			$name              = basename( $cert->_lp_cert_template );
			$data_files[$name] = @file_get_contents( $cert->_lp_cert_template );
		}

		if ( !empty( $cert->_lp_cert_preview ) ) {
			$name              = basename( $cert->_lp_cert_preview );
			$data_files[$name] = @file_get_contents( $cert->_lp_cert_preview );
		}

		foreach ( $data_files as $name => $data ) {
			file_put_contents( $upload_dir['userpath'] . '/' . $name, $data );
			$data_files[$name] = $upload_dir['userpath'] . '/' . $name;
		}

		if ( $zip->create( $data_files, PCLZIP_OPT_REMOVE_PATH, $upload_dir['userpath'] ) ) {
			header( 'Content-Disposition: attachment; filename=' . "certificate-{$cert_id}.zip" );
			header( 'Content-Length: ' . filesize( $zip->zipname ) );
			readfile( $zip->zipname );
		}
		foreach ( $data_files as $path ) {
			@unlink( $path );
		}
		die();
	}

	public function do_import() {
		$url = !empty( $_REQUEST['url'] ) ? $_REQUEST['url'] : '';
		if ( !$url ) {
			return;
		}
		$zip_file = $this->url_to_path( $url );
		$return   = $this->import_from_file( $zip_file );
		learn_press_send_json( $return );
	}

	public function import_from_file( $zip_file ) {
		if ( !file_exists( $zip_file ) || !preg_match( '/.zip$/', $zip_file ) ) {
			return;
		}
		if ( !function_exists( 'unzip_file' ) ) {
			include_once ABSPATH . '/wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$import_dir = learn_press_certificate_upload_dir( "_imports/" . uniqid() );
		$result     = unzip_file( $zip_file, $import_dir['userpath'] );
		if ( is_wp_error( $result ) ) {
			return;
		}
		if ( !file_exists( $import_dir['userpath'] . '/cert.json' ) ) {
			return;
		}
		$cert = @maybe_unserialize( file_get_contents( $import_dir['userpath'] . '/cert.json' ) );
		if ( !$cert ) {
			return;
		}
		$cert_data = (array) $cert;
		if ( !empty( $cert_data['ID'] ) ) {
			unset( $cert_data['ID'] );
		}
		$cert_id = wp_insert_post( $cert_data );
		if ( $cert_id && !empty( $cert_data['meta'] ) ) {
			$upload_dir = learn_press_certificate_upload_dir( '' );
			foreach ( $cert_data['meta'] as $key => $value ) {
				switch ( $key ) {
					case '_lp_cert_template':
						$template_name = basename( $value );
						if ( file_exists( $import_dir['userpath'] . '/' . $template_name ) ) {
							if ( !function_exists( 'wp_handle_upload' ) ) {
								require_once( ABSPATH . 'wp-admin/includes/file.php' );
							}
							$a = media_sideload_image( $import_dir['userurl'] . '/' . $template_name, 0, '', 'src' );
							if ( is_string( $a ) ) {
								$value = $a;
							}
						}
						break;
					case '_lp_cert_preview':
						// copy cert preview from cache to upload path and update new url
						$template_name = basename( $value );
						if ( file_exists( $import_dir['userpath'] . '/' . $template_name ) ) {
							if ( copy( $import_dir['userpath'] . '/' . $template_name, $upload_dir['userpath'] . '/' . $template_name ) ) {
								$value = $upload_dir['userurl'] . '/' . $template_name;
							}
						}
				}

				update_post_meta( $cert_id, $key, $value );
			}
		}
		$this->rmdir( $import_dir['userpath'] );
		return
			array(
				'redirect' => admin_url( 'edit.php?post_type=lp_cert' ),
				'success'  => 1,
				'message'  => __( 'Import certificate successfully', 'learnpress-certificates' )
			);
	}

	public function rmdir( $dir ) {
		if ( !file_exists( $dir ) ) {
			return true;
		}
		if ( !is_dir( $dir ) ) {
			return unlink( $dir );
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( $item == '.' || $item == '..' ) {
				continue;
			}
			if ( !$this->rmdir( $dir . DIRECTORY_SEPARATOR . $item ) ) {
				return false;
			}
		}
		return rmdir( $dir );
	}

	public function url_to_path( $url ) {
		if ( ( $pos = strpos( $url, '/wp-content/' ) ) === false ) {
			return $url;
		}
		$path = ABSPATH . substr( $url, $pos + 1 );
		return $path;
	}

	protected function _create_cert_object( $id ) {
		$cert       = get_post( $id );
		$cert->meta = array();
		foreach ( array( '_lp_cert_preview', '_lp_cert_template', '_lp_cert_layers' ) as $meta_key ) {
			$cert->meta[$meta_key] = get_post_meta( $id, $meta_key, true );

		}


		return $cert;
	}

	public function row_actions( $actions, $post ) {
		//check for your post type
		if ( $post->post_type == "lp_cert" ) {
			$actions['export_cert'] = sprintf( '<a href="%s">%s</a>', admin_url( 'edit.php?post_type=lp_cert&export=' . $post->ID ), __( 'Export', 'learnpress-certificates' ) );
		}
		return $actions;
	}

	function download() {
		if ( !empty( $_REQUEST['download_cert'] ) ) {
			$name = get_transient( 'download-user-cert-' . get_current_user_id() );
			$file = $_REQUEST['download_cert'];
			$type = !empty( $_REQUEST['type'] ) ? $_REQUEST['type'] : '';

			if ( $name != $file || !$type ) {
				return;
			}

			$upload_dir = learn_press_certificate_upload_dir();
			$download   = $upload_dir['userpath'] . '/' . $file . '.' . $type;

			if ( file_exists( $download ) ) {
				/*learn_press_send_json(
					array(
						'url'  => $upload_dir['userurl'] . '/' . $file . '.' . $type,
						'name' => $file . '.' . $type
					)
				);*/
				switch ( strtolower( $type ) ) {
					case 'png':
						header( 'Content-Type: image/png' );
						break;
					case 'jpg':
						header( 'Content-Type: image/jpeg' );
						break;
				}
				header( 'Content-Disposition: attachment; filename=' . $file . '.' . $type );
				header( 'Content-Length: ' . filesize( $download ) );
				readfile( $download );
				die();
			}
		}
	}

	function generate_download() {
		if ( !empty( $_POST['download_cert'] ) ) {
			$upload_dir = learn_press_certificate_upload_dir();
			$data       = $_POST['download_cert'];
			if ( !empty( $data['combine'] ) ) {
				$content = '';
				for ( $i = 1; $i <= $data['m']; $i ++ ) {
					$content .= file_get_contents( $upload_dir['userpath'] . '/' . $data['t'] . '-' . $i . '.data' );
					@unlink( $upload_dir['userpath'] . '/' . $data['t'] . '-' . $i . '.data' );
				}
				if ( $content ) {
					if ( preg_match_all( '!data:(image\/(.*?));base64,!', $content, $matches ) ) {

						$content    = substr( $content, strlen( $matches[0][0] ) );
						$upload_dir = learn_press_certificate_upload_dir();

						$cert_name = $data['name'] . '.' . $this->get_file_ext( $matches[2][0] );
						file_put_contents( $upload_dir['userpath'] . '/' . $cert_name, base64_decode( $content ) );
						set_transient( 'download-user-cert-' . get_current_user_id(), $data['name'], 60 * 5 );
						$_REQUEST['type']          = $this->get_file_ext( $matches[2][0] );
						$_REQUEST['download_cert'] = $data['name'];
						//$this->download();
						//
						exit();
					} else {
						echo 'content not match';
					}
				} else {
					echo 'content is null';
				}
			} else {
				file_put_contents( $upload_dir['userpath'] . '/' . $data['t'] . '-' . $data['i'] . '.data', $data['data'] );
			}
		}
		die();
	}

	function popup_cert() {
		$cert_data = learn_press_get_user_certificates( get_current_user_id(), get_the_ID() );
		if ( !$cert_data || get_post_type( $cert_data->cert_id ) != 'lp_cert' ) {
			return;
		}
		$user_id   = get_current_user_id();
		$cert_data = (array) $cert_data;
		if ( get_transient( '_user_cert_' . $user_id ) ) {
			delete_transient( '_user_cert_' . $user_id );
			$cert_data['popup'] = true;
		}
		learn_press_certificates_template( 'popup-cert.php', $cert_data );
	}

	function on_finish_course( $course_id, $user_id, $result ) {
		$user = learn_press_get_user( $user_id );
		if ( !( $user && $user->has_passed_course( $course_id ) ) ) {
			return;
		}

		if ( !( $cert_id = learn_press_course_has_cert( $course_id ) ) ) {
			return;
		}

		set_transient(
			'_user_cert_' . $user_id,
			array(
				'cert_id'   => $cert_id,
				'course_id' => $course_id,
				'user_id'   => $user_id
			),
			HOUR_IN_SECONDS
		);
	}

	function cert_preview( $template ) {
		if ( learn_press_is_profile() && !empty( $_REQUEST['view-cert'] ) ) {
			$template = learn_press_certificates_locate_template( '/templates/cert-preview.php' );
		}
		return $template;
	}

	function get_tab_slug() {
		return learn_press_get_certificate_tab_slug();
	}

	function certificates_tab( $tabs, $user ) {
		$tabs[$this->get_tab_slug()] = array(
			'title'    => __( 'Certificates', 'learnpress-certificates' ),
			'callback' => array( $this, 'certificates_tab_content' )
		);
		return $tabs;
	}

	function certificates_tab_content( $tab, $tabs, $user ) {
		learn_press_certificates_template(
			'course-certificates.php',
			array(
			    'certificates' => learn_press_get_user_certificates( $user->id )
			)
		);
	}

	function field_options( $options, $type ) {
		switch ( $type ) {
			case 'student-name':
				$options   = array_reverse( $options );
				$options[] = array(
					'name'    => 'display',
					'type'    => 'select',
					'title'   => __( 'Display', 'learnpress-certificates' ),
					'std'     => '',
					'options' => array(
						'{login_name}'   => __( 'Login name' ),
						'{display_name}' => __( 'Display name' ),
					)
				);
				$options   = array_reverse( $options );
				break;
			case 'custom':
				$options   = array_reverse( $options );
				$options[] = array(
					'name'  => 'text',
					'type'  => 'text',
					'title' => __( 'Text', 'learnpress-certificates' ),
					'std'   => ''
				);
				$options   = array_reverse( $options );
				break;
			case 'course-start-date':
			case 'course-end-date':
			case 'current-date':
			case 'current-time':
			case 'student-birth-date':
				$options   = array_reverse( $options );
				$options[] = array(
					'name'  => 'format',
					'type'  => 'text',
					'title' => __( 'Format', 'learnpress-certificates' ),
					'std'   => preg_match( '/date$/', $type ) ? get_option( 'date_format' ) : get_option( 'time_format' )
				);
				$options   = array_reverse( $options );
		}
		return $options;
	}

	function load_field() {
		$field_options = array_map( 'stripslashes', learn_press_get_request( 'field' ) );
		if ( !$field_options ) {
			throw new Exception( __( 'Error!', 'learnpress-certificates' ) );
		}
		if ( empty( $field_options['fieldType'] ) ) {
			throw new Exception( __( 'Invalid field type!', 'learnpress-certificates' ) );
		}

		require_once LP_ADDON_CERTIFICATES_PATH . '/incs/html/field-options.php';

		die();
	}

	/**
	 * Include common files
	 */
	function includes() {
		require_once LP_ADDON_CERTIFICATES_PATH . '/incs/class-lp-certificate-field.php';
		require_once LP_ADDON_CERTIFICATES_PATH . '/incs/lp-certificate-functions.php';

		return;
		require_once( LP_ADDON_CERTIFICATES_PATH . '/incs/class-lpr-certificate-helper.php' );
		require_once( LP_ADDON_CERTIFICATES_PATH . '/incs/class-lpr-certificate-field.php' );
	}

	/**
	 * Register Certificate post type
	 */
	function register_post_type() {
		define( 'LP_ADDON_CERTIFICATES_THEME_PATH', learn_press_template_path() . '/addons/certificates/' );

		register_post_type( 'lp_cert',
			array(
				'labels'             => array(
					'name'          => __( 'Certificate', 'learnpress-certificates' ),
					'menu_name'     => __( 'Certificates', 'learnpress-certificates' ),
					'singular_name' => __( 'Certificate', 'learnpress-certificates' ),
					'add_new_item'  => __( 'Add New Certificate', 'learnpress-certificates' ),
					'edit_item'     => __( 'Edit Certificate', 'learnpress-certificates' ),
					'all_items'     => __( 'Certificates', 'learnpress-certificates' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'has_archive'        => false,
				'capability_type'    => 'lp_course',
				'map_meta_cap'       => true,
				'show_in_menu'       => 'learn_press',
				'show_in_admin_bar'  => true,
				'show_in_nav_menus'  => true,
				'supports'           => array(
					'title',
					'author'
				),
				'rewrite'            => array( 'slug' => 'certificate' ),
				'map_meta_cap'       => true,
			)
		);
		$this->init();
	}

	function remove_meta_boxes() {
		remove_meta_box( 'authordiv', 'lp_cert', 'normal' );
	}

	function update_cert( $post_id ) {
		if ( get_post_type( $post_id ) == 'lp_course' ) {
			$cert = learn_press_get_request( 'learn-press-cert' );
			update_post_meta( $post_id, "_lp_cert", $cert );
			return;
		}
		if ( get_post_type( $post_id ) != 'lp_cert' ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$cert = learn_press_get_request( 'learn-press-cert' );
		if ( !$cert ) {
			return;
		}
		foreach ( $cert as $k => $v ) {
			if ( is_string( $v ) ) {
				$v = stripcslashes( $v );
				$v = ( $j = json_decode( $v ) ) ? $j : $v;
			}
			if ( $k == 'preview' ) {
				$url = $this->output_preview( $post_id, $v );
				update_post_meta( $post_id, "_lp_cert_{$k}", $url );
				continue;
			} elseif ( $k == 'layers' && $v ) {
				LP_Debug::instance()->add( $v );

				foreach ( $v as $_k => $layer ) {
					$layer = (array) $layer;
					foreach ( $layer as $f => $fv ) {
						if ( is_string( $fv ) ) {
							$layer[$f] = addslashes( $fv );
						}
					}
					$v[$_k] = (object) $layer;
				}
				LP_Debug::instance()->add( $v );

			}

			update_post_meta( $post_id, "_lp_cert_{$k}", $v );
		}
	}

	function output_preview( $cert, $data ) {
		if ( preg_match_all( '!data:image\/(.*?);base64,!', $data, $matches ) ) {
			$data       = substr( $data, strlen( $matches[0][0] ) );
			$upload_dir = learn_press_certificate_upload_dir( '' );

			$cert_name = get_post_field( 'post_name', $cert ) . '-preview.' . $this->get_file_ext( $matches[1][0] );
			file_put_contents( $upload_dir['path'] . '/' . $cert_name, base64_decode( $data ) );
			return $upload_dir['url'] . '/' . $cert_name;
		}
	}

	function get_file_ext( $type ) {
		$return = '';
		switch ( strtolower( $type ) ) {
			case 'png':
				$return = 'png';
				break;
			case 'jpeg':
				$return = 'jpg';
				break;
		}
		return $return;
	}

	public function course_tab() {
		add_meta_box(
			'learn-press-certs-meta-box',
			__( 'Certificate', 'learnpress-certificates' ),
			array( $this, 'course_certs' ),
			'lp_course',
			'normal',
			'low'
		);
	}

	/**
	 * Add meta box to certificate screen
	 */
	function add_meta_boxes() {
		add_meta_box(
			'learn-press-cert-meta-box',
			__( 'Certificate Design', 'learnpress-certificates' ),
			array( $this, 'cert_form' ),
			'lp_cert',
			'normal',
			'high'
		);


	}

	/**
	 * Meta box design form
	 */
	function cert_form( $post ) {
		$has_template = get_post_meta( $post->ID, '_lp_cert_template', true );
		require_once LP_ADDON_CERTIFICATES_PATH . '/incs/html/cert-design.php';
	}

	/**
	 * Meta box design form
	 *
	 * @var int
	 */
	function course_certs( $post ) {
		require_once LP_ADDON_CERTIFICATES_PATH . '/incs/html/course-certs.php';
	}

	function admin_enqueue_assets() {
		global $post_type;
		if ( !in_array( $post_type, array( 'lp_course', 'lp_cert' ) ) ) {
			return;
		}

		wp_enqueue_script( 'fabric', plugins_url( '/assets/js/fabric.js', LP_ADDON_CERTIFICATES_FILE ), array( 'jquery', 'backbone', 'underscore', 'jquery-ui-slider', 'wp-color-picker', 'jquery-ui-draggable', 'jquery-ui-droppable' ) );
		wp_enqueue_script( 'learn-press-admin-cert', plugins_url( '/assets/js/admin.js', LP_ADDON_CERTIFICATES_FILE ), array( 'fabric' ) );

		wp_enqueue_style( 'learn-press-admin-cert', plugins_url( '/assets/css/admin.css', LP_ADDON_CERTIFICATES_FILE ), array( 'wp-color-picker' ) );
		//wp_enqueue_script( 'learn-press-admin-cert', '', array( 'jquery', 'backbone', 'underscore' ) );
	}

	function frontend_enqueue_assets() {
		if ( !apply_filters( 'learn_press_certificates_frontend_enqueue_assets', !( !learn_press_is_profile() && !learn_press_is_course() ) ) ) {
			return;
		}
		wp_enqueue_script( 'fabric', plugins_url( '/assets/js/fabric.js', LP_ADDON_CERTIFICATES_FILE ), array( 'jquery', 'backbone', 'underscore' ) );
		wp_enqueue_script( 'learn-press-frontend-cert', plugins_url( '/assets/js/certificates.js', LP_ADDON_CERTIFICATES_FILE ), array( 'fabric' ) );
		wp_enqueue_style( 'learn-press-frontend-cert', plugins_url( '/assets/css/certificates.css', LP_ADDON_CERTIFICATES_FILE ) );
		wp_enqueue_style( 'learn-press-frontend-cert-print', plugins_url( '/assets/css/print.css', LP_ADDON_CERTIFICATES_FILE ), null, null, 'print' );
	}

	function get_json( $post_id, $course_id = 0, $user_id = 0 ) {
		if ( !$course_id ) {
			global $post;
			$course_id = $post ? $post->ID : 0;
		}
		$template = null;
		if ( $course_id ) {
			$layers = ( $layers = get_post_meta( $post_id, '_lp_cert_layers', true ) ) && is_array( $layers ) ? array_filter( $layers ) : null;
			if ( $user_id /*&& $course = learn_press_get_certificate_course( $post_id )*/ ) {
				$layers = $this->apply_content_fields( $user_id, $course_id, $layers );
			}
		}
		$template = get_post_meta( $post_id, '_lp_cert_template', true );
		$template = ( $template = get_post_meta( $post_id, '_lp_cert_template', true ) ) ? $template : null;
		if($template){
			$template = preg_replace( '#^http[^:]*:#i' , '' , $template);
		}
		return array(
			'template'  => $template,
			'course_id' => $course_id,
			'layers'    => $layers
		);
	}

	function get_variables( $user_id, $course_id ) {
		remove_filter( 'the_title', 'wptexturize' );
		$_variables = wp_cache_get( 'learn-press-cert-variables' );
		$lp_info = get_the_author_meta( 'lp_info', $user_id );
		if ( empty( $_variables ) ) {
			$_variables = array();
		}
		if ( empty( $_variables ) || empty( $_variables[$user_id . '-' . $course_id] ) ) {
			global $wpdb;
			$variables = array(
				'{login_name}'        => 'user_login_name',
				'{display_name}'      => 'user_display_name',
				'{course_name}'       => 'course_name',
				'{course_start_date}' => 'course_start_date',
				'{course_end_date}'   => 'course_end_date',
				'{current_date}'      => 'current_date',
				'{current_time}'      => 'current_time',
				'{course_percent}'    => 'course_percent',
				'{student_birth_place}' => 'student_birth_place',
				'{student_birth_date}' => 'student_birth_date',
				'{student_fiscal_code}' => 'student_fiscal_code'
			);
			$user      = learn_press_get_user( $user_id );
			$loaded    = array();
			foreach ( $variables as $var => $name ) {
				if ( array_key_exists( $name, $loaded ) ) {
					$variables[$var] = $loaded[$name];
					continue;
				}
				$value = '';
				switch ( $name ) {
					case 'user_login_name':
						$value = $user->user_login;
						break;
					case 'user_display_name':
						$value = $user->display_name;
						break;
					case 'course_name':
						$value = get_the_title( $course_id );
						break;
					case 'course_start_date':
					case 'course_end_date':
						if ( version_compare( LEARNPRESS_VERSION, '2.0', '<' ) ) {
							$query = $wpdb->prepare( "
							SELECT uc.start_time, uc.end_time
							FROM {$wpdb->prefix}learnpress_user_courses uc
							WHERE uc.user_id = %d
								AND uc.course_id = %d
						", $user_id, $course_id );
						} else {
							$query = $wpdb->prepare( "
							SELECT uc.start_time, uc.end_time
							FROM {$wpdb->prefix}learnpress_user_items uc
							WHERE uc.user_id = %d
								AND uc.item_id = %d
						", $user_id, $course_id );


						}
						if ( $result = $wpdb->get_row( $query ) ) {
							$value = ( $name == 'course_start_date' ) ? ( $result->start_time ) : ( $result->end_time );
						} else {
							$value = 0;
						}
						break;
					case 'current_date':
					case 'current_time':
						$value = current_time( 'mysql' );
						break;
					case 'course_percent':
						$value = LP_Course::get_course( $course_id )->evaluate_course_results( $user_id );
						break;
					case 'student_birth_place':
						$value = $lp_info['birth_place'];
						break;
					case 'student_birth_date':
						$value = $lp_info['birth_date'];
						break;
					case 'student_fiscal_code':
						$value = $lp_info['fiscal_code'];
						break;
				}
				$loaded[$name]   = $value;
				$variables[$var] = ( $value );
			}
			$_variables[$user_id . '-' . $course_id] = $variables;
			wp_cache_set( 'learn-press-cert-variables', $_variables );
		}
		add_filter( 'the_title', 'wptexturize' );

		return apply_filters( 'learn_press_certificate_field_variables', $_variables[$user_id . '-' . $course_id], $user_id, $course_id );
	}

	function apply_content_fields( $user, $course_id, $layers ) {
		if ( is_string( $user ) || is_numeric( $user ) ) {
			$user = learn_press_get_user( $user );
		}
		if ( $layers && $user ) {
			$variables = $this->get_variables( $user->ID, $course_id );
			//$searches  = array_keys( $variables );
			//$replaces  = array_values( $variables );
			$apply = $variables;

			foreach ( $layers as $i => $layer ) {
				$text = $layer->text;
				switch ( $layer->fieldType ) {
					case 'student-name':
						if ( empty( $layer->display ) ) {
							$text = '{login_name}';
						} else {
							$text = $layer->display;
						}
						break;
					case 'course-name':
						$text = '{course_name}';
						break;
					case 'course-start-date':
						$text         = '{course_start_date}';
						$format       = !empty( $layer->format ) ? $layer->format : get_option( 'date_format' );
						$apply[$text] = date( $format, @strtotime( $variables[$text] ) );
						break;
					case 'course-end-date':
						$text         = '{course_end_date}';
						$format       = !empty( $layer->format ) ? $layer->format : get_option( 'date_format' );
						$apply[$text] = date_i18n( $format, @strtotime( $variables[$text] ) );
						break;
					case 'current-date':
						$text         = '{current_date}';
						$format       = !empty( $layer->format ) ? $layer->format : get_option( 'date_format' );
						$apply[$text] = date_i18n( $format, @strtotime( $variables[$text] ) );
						break;
					case 'current-time':
						$text         = '{current_time}';
						$format       = !empty( $layer->format ) ? $layer->format : get_option( 'time_format' );
						$apply[$text] = date_i18n( $format, @strtotime( $variables[$text] ) );
						break;
					case 'student-birth-date':
						$text = '{student_birth_date}';
						break;
					case 'student-fiscal-code':
						$text = '{student_fiscal_code}';
						break;
					case 'student-birth-place':
						$text = '{student_birth_place}';
						break;
					case 'custom':
						$text = $layer->text;
						if ( preg_match_all( '!\{(.+?)(:(.+?))?\}!', $text, $matches ) ) {
							foreach ( $matches[1] as $k => $var_name ) {
								if ( $var_name == 'course_start_date' || $var_name == 'course_end_date' || $var_name == 'current_date' ) {
									$format = !empty( $matches[3][$k] ) ? $matches[3][$k] : get_option( 'date_format' );
									$reg    = '/' . str_replace( array( '{', '}' ), array( '\{', '\}' ), $matches[0][$k] ) . '/';
									$rep    = date( $format, is_string( $variables['{' . $var_name . '}'] ) ? strtotime( $variables['{' . $var_name . '}'] ) : $variables['{' . $var_name . '}'] );
									if ( !$rep ) {
										$rep = '';
									}
									$text = preg_replace( $reg, $rep, $text );
								} elseif ( $var_name == 'current_time' ) {
									$format = !empty( $matches[3][$k] ) ? $matches[3][$k] : get_option( 'time_format' );
									$reg    = '/' . str_replace( array( '{', '}' ), array( '\{', '\}' ), $matches[0][$k] ) . '/';
									$rep    = date( $format, is_string( $variables['{' . $var_name . '}'] ) ? strtotime( $variables['{' . $var_name . '}'] ) : $variables['{' . $var_name . '}'] );
									if ( !$rep ) {
										$rep = '';
									}
									$text = preg_replace( $reg, $rep, $text );
								} elseif ( $var_name == 'course_percent' ) {
									$reg   = '/' . str_replace( array( '{', '}' ), array( '\{', '\}' ), $matches[0][$k] ) . '/';
									$point = 0;
									switch ( $matches[3][0] ) {
										case 'point_10':
											$point = $variables['{course_percent}'] / 10;
											$point = round( $point, 1 );
											break;
										case 'point_100':
										default:
											$point = $variables['{course_percent}'];
											$point = round( $point, 2 );
									}

									$text = preg_replace( $reg, $point, $text );
								}
							}
						}
						break;
					default:
						$text = apply_filters( 'learn_press_certificate_apply_content_field', $text, $layer, $apply, $user, $course_id );
				}
				foreach ( $apply as $search => $replace ) {
					$text = str_replace( $search, $replace, $text );
				}
				$layers[$i]->text = $text;
			}
		}
		return apply_filters( 'learn_press_certificate_apply_fields_content', $layers, $user, $course_id );
	}

	function columns_head( $columns ) {
		if ( get_post_type() == 'lp_cert' ) {
			$columns['courses'] = __( 'Courses', 'learnpress-certificates' );
		}
		$columns['certificate'] = __( 'Preview', 'learnpress-certificates' );
		return $columns;
	}

	function columns_content( $column, $post_id ) {
		global $wpdb;
		switch ( $column ) {
			case 'certificate':
				if ( get_post_type( $post_id ) == 'lp_cert' ) {
					$course_cert = $post_id;
				} else {
					$course_cert = get_post_meta( $post_id, '_lp_cert', true );
				}
				if ( $course_cert ) {
					$preview = get_post_meta( $course_cert, '_lp_cert_preview', true );
					echo '<div class="course-cert-preview">';
					echo sprintf( '<a href="%s"><img src="%s" alt="%s" /></a>', get_edit_post_link( $course_cert ), $preview, get_post_field( 'post_name', $course_cert ) );
					echo '</div>';
				} else {
					_e( '-', 'learnpress-certificates' );
				}
				break;
			case 'courses':
				if ( get_post_type() == 'lp_cert' ) {
					if ( get_post_type( $post_id ) == 'lp_cert' ) {
						$course_cert = $post_id;
					} else {
						$course_cert = get_post_meta( $post_id, '_lp_cert', true );
					}
					$output = '';
					if ( $course_cert ) {
						$query = $wpdb->prepare( "
						SELECT p.ID, p.post_title
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
						INNER JOIN {$wpdb->posts} c ON c.ID = pm.meta_value AND pm.meta_key = %s
					  	WHERE c.ID = %d
					", '_lp_cert', '_lp_cert', $post_id );
						if ( $courses = $wpdb->get_results( $query ) ) {
							$links = array();
							foreach ( $courses as $course ) {
								$links[] = sprintf( '<a href="%s">%s</a>', get_edit_post_link( $course->ID ), $course->post_title );
							}
							$output = join( ' | ', $links );
						}
					}
					if ( $output ) {
						echo $output;
					} else {
						_e( 'Unassigned', 'learnpress-certificates' );
					}
				}
		}
	}

	function init() {
		$endpoint                   = preg_replace( '!_!', '-', $this->get_tab_slug() );
		LP()->query_vars[$endpoint] = $endpoint;
		add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
	}

	function profile_tab_endpoints( $endpoints ) {
		$endpoints[] = $this->get_tab_slug();
		return $endpoints;
	}

	function downloadx() {
		if ( !empty( $_POST['download_cert'] ) ) {
			$data = $_POST['download_cert']['data'];
			$name = $_POST['download_cert']['name'];
			if ( preg_match_all( '!data:(image\/(.*?));base64,!', $data, $matches ) ) {

				$data       = substr( $data, strlen( $matches[0][0] ) );
				$upload_dir = learn_press_certificate_upload_dir();

				$cert_name = $name . '.' . $this->get_file_ext( $matches[2][0] );
				file_put_contents( $upload_dir['userpath'] . '/' . $cert_name, base64_decode( $data ) );
				header( 'Content-Type: ' . $matches[1][0] );
				header( 'Content-Disposition: attachment; filename="' . $cert_name . '"' );
				readfile( $upload_dir['userpath'] . '/' . $cert_name );
				exit();
			}
		}
	}

	public static function admin_notice() {
		?>
		<div class="error">
			<p><?php printf( __( '<strong>Certificates</strong> addon version %s requires <strong>LearnPress</strong> version %s or higher', 'learnpress-certificates' ), LP_ADDON_CERTIFICATES_VER, LP_ADDON_CERTIFICATES_REQUIRE_VER ); ?></p>
		</div>
		<?php
	}

	static function instance() {

		if ( !defined( 'LEARNPRESS_VERSION' ) || ( version_compare( LEARNPRESS_VERSION, LP_ADDON_CERTIFICATES_REQUIRE_VER, '<' ) ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
			return false;
		}

		if ( !self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}

