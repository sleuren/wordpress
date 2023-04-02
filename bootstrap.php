<?php

/**
 * Main class of the plugin used to add functionalities
 *
 * @since 1.0.0
 */
class Sleuren {

	// Refers to a single instance of this class
	private static $instance = null;

	// For the debug log object
	private $debug_log;

	// For the wp-config object
	private $wp_config;

	/**
	 * Creates or returns a single instance of this class
	 *
	 * @return Sleuren a single instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;

	}

	/**
	 * Initialize plugin functionalities
	 */
	private function __construct() {

		global $pagenow;

		// Register admin menu and subsequently the main admin page
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

		// Do not display any admin notices while viewing logs.
		add_action( 'admin_notices', [ $this, 'suppress_admin_notices' ], 0 );
		add_action( 'all_admin_notices', [ $this, 'suppress_generic_notices' ], 0 );

		// Add action links
		add_filter( 'plugin_action_links_'.SLE_SLUG.'/'.SLE_SLUG.'.php', [ $this, 'action_links' ] );

		if ( is_admin() ) {

			if ( $this->is_sleuren() ) {

				// Update footer text
				add_filter( 'admin_footer_text', [ $this, 'footer_text' ] );

				// Enqueue admin scripts and styles only on the plugin's main page
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

			}

		}

		// Enqueue admin scripts and styles on plugin editor page
		if ( 'plugin-editor.php' === $pagenow ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'plugin_editor_scripts' ] );
		}

		// Enqueue admin scripts and styles on theme editor page
		if ( 'theme-editor.php' === $pagenow ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'theme_editor_scripts' ] );
		}

		// Add admin bar icon if error logging is enabled and admin URL is not the plugin's main page. It will show on on the front end too (when logged-in), as we're also logging JavaScript errors.

        $default_value = array(
            'status'    => 'disabled',
            'on'        => date( 'Y-m-d H:i:s' ),
        );

		$logging_info 	= get_option( 'sleuren', $default_value );
		$logging_status = $logging_info['status'];

		if ( ( $logging_status == 'enabled' ) && ! $this->is_sleuren() ) {

			// https://developer.wordpress.org/reference/hooks/admin_bar_menu/
			add_action( 'admin_bar_menu', [ $this, 'admin_bar_icon' ] );

		}

		// Add dashboard widget
		
		add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );

		// Add inline CSS for the admin bar icon (menu item)
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_bar_icon_css' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'admin_bar_icon_css' ] );

		// Enqueue public scripts and styles
		add_action( 'wp_enqueue_scripts', [ $this, 'public_scripts' ] );

		// Register ajax calls
		$this->debug_log = new Sleuren\Classes\Debug_Log;
		$this->wp_config = new Sleuren\Classes\WP_Config_Transformer;
		add_action( 'wp_ajax_toggle_debugging', [ $this->debug_log, 'toggle_debugging' ] );
		add_action( 'wp_ajax_toggle_autorefresh', [ $this->debug_log, 'toggle_autorefresh' ] );
		add_action( 'wp_ajax_get_latest_entries', [ $this->debug_log, 'get_latest_entries' ] );
		add_action( 'wp_ajax_clear_log', [ $this->debug_log, 'clear_log' ] );
		add_action( 'wp_ajax_disable_wp_file_editor', [ $this->debug_log, 'disable_wp_file_editor' ] );
		add_action( 'wp_ajax_log_js_errors', [ $this->debug_log, 'log_js_errors' ] );
		add_action( 'wp_ajax_nopriv_log_js_errors', [ $this->debug_log, 'log_js_errors' ] );

	}

	/**
	 * Check if current screen is this plugin's main page
	 *
	 * @since 1.0.0
	 */
	public function is_sleuren() {

		$request_uri = sanitize_text_field( $_SERVER['REQUEST_URI'] ); // e.g. /wp-admin/index.php?page=page-slug

		if ( strpos( $request_uri, 'tools.php?page=' . SLE_SLUG ) !== false ) {
			return true; // Yes, this is the plugin's main page
		} else {
			return false; // Nope, this is NOT the plugin's page
		}

	}

	/**
	 * Register admin menu
	 *
	 * @since 1.0.0
	 */
	public function register_admin_menu() {

		add_submenu_page(
			'tools.php',
			__( 'Sleuren', 'sleuren' ),
			__( 'Sleuren', 'sleuren' ),
			'manage_options',
			'sleuren',
			[ $this, 'create_main_page' ]
		);

	}

	/**
	 * Register action links
	 *
	 * @since 1.0.0
	 */
	public function action_links( $links ) {

		$settings_link = '<a href="tools.php?page='.SLE_SLUG.'">' . esc_html__( 'View Debug Log', 'sleuren' ) . '</a>';

		array_unshift($links, $settings_link); 

		return $links; 

	}

	/**
	 * Change admin footer text
	 *
	 * @since 1.0.0
	 */
	public function footer_text() {
		?>
			<a href="https://wordpress.org/plugins/sleuren/" target="_blank"><?php esc_html_e( 'Sleuren', 'sleuren' ); ?></a> (<a href="https://github.com/sleuren/wordpress" target="_blank">github</a>).
		<?php
	}

	/**
	 * Add debug icon in the admin bar
	 *
	 * @since 1.6.0
	 */
	public function admin_bar_icon( WP_Admin_Bar $wp_admin_bar ) {

		// https://developer.wordpress.org/reference/classes/wp_admin_bar/add_menu/
		// https://developer.wordpress.org/reference/classes/wp_admin_bar/add_node/ for more examples
		$wp_admin_bar->add_menu( array(
			'id'		=> SLE_SLUG,
			'parent'	=> 'top-secondary',
			'group'		=> null,
			'title'		=> '<span class="dashicons dashicons-warning"></span>',
			'href'		=> admin_url( 'tools.php?page=' . SLE_SLUG ),
			'meta'		=> array(
				'class'		=> 'sleuren-admin-bar-icon',
				'title'		=> esc_attr__( 'Error logging is enabled. Click to access the Sleuren.', 'sleuren' )
			),
		) );

	}

	/**
	 * Create the main admin page of the plugin
	 *
	 * @since 1.0.0
	 */
	public function create_main_page() {

		$log_file_path 		= get_option( 'sleuren_file_path' );
		$log_file_shortpath = str_replace( sanitize_text_field( $_SERVER['DOCUMENT_ROOT'] ), "", $log_file_path );
		$file_size 			= size_format( (int) filesize( $log_file_path ) );

		?>

		<div class="wrap sleuren-main-page">
			<div id="sleuren-header" class="sleuren-header">
				<div class="sleuren-header-left">
					<h1 class="sleuren-heading"><?php esc_html_e( 'Sleuren Logs', 'sleuren' ); ?> <small><?php esc_html_e( 'by', 'sleuren' ); ?> <a href="https://sleuren.com" target="_blank">Sleuren</a></small></h1>
				</div>
				<div class="sleuren-header-right">
					<a href="https://wordpress.org/plugins/sleuren/" target="_blank" class="sleuren-header-action"><span>&#8505;</span> <?php esc_html_e( 'Info', 'sleuren' ); ?></a>
					<a href="https://wordpress.org/plugins/sleuren/#reviews" target="_blank" class="sleuren-header-action"><span>★</span> <?php esc_html_e( 'Review', 'sleuren' ); ?></a>
					<a href="https://wordpress.org/support/plugin/sleuren/" target="_blank" class="sleuren-header-action">✚ <?php esc_html_e( 'Feedback', 'sleuren' ); ?></a>
				</div>
			</div>
			<div class="sleuren-body">
				<div class="sleuren-log-management">
					<div class="sleuren-logging-status">
						<div class="sleuren-log-status-toggle">
							<input type="checkbox" id="sleuren-checkbox" class="inset-3 sleuren-checkbox"><label for="sleuren-checkbox" class="green sleuren-switcher"></label>
						</div>
						<?php echo $this->debug_log->get_status(); ?>
					</div>
					<div class="sleuren-autorefresh-status">
						<div class="sleuren-log-autorefresh-toggle">
							<input type="checkbox" id="sleuren-checkbox" class="inset-3 sleuren-checkbox"><label for="sleuren-checkbox" class="green sleuren-switcher"></label>
						</div>
						<?php echo $this->debug_log->get_autorefresh_status(); ?>
					</div>
				</div>
				<?php
					$this->debug_log->get_entries_datatable();
				?>
			</div>
			<div class="sleuren-footer">
				<div id="sleuren-log-file-location-section" class="sleuren-footer-section">
					<div class="sleuren-log-file-location"><strong><?php esc_html_e( 'Log file', 'sleuren' ); ?></strong>: <?php echo esc_html( $log_file_shortpath ); ?> (<span id="sleuren-log-file-size"><?php echo esc_html( $file_size ); ?></span>)</div>
					<button id="sleuren-log-clear" class="button button-small button-secondary sleuren-footer-button sleuren-log-clear"><?php esc_html_e( 'Clear Log', 'sleuren' ); ?></button>
				</div>
				<div id="sleuren-disable-wp-file-editor-section" class="sleuren-footer-section sleuren-top-border" style="display:none;">
					<div><?php esc_html_e( 'Once error logging is enabled, the core\'s plugin/theme editor stays enabled even if error logging has been disabled later on. This allows for viewing the files where errors occurred even when logging has been disabled. You can optionally disable the editor here once you\'re done debugging.', 'sleuren' ); ?></div>
					<button id="sleuren-disable-wp-file-editor" class="button button-small button-secondary sleuren-footer-button sleuren-disable-wp-file-editor"><?php esc_html_e( 'Disable Editor', 'sleuren' ); ?></button>
				</div>
				<?php
					echo $this->wp_config->wpconfig_file( 'status' );
				?>
			</div>
		</div>

		<?php

	}

	/**
	 * To stop other plugins' admin notices overlaying in the Sleuren UI, remove them.
	 *
	 * @hooked admin_notices
	 *
	 * @since 1.8.7
	 */
	public function suppress_admin_notices() {

		global $plugin_page;

		if ( SLE_SLUG === $plugin_page ) {
			remove_all_actions( 'admin_notices' );
		}

	}

	/**
	 * Suppress all generic notices on the plugin settings page
	 *
	 * @since 1.8.8
	 */
	public function suppress_generic_notices() {

		global $plugin_page;

		// Suppress all notices

		if ( SLE_SLUG === $plugin_page ) {

			remove_all_actions( 'all_admin_notices' );

		}

	}

	/**
	 * Add dashboard widget with latest errors
	 *
	 * @since 1.8.0
	 */
	public function add_dashboard_widget() {

		wp_add_dashboard_widget(
			'sleuren_widget', // widget ID
			__( 'Debug Log | Latest Errors', 'sleuren' ), // widget title
			array( $this, 'get_dashboard_widget_entries' ) // callback #1 to display entries
			// array( $this, 'dashboard_widget_settings' ) // callback #2 for configuration
		);

	}

	/**
	 * Load latest errors for dashboard widget
	 *
	 * @since 1.8.0
	 */
	public function get_dashboard_widget_entries() {

		$this->debug_log->get_dashboard_widget_entries();

	}

	/**
	 * Enqueue admin scripts
	 *
	 * @since 1.0.0
	 */
	public function admin_scripts() {

		wp_enqueue_style( 'sleuren-admin', SLE_URL . 'assets/css/admin.css', array(), SLE_VERSION );
		wp_enqueue_style( 'sleuren-datatables', SLE_URL . 'assets/css/datatables.min.css', array(), SLE_VERSION );
		wp_enqueue_style( 'sleuren-toast', SLE_URL . 'assets/css/jquery.toast.min.css', array(), SLE_VERSION );
		wp_enqueue_script( 'sleuren-app', SLE_URL . 'assets/js/admin.js', array(), SLE_VERSION, false );
		wp_enqueue_script( 'sleuren-jsticky', SLE_URL . 'assets/js/jquery.jsticky.mod.min.js', array( 'jquery' ), SLE_VERSION, false );
		wp_enqueue_script( 'sleuren-datatables', SLE_URL . 'assets/js/datatables.min.js', array( 'jquery' ), SLE_VERSION, false );
		wp_enqueue_script( 'sleuren-toast', SLE_URL . 'assets/js/jquery.toast.min.js', array( 'jquery' ), SLE_VERSION, false );

		// Pass on data from PHP to JS

        $default_value = array(
            'status'    => 'disabled',
            'on'        => date( 'Y-m-d H:i:s' ),
        );

		$log_info = get_option( 'sleuren', $default_value );
		$log_status = $log_info['status']; // WP_DEBUG log status: enabled / disabled

		if ( false !== get_option( 'sleuren_autorefresh' ) ) {
			$autorefresh_status = get_option( 'sleuren_autorefresh' );
		} else {
			$autorefresh_status = 'disabled';
	        update_option( 'sleuren_autorefresh', $autorefresh_status, false );
		}

		wp_localize_script( 
			'sleuren-app', 
			'sleurenVars', 
			array(
				'logStatus'			=> $log_status,
				'autorefreshStatus'	=> $autorefresh_status,
				'jsErrorLogging'	=> array(
					'status'	=> '',
					'url'		=> admin_url( 'admin-ajax.php' ),
					'nonce'		=> wp_create_nonce( SLE_SLUG ),
					'action'	=> 'log_js_errors',
				),
				'toastMessage'		=> array(
					'toggleDebugSuccess'	=> __( 'Error logging has been enabled and the latest entries have been loaded.', 'sleuren' ),
					'copySuccess'			=> __( 'Entries have been copied from an existing debug.log file.', 'sleuren' ),
					'logFileCleared'		=> __( 'Log file has been cleared.', 'sleuren' ),
					'editoDisabled'			=> __( 'WordPress plugin/theme editor has been disabled. ', 'sleuren' ),
					'paginationActive'		=> __( 'Pagination is active. Auto-refresh has been disabled.', 'sleuren' ),
				),
				'dataTable'			=> array(
					'emptyTable'	=> __( 'No data available in table', 'sleuren' ),
					'info'			=> __( 'Showing _START_ to _END_ of _TOTAL_ entries', 'sleuren' ),
					'infoEmpty'		=> __( 'Showing 0 to 0 of 0 entries', 'sleuren' ),
					'infoFiltered'	=> __( '(filtered from _MAX_ total entries)', 'sleuren' ),
					'lengthMenu'	=> __( 'Show _MENU_ entries', 'sleuren' ),
					'search'		=> __( 'Search:', 'sleuren' ),
					'zeroRecords'	=> __( 'No matching records found', 'sleuren' ),
					'paginate'		=> array(
					    'first'		=> __( 'First', 'sleuren' ),
					    'last'		=> __( 'Last', 'sleuren' ),
					    'next'		=> __( 'Next', 'sleuren' ),
					    'previous'	=> __( 'Previous', 'sleuren' ),
					),
				),
			) 
		);

	}

	/**
	 * Scripts for WP plugin editor page
	 *
	 * @since 2.0.0
	 */
	public function plugin_editor_scripts() {

		wp_enqueue_style( 'sleuren-plugin-theme-editor', SLE_URL . 'assets/css/plugin-theme-editor.css', array(), SLE_VERSION );
		wp_enqueue_script( 'sleuren-plugin-editor', SLE_URL . 'assets/js/plugin-editor.js', array( 'jquery', 'wp-theme-plugin-editor' ), SLE_VERSION, false );

	}

	/**
	 * Scripts for WP theme editor page
	 *
	 * @since 2.0.0
	 */
	public function theme_editor_scripts() {

		wp_enqueue_style( 'sleuren-plugin-theme-editor', SLE_URL . 'assets/css/plugin-theme-editor.css', array(), SLE_VERSION );
		wp_enqueue_script( 'sleuren-theme-editor', SLE_URL . 'assets/js/theme-editor.js', array( 'jquery', 'wp-theme-plugin-editor' ), SLE_VERSION, false );

	}

	/**
	 * Admin bar icon's inline css
	 *
	 * @since 1.6.0
	 */
	public function admin_bar_icon_css() {

		// https://developer.wordpress.org/reference/functions/wp_add_inline_style/
		wp_add_inline_style( 'admin-bar', '

			#wpadminbar .sleuren-admin-bar-icon .dashicons { 
				font-family: dashicons; 
				font-size: 20px; 
				width: 20px; 
				height: 20px; 
				line-height: 32px; 
			}

			#wpadminbar .quicklinks ul li.sleuren-admin-bar-icon a { 
				background: green;
			}

			#wpadminbar:not(.mobile) .ab-top-menu>li:hover>.ab-item { 
				transition: .25s;
			}

			#wpadminbar:not(.mobile) .ab-top-menu>li.sleuren-admin-bar-icon:hover>.ab-item,
			#wpadminbar:not(.mobile) .ab-top-menu>li.sleuren-admin-bar-icon>.ab-item:focus { 
				background: #006600; 
				color: #fff; 
			}

		' );

	}

	/**
	 * Enqueue public scripts
	 *
	 * @since 1.4.0
	 */
	public function public_scripts() {

		wp_enqueue_script( 'sleuren-public', SLE_URL . 'assets/js/public.js', array( 'jquery' ), SLE_VERSION, false );

        $default_value = array(
            'status'    => 'disabled',
            'on'        => date( 'Y-m-d H:i:s' ),
        );

		$log_info = get_option( 'sleuren', $default_value );
		$log_status = $log_info['status']; // WP_DEBUG log status: enabled / disabled

		wp_localize_script( 
			'sleuren-public', 
			'sleurenVars', 
			array(
				'logStatus'			=> $log_status,
				'jsErrorLogging'	=> array(
					'status'	=> '',
					'url'		=> admin_url( 'admin-ajax.php' ),
					'nonce'		=> wp_create_nonce( SLE_SLUG ),
					'action'	=> 'log_js_errors',
				),
			) 
		);
	}

}

Sleuren::get_instance();