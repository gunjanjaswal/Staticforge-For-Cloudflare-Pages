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
		add_action( 'admin_post_sforge_partial_rebuild', [ $this, 'action_partial_rebuild' ] );
		add_action( 'admin_post_sforge_clear_log',       [ $this, 'action_clear_log' ] );
		add_action( 'admin_post_sforge_deploy_form',     [ $this, 'action_deploy_form' ] );
		add_action( 'admin_post_sforge_remove_form',     [ $this, 'action_remove_form' ] );
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
			wp_enqueue_script( 'sforge-help', SFORGE_URL . 'admin/help.js', [], $this->asset_ver( 'admin/help.js' ), true );
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
			'content' => sprintf(
				/* translators: %s: URL of the full Setup Guide page. */
				__( '<p><strong>Five steps to first deploy:</strong></p><ol><li>Create a Cloudflare Pages project in <em>Direct Upload</em> mode (note the slug, e.g. <code>mysite</code>).</li><li>Create an API Token with <code>Account &middot; Cloudflare Pages &middot; Edit</code>.</li><li>Copy your Account ID from the CF dashboard sidebar.</li><li>Fill the fields on this page &rarr; Save &rarr; <em>Test Connection</em>.</li><li>Click <em>Rebuild + Deploy Now</em> &rarr; watch the activity log.</li></ol><p>Full walkthrough: <a href="%s">Setup Guide</a></p>', 'staticforge-for-cloudflare-pages' ),
				esc_url( $help_url )
			),
		] );
		$screen->add_help_tab( [
			'id'      => 'sforge_fields',
			'title'   => __( 'Field Reference', 'staticforge-for-cloudflare-pages' ),
			'content' => __( '<dl><dt><strong>Account ID</strong></dt><dd>32-char hex from CF Dashboard right sidebar.</dd><dt><strong>API Token</strong></dt><dd>Permission: <code>Account &middot; Cloudflare Pages &middot; Edit</code>. Shown once at creation &mdash; copy it.</dd><dt><strong>Pages Project</strong></dt><dd>The slug only, NOT the <code>.pages.dev</code> URL.</dd><dt><strong>Branch</strong></dt><dd><code>main</code> = production. Anything else = preview deployment.</dd><dt><strong>Public Site URL</strong></dt><dd>Where the static site lives publicly. Used to rewrite WP URLs in HTML output.</dd><dt><strong>Inline CSS</strong></dt><dd>Embeds linked stylesheets so each exported page is self-contained.</dd><dt><strong>Debounce</strong></dt><dd>Rapid edits within this many seconds collapse into a single deploy.</dd><dt><strong>Rewrite <code>/wp-content/</code> URLs</strong></dt><dd>Rewrites every <code>/wp-content/*</code> URL (themes, plugins, uploads) to the live host. Requires a Worker/Nginx proxy on the live host pointing back at the dashboard.</dd><dt><strong>Bundle <code>/wp-content/uploads/</code> into deploy</strong></dt><dd>Softer alternative for shared-hosting origins whose firewall blocks Cloudflare. Fetches every uploads URL referenced in rendered HTML and ships them inside the CF Pages deploy. Themes/plugins still load from origin. Ignored when the rewrite-all toggle above is on.</dd><dt><strong>Bundle self-hosted fonts</strong></dt><dd>Ships <code>@font-face</code> fonts served from your WordPress host inside the deploy and rewrites their URLs to the Public Site URL, so they load same-origin instead of being blocked by CORS. On by default.</dd><dt><strong>Extra paths to include</strong></dt><dd>One path per line, relative to your WordPress root (e.g. <code>wp-content/plugins/elementor/assets/lib/font-awesome</code>). Files and whole folders are copied straight off disk into the deploy, and their <code>/wp-content/*</code> URLs are pointed at Cloudflare so the bundled copy is what loads. For assets the crawler never sees in the HTML &mdash; plugin icon fonts, a webfont folder, a downloadable PDF.</dd><dt><strong>Redirect <code>*.pages.dev</code> to live host</strong></dt><dd>Injects a tiny client-side JS snippet so any browser landing on <code>&lt;project&gt;.pages.dev</code> bounces to the canonical Public Site URL (preserves path + query). Auto-skipped when Public Site URL is itself a <code>.pages.dev</code> URL.</dd></dl>', 'staticforge-for-cloudflare-pages' ),
		] );
		$screen->add_help_tab( [
			'id'      => 'sforge_trouble',
			'title'   => __( 'Troubleshooting', 'staticforge-for-cloudflare-pages' ),
			'content' => sprintf(
				/* translators: %s: URL of the full Setup Guide page. */
				__( '<ul><li><code>Project not found</code> &rarr; Pages Project must be the slug, not the URL.</li><li><code>Request body is incorrect</code> &rarr; old plugin version. Update.</li><li>Stuck on <em>Manifest</em> &rarr; PHP memory/timeout limit, or upload batch too large.</li><li>Sub-sitemaps missing &rarr; ensure plugin v1.0.0+ (handles CDATA-wrapped <code>&lt;loc&gt;</code>).</li><li>Live site shows <code>noindex</code> &rarr; turn off WordPress &rarr; Settings &rarr; Reading "Discourage search engines".</li><li>Fonts blocked by CORS on the live site &rarr; keep <strong>Bundle self-hosted fonts</strong> ticked and run a Full Rebuild so the fonts ship in the deploy.</li><li>Images return <code>520</code>/<code>522</code> on the live site &rarr; origin firewall blocks Cloudflare. Tick <strong>Bundle <code>/wp-content/uploads/</code> into deploy</strong>, untick <strong>Rewrite <code>/wp-content/</code> URLs</strong>, rebuild.</li><li><code>*.pages.dev</code> URL doesn\'t redirect &rarr; redirect is JS-based (Direct Upload can\'t activate <code>_worker.js</code>/Functions). <code>curl -I</code> won\'t see it; test in a real browser. Make sure <strong>Public Site URL</strong> is a non-<code>.pages.dev</code> URL.</li></ul><p>Detailed: <a href="%s">Setup Guide</a></p>', 'staticforge-for-cloudflare-pages' ),
				esc_url( $help_url )
			),
		] );
		$screen->set_help_sidebar(
			'<p><strong>StaticForge for Cloudflare Pages</strong></p>' .
			/* translators: %s: author name, linked to their website. */
			'<p>' . sprintf( __( 'By %s', 'staticforge-for-cloudflare-pages' ), '<a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener">Gunjan Jaswal</a>' ) . '</p>' .
			'<p><a href="mailto:hello@gunjanjaswal.me">hello@gunjanjaswal.me</a></p>'
		);
	}

	public function ajax_get_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'msg' => 'forbidden' ], 403 );
		}
		check_ajax_referer( 'sforge_log', 'nonce' );
		$next_full    = wp_next_scheduled( SFORGE_Rebuild::HOOK_FULL );
		$next_partial = wp_next_scheduled( SFORGE_Rebuild::HOOK_PARTIAL );
		$queued  = array_filter( [ $next_full, $next_partial ] );
		$next    = $queued ? min( $queued ) : false;
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
			               ( strpos( $top_msg, 'no urls to export' ) !== false );
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
		$out['render_origin']      = $this->sanitize_render_origin( $in['render_origin'] ?? '' );
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
		$out['bundle_uploads']     = ! empty( $in['bundle_uploads'] ) ? 1 : 0;
		$out['bundle_fonts']       = ! empty( $in['bundle_fonts'] ) ? 1 : 0;
		$out['extra_paths']        = SFORGE_Extra_Assets::sanitize_list( preg_split( '/[\r\n]+/', (string) ( $in['extra_paths'] ?? '' ) ) );
		$out['redirect_pages_dev'] = ! empty( $in['redirect_pages_dev'] ) ? 1 : 0;

		// Form handler config. Secrets (the email-provider key, Turnstile secret) are
		// never part of this form — they are submitted straight to the deploy handler
		// and pushed to the Worker, never stored. See action_deploy_form().
		$presets              = SFORGE_Forms::presets();
		$provider             = sanitize_key( $in['form_provider'] ?? 'resend' );
		$out['form_enabled']  = ! empty( $in['form_enabled'] ) ? 1 : 0;
		$out['form_worker_name']      = sanitize_text_field( $in['form_worker_name'] ?? '' );
		$out['form_provider']         = isset( $presets[ $provider ] ) ? $provider : 'resend';
		$out['form_endpoint']         = esc_url_raw( $in['form_endpoint'] ?? '' );
		$out['form_auth_header_name'] = sanitize_text_field( $in['form_auth_header_name'] ?? 'Authorization' );
		$out['form_auth_header_tpl']  = sanitize_text_field( $in['form_auth_header_tpl'] ?? 'Bearer ${SFORGE_FORM_KEY}' );
		$out['form_content_type']     = ( ( $in['form_content_type'] ?? 'json' ) === 'form' ) ? 'form' : 'json';
		$out['form_body_template']    = $this->sanitize_body_template( (string) ( $in['form_body_template'] ?? '' ) );
		$out['form_required_fields']  = $this->sanitize_field_list( (string) ( $in['form_required_fields'] ?? 'name,email,message' ) );
		$out['form_turnstile']        = ! empty( $in['form_turnstile'] ) ? 1 : 0;
		$out['form_turnstile_site']   = sanitize_text_field( $in['form_turnstile_site'] ?? '' );

		// The deployed Worker URL and the "key is set" flag are written by the deploy
		// handler, not this form. Carry them over so a normal Save doesn't wipe them.
		$existing                 = get_option( SFORGE_OPT, [] );
		$out['form_worker_url']   = isset( $existing['form_worker_url'] ) ? esc_url_raw( $existing['form_worker_url'] ) : '';
		$out['form_key_set']      = ! empty( $existing['form_key_set'] ) ? 1 : 0;

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

	/**
	 * Render-origin override: keep only scheme://host[:port]. Accepts loopback /
	 * private hosts (127.0.0.1, localhost, internal IPs). Empty = feature off.
	 */
	protected function sanitize_render_origin( $val ) {
		$val = trim( (string) $val );
		if ( $val === '' ) {
			return '';
		}
		// Tolerate a bare host[:port] entry by assuming http://.
		if ( ! preg_match( '#^https?://#i', $val ) ) {
			$val = 'http://' . $val;
		}
		$p = wp_parse_url( $val );
		if ( empty( $p['host'] ) ) {
			return '';
		}
		$scheme = ( isset( $p['scheme'] ) && strtolower( $p['scheme'] ) === 'https' ) ? 'https' : 'http';
		$port   = isset( $p['port'] ) ? ':' . (int) $p['port'] : '';
		return $scheme . '://' . strtolower( $p['host'] ) . $port;
	}

	/**
	 * The email-provider request body template. Kept close to verbatim (it is JSON
	 * with {{tokens}}), only normalising line endings, stripping control chars
	 * except tab/newline, and capping length. JSON validity is checked at deploy
	 * time, not here, so a half-edited template can still be saved.
	 */
	protected function sanitize_body_template( $txt ) {
		$txt = str_replace( [ "\r\n", "\r" ], "\n", (string) $txt );
		$txt = preg_replace( '/[^\P{C}\n\t]+/u', '', $txt );
		return substr( trim( $txt ), 0, 10000 );
	}

	/** Comma or newline separated field names -> a clean list of sanitised keys. */
	protected function sanitize_field_list( $csv ) {
		$parts = preg_split( '/[\s,]+/', (string) $csv );
		$out   = [];
		foreach ( (array) $parts as $p ) {
			$k = sanitize_key( $p );
			if ( $k !== '' && ! in_array( $k, $out, true ) ) {
				$out[] = $k;
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
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
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
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
		}
		check_admin_referer( 'sforge_action' );
		SFORGE_Rebuild::schedule( SFORGE_Rebuild::MODE_FULL, [], 'manual', 5 );
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge', 'sforge_msg' => 'rebuild_scheduled' ], admin_url( 'admin.php' ) ) . '#sforge-activity-log' );
		exit;
	}

	/**
	 * Rebuild only what has changed since the last deploy. Any posts already
	 * queued by an edit stay queued; this just runs the rebuild now instead of
	 * waiting out the debounce.
	 */
	public function action_partial_rebuild() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
		}
		check_admin_referer( 'sforge_action' );
		SFORGE_Rebuild::schedule( SFORGE_Rebuild::MODE_PARTIAL, [], 'manual', 5 );
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge', 'sforge_msg' => 'rebuild_scheduled' ], admin_url( 'admin.php' ) ) . '#sforge-activity-log' );
		exit;
	}

	/**
	 * Build the form handler from the saved config plus the secret(s) entered on
	 * this request, deploy it as a standalone Worker, and remember its URL.
	 *
	 * The email-provider key (and Turnstile secret) arrive only in this POST and
	 * are pushed straight to the Worker as secrets — never written to options.
	 */
	public function action_deploy_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
		}
		check_admin_referer( 'sforge_action' );

		$key = isset( $_POST['sforge_form_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sforge_form_key'] ) ) ) : '';
		$ts  = isset( $_POST['sforge_turnstile_secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sforge_turnstile_secret'] ) ) ) : '';

		$err = $this->validate_form_config( $key, $ts );
		if ( $err ) {
			SFORGE_Logger::log( 'Form handler: ' . $err, 'error' );
			$this->redirect_forms( 'form_fail' );
		}

		$config = SFORGE_Forms::worker_config();
		$script = SFORGE_Forms::build_worker_script( $config );

		$secrets = [ SFORGE_Forms::SECRET_KEY => $key ];
		if ( ! empty( $config['turnstile'] ) ) {
			$secrets[ SFORGE_Forms::SECRET_TURNSTILE ] = $ts;
		}

		$deployer = new SFORGE_Worker_Deployer();
		$result   = $deployer->deploy( $script, $secrets );
		if ( is_wp_error( $result ) ) {
			SFORGE_Logger::log( 'Form handler deploy FAIL: ' . $result->get_error_message(), 'error' );
			$this->redirect_forms( 'form_fail' );
		}

		$o = get_option( SFORGE_OPT, [] );
		$o['form_worker_url']  = esc_url_raw( $result['url'] );
		$o['form_worker_name'] = sanitize_text_field( $result['name'] );
		$o['form_key_set']     = 1;
		$o['form_enabled']     = 1;
		update_option( SFORGE_OPT, $o );

		SFORGE_Logger::log( sprintf(
			'Form handler deployed: <a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $result['url'] ),
			esc_html( $result['url'] )
		) );
		$this->redirect_forms( 'form_deployed' );
	}

	/**
	 * Pre-deploy validation. Returns an error string, or '' when the config is
	 * ready to ship.
	 */
	protected function validate_form_config( $key, $ts ) {
		if ( self::get( 'account_id', '' ) === '' || self::get( 'api_token', '' ) === '' ) {
			return 'add your Cloudflare Account ID and API token first.';
		}
		if ( $key === '' ) {
			return 'enter the email-provider API key to deploy (it is never stored, so re-enter it to redeploy).';
		}
		$endpoint = (string) self::get( 'form_endpoint', '' );
		if ( $endpoint === '' || ! preg_match( '#^https?://#i', $endpoint ) ) {
			return 'set a valid provider endpoint URL under Forms first.';
		}
		$tpl = (string) self::get( 'form_body_template', '' );
		json_decode( $tpl, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return 'the request body template is not valid JSON — fix it under Forms and save before deploying.';
		}
		if ( (int) self::get( 'form_turnstile', 0 ) && $ts === '' ) {
			return 'Turnstile is enabled, so the Turnstile secret key is required (it is set on every deploy).';
		}
		return '';
	}

	/** Tear down the deployed Worker and forget its URL. */
	public function action_remove_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
		}
		check_admin_referer( 'sforge_action' );

		$deployer = new SFORGE_Worker_Deployer();
		$result   = $deployer->delete();
		if ( is_wp_error( $result ) ) {
			SFORGE_Logger::log( 'Form handler removal FAIL: ' . $result->get_error_message(), 'error' );
			$this->redirect_forms( 'form_fail' );
		}

		$o = get_option( SFORGE_OPT, [] );
		$o['form_worker_url'] = '';
		$o['form_key_set']    = 0;
		$o['form_enabled']    = 0;
		update_option( SFORGE_OPT, $o );

		SFORGE_Logger::log( 'Form handler removed.' );
		$this->redirect_forms( 'form_removed' );
	}

	/** Redirect back to the settings page with a flash message, anchored at Forms. */
	protected function redirect_forms( $msg ) {
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge', 'sforge_msg' => $msg ], admin_url( 'admin.php' ) ) . '#sforge-forms' );
		exit;
	}

	public function action_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
		}
		check_admin_referer( 'sforge_action' );
		SFORGE_Logger::clear();
		wp_safe_redirect( add_query_arg( [ 'page' => 'sforge' ], admin_url( 'admin.php' ) ) . '#sforge-activity-log' );
		exit;
	}
}
