<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFORGE_Settings {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'admin_post_sforge_test_connection', [ $this, 'action_test_connection' ] );
		add_action( 'admin_post_sforge_full_rebuild',    [ $this, 'action_full_rebuild' ] );
		add_action( 'admin_post_sforge_clear_log',       [ $this, 'action_clear_log' ] );
		add_action( 'admin_enqueue_scripts',           [ $this, 'enqueue' ] );
		add_action( 'wp_ajax_sforge_get_log',            [ $this, 'ajax_get_log' ] );
	}

	public static function get( $key = null, $default = null ) {
		$opts = get_option( SFORGE_OPT, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		if ( $key === null ) {
			return $opts;
		}
		return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}

	public function menu() {
		add_menu_page(
			__( 'StaticForge for Cloudflare Pages', 'staticforge-for-cloudflare-pages' ),
			__( 'StaticForge for Cloudflare Pages', 'staticforge-for-cloudflare-pages' ),
			'manage_options',
			'sforge',
			[ $this, 'render_page' ],
			'dashicons-cloud-upload',
			61
		);
		add_submenu_page(
			'sforge',
			__( 'Settings', 'staticforge-for-cloudflare-pages' ),
			__( 'Settings', 'staticforge-for-cloudflare-pages' ),
			'manage_options',
			'sforge',
			[ $this, 'render_page' ]
		);
		add_submenu_page(
			'sforge',
			__( 'Setup Guide', 'staticforge-for-cloudflare-pages' ),
			__( 'Setup Guide', 'staticforge-for-cloudflare-pages' ),
			'manage_options',
			'sforge-help',
			[ $this, 'render_help_page' ]
		);
	}

	public function render_help_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SFORGE_DIR . 'admin/help-page.php';
	}

	public function enqueue( $hook ) {
		$css_v = $this->asset_ver( 'admin/admin.css' );
		$js_v  = $this->asset_ver( 'admin/admin.js' );
		$help_v = $this->asset_ver( 'admin/help.css' );

		if ( $hook === 'toplevel_page_sforge' ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'sforge-admin', SFORGE_URL . 'admin/admin.css', [ 'dashicons' ], $css_v );
			wp_enqueue_script( 'sforge-admin', SFORGE_URL . 'admin/admin.js', [], $js_v, true );
			wp_localize_script( 'sforge-admin', 'sforgeAdmin', [
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sforge_log' ),
			] );
			$this->register_help_tab();
			return;
		}
		if ( $hook === 'static-to-pages_page_sforge-help' || strpos( (string) $hook, 'sforge-help' ) !== false ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'sforge-help', SFORGE_URL . 'admin/help.css', [ 'dashicons' ], $help_v );
		}
	}

	protected function asset_ver( $rel_path ) {
		$path = SFORGE_DIR . $rel_path;
		$mt   = file_exists( $path ) ? (int) filemtime( $path ) : 0;
		return SFORGE_VERSION . '.' . $mt;
	}

	protected function register_help_tab() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$help_url = admin_url( 'admin.php?page=sforge-help' );
		$screen->add_help_tab( [
			'id'      => 'sforge_quickstart',
			'title'   => __( 'Quick Start', 'staticforge-for-cloudflare-pages' ),
			'content' =>
				'<p><strong>Five steps to first deploy:</strong></p>' .
				'<ol>' .
				'<li>Create a Cloudflare Pages project in <em>Direct Upload</em> mode (note the slug, e.g. <code>mysite</code>).</li>' .
				'<li>Create an API Token with <code>Account &middot; Cloudflare Pages &middot; Edit</code>.</li>' .
				'<li>Copy your Account ID from the CF dashboard sidebar.</li>' .
				'<li>Fill the fields on this page &rarr; Save &rarr; <em>Test Connection</em>.</li>' .
				'<li>Click <em>Rebuild + Deploy Now</em> &rarr; watch the activity log.</li>' .
				'</ol>' .
				'<p>Full walkthrough: <a href="' . esc_url( $help_url ) . '">Setup Guide</a></p>',
		] );
		$screen->add_help_tab( [
			'id'      => 'sforge_fields',
			'title'   => __( 'Field Reference', 'staticforge-for-cloudflare-pages' ),
			'content' =>
				'<dl>' .
				'<dt><strong>Account ID</strong></dt><dd>32-char hex from CF Dashboard right sidebar.</dd>' .
				'<dt><strong>API Token</strong></dt><dd>Permission: <code>Account &middot; Cloudflare Pages &middot; Edit</code>. Shown once at creation &mdash; copy it.</dd>' .
				'<dt><strong>Pages Project</strong></dt><dd>The slug only, NOT the <code>.pages.dev</code> URL.</dd>' .
				'<dt><strong>Branch</strong></dt><dd><code>main</code> = production. Anything else = preview deployment.</dd>' .
				'<dt><strong>Public Site URL</strong></dt><dd>Where the static site lives publicly. Used to rewrite WP URLs in HTML output.</dd>' .
				'<dt><strong>Inline CSS</strong></dt><dd>Embeds linked stylesheets so each exported page is self-contained.</dd>' .
				'<dt><strong>Debounce</strong></dt><dd>Rapid edits within this many seconds collapse into a single deploy.</dd>' .
				'</dl>',
		] );
		$screen->add_help_tab( [
			'id'      => 'sforge_trouble',
			'title'   => __( 'Troubleshooting', 'staticforge-for-cloudflare-pages' ),
			'content' =>
				'<ul>' .
				'<li><code>Project not found</code> &rarr; Pages Project must be the slug, not the URL.</li>' .
				'<li><code>Request body is incorrect</code> &rarr; old plugin version. Update.</li>' .
				'<li>Stuck on <em>Manifest</em> &rarr; PHP memory/timeout limit, or upload batch too large.</li>' .
				'<li>Sub-sitemaps missing &rarr; ensure plugin v1.0.0+ (handles CDATA-wrapped <code>&lt;loc&gt;</code>).</li>' .
				'<li>Live site shows <code>noindex</code> &rarr; turn off WordPress &rarr; Settings &rarr; Reading "Discourage search engines".</li>' .
				'</ul>' .
				'<p>Detailed: <a href="' . esc_url( $help_url ) . '">Setup Guide</a></p>',
		] );
		$screen->set_help_sidebar(
			'<p><strong>StaticForge for Cloudflare Pages</strong></p>' .
			'<p>By <a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener">Gunjan Jaswal</a></p>' .
			'<p><a href="mailto:hello@gunjanjaswal.me">hello@gunjanjaswal.me</a></p>'
		);
	}

	public function ajax_get_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'msg' => 'forbidden' ], 403 );
		}
		check_ajax_referer( 'sforge_log', 'nonce' );
		$next = wp_next_scheduled( 'sforge_full_rebuild' );
		$running = false;
		if ( $next && $next <= time() + 5 ) {
			$running = true;
		}
		$log = SFORGE_Logger::get();
		// Most recent log entry: detect activity in last 60s as "running".
		if ( ! empty( $log[0]['time'] ) ) {
			$ts = strtotime( $log[0]['time'] );
			$top_msg = strtolower( (string) ( $log[0]['msg'] ?? '' ) );
			$is_terminal = ( strpos( $top_msg, 'deploy ok' ) !== false ) ||
			               ( strpos( $top_msg, 'deploy fail' ) !== false ) ||
			               ( strpos( $top_msg, 'nothing rendered' ) !== false ) ||
			               ( strpos( $top_msg, 'no urls' ) !== false );
			if ( $ts && ( time() - $ts ) < 60 && ! $is_terminal ) {
				$running = true;
			}
		}
		wp_send_json_success( [
			'log'     => $log,
			'running' => $running,
			'next'    => $next ? (int) $next - time() : null,
		] );
	}

	public function register() {
		register_setting( 'sforge_group', SFORGE_OPT, [ $this, 'sanitize' ] );
	}

	public function sanitize( $in ) {
		$out = [];
		$out['account_id']         = sanitize_text_field( $in['account_id'] ?? '' );
		$out['api_token']          = sanitize_text_field( $in['api_token'] ?? '' );
		$out['project_name']       = sanitize_text_field( $in['project_name'] ?? '' );
		$out['branch']             = sanitize_text_field( $in['branch'] ?? 'main' );
		$out['post_types']         = array_map( 'sanitize_key', (array) ( $in['post_types'] ?? [] ) );
		$out['auto_deploy']        = ! empty( $in['auto_deploy'] ) ? 1 : 0;
		$out['inline_css']         = ! empty( $in['inline_css'] ) ? 1 : 0;
		$out['include_taxonomies'] = ! empty( $in['include_taxonomies'] ) ? 1 : 0;
		$out['include_authors']    = ! empty( $in['include_authors'] ) ? 1 : 0;
		$out['include_homepage']   = ! empty( $in['include_homepage'] ) ? 1 : 0;
		$out['cf_pages_url']       = esc_url_raw( $in['cf_pages_url'] ?? '' );
		$out['export_dir']         = sanitize_file_name( $in['export_dir'] ?? 'sforge-export' );
		$out['debounce']           = max( 10, intval( $in['debounce'] ?? 120 ) );
		$out['robots_txt']         = isset( $in['robots_txt'] ) ? $this->sanitize_robots( (string) $in['robots_txt'] ) : '';
		$out['seo_inject']         = ! empty( $in['seo_inject'] ) ? 1 : 0;
		$out['seo_inject_force']   = ! empty( $in['seo_inject_force'] ) ? 1 : 0;
		$out['featured_image_priority'] = ! empty( $in['featured_image_priority'] ) ? 1 : 0;
		$out['dashboard_block']    = ! empty( $in['dashboard_block'] ) ? 1 : 0;
		$out['sitemap_post_types'] = array_map( 'sanitize_key', (array) ( $in['sitemap_post_types'] ?? [] ) );
		$out['sitemap_homepage']   = ! empty( $in['sitemap_homepage'] ) ? 1 : 0;
		$out['sitemap_taxonomies'] = ! empty( $in['sitemap_taxonomies'] ) ? 1 : 0;
		$out['sitemap_authors']    = ! empty( $in['sitemap_authors'] ) ? 1 : 0;
		$out['sitemap_split']      = ! empty( $in['sitemap_split'] ) ? 1 : 0;
		$out['profile_schema']     = ! empty( $in['profile_schema'] ) ? 1 : 0;
		$out['rewrite_wpcontent']  = ! empty( $in['rewrite_wpcontent'] ) ? 1 : 0;
		$out['redirect_pages_dev'] = ! empty( $in['redirect_pages_dev'] ) ? 1 : 0;

		// React to dashboard_block toggle changes by applying / restoring the physical robots.txt.
		if ( class_exists( 'SFORGE_Dashboard_Block' ) ) {
			$prev = (int) ( get_option( SFORGE_OPT, [] )['dashboard_block'] ?? 1 );
			if ( $prev && ! $out['dashboard_block'] ) {
				SFORGE_Dashboard_Block::on_deactivate();
			} elseif ( ! $prev && $out['dashboard_block'] ) {
				SFORGE_Dashboard_Block::on_activate();
			}
		}
		return $out;
	}

	protected function sanitize_robots( $txt ) {
		// Normalise line endings, strip control chars except \n/\t, cap length.
		$txt = str_replace( [ "\r\n", "\r" ], "\n", $txt );
		$txt = preg_replace( '/[^\P{C}\n\t]+/u', '', $txt );
		$txt = substr( $txt, 0, 20000 );
		return trim( $txt );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SFORGE_DIR . 'admin/settings-page.php';
	}

	public function action_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'sforge_action' );
		$d = new SFORGE_Deployer();
		$r = $d->test_connection();
		$msg = is_wp_error( $r ) ? 'test_fail' : 'test_ok';
		if ( is_wp_error( $r ) ) {
			SFORGE_Logger::log( 'Test FAIL: ' . $r->get_error_message(), 'error' );
		} else {
			SFORGE_Logger::log( 'Test OK: project ' . esc_html( self::get( 'project_name' ) ) . ' reachable.' );
		}
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge', 'sforge_msg' => $msg ], admin_url( 'admin.php' ) ) . '#sforge-activity-log' );
		exit;
	}

	public function action_full_rebuild() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'sforge_action' );
		wp_clear_scheduled_hook( 'sforge_full_rebuild' );
		wp_schedule_single_event( time() + 5, 'sforge_full_rebuild' );
		SFORGE_Logger::log( 'Full rebuild queued (manual).' );
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge', 'sforge_msg' => 'rebuild_scheduled' ], admin_url( 'admin.php' ) ) . '#sforge-activity-log' );
		exit;
	}

	public function action_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'sforge_action' );
		SFORGE_Logger::clear();
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge' ], admin_url( 'admin.php' ) ) . '#sforge-activity-log' );
		exit;
	}
}
