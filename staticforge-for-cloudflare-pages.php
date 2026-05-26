<?php
/**
 * Plugin Name: StaticForge for Cloudflare Pages
 * Plugin URI: https://github.com/gunjanjaswal/staticforge-for-cloudflare-pages
 * Description: Auto-export the entire WordPress site (posts, pages, custom post types, archives, SEO meta) as static HTML with inlined CSS, and deploy to Cloudflare Pages on every publish/update via the Direct Upload API.
 * Version: 1.1.1
 * Author: Gunjan Jaswal
 * Author URI: https://www.gunjanjaswal.me
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: staticforge-for-cloudflare-pages
 * Requires at least: 5.8
 * Tested up to: 7.0
 * Requires PHP: 7.4
 *
 * Contact: hello@gunjanjaswal.me
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFORGE_VERSION', '1.1.1' );
define( 'SFORGE_FILE', __FILE__ );
define( 'SFORGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFORGE_URL', plugin_dir_url( __FILE__ ) );
define( 'SFORGE_OPT', 'sforge_settings' );
define( 'SFORGE_LOG_OPT', 'sforge_log' );

require_once SFORGE_DIR . 'includes/class-sforge-logger.php';
require_once SFORGE_DIR . 'includes/class-sforge-settings.php';
require_once SFORGE_DIR . 'includes/class-sforge-renderer.php';
require_once SFORGE_DIR . 'includes/class-sforge-crawler.php';
require_once SFORGE_DIR . 'includes/class-sforge-seo.php';
require_once SFORGE_DIR . 'includes/class-sforge-seo-injector.php';
require_once SFORGE_DIR . 'includes/class-sforge-profile-schema.php';
require_once SFORGE_DIR . 'includes/class-sforge-featured-image.php';
require_once SFORGE_DIR . 'includes/class-sforge-dashboard-block.php';
require_once SFORGE_DIR . 'includes/class-sforge-deployer.php';
require_once SFORGE_DIR . 'includes/class-sforge-assets-bundler.php';
require_once SFORGE_DIR . 'includes/class-sforge-hooks.php';

/**
 * One-time migration from the legacy "Send Static to Pages" install
 * (option keys `sstp_settings` / `sstp_log`, cron hook `sstp_full_rebuild`).
 * Runs early on plugins_loaded so the rest of the boot sequence sees the
 * new option keys. Safe to run repeatedly; a flag prevents repeat work.
 */
add_action( 'plugins_loaded', function() {
	if ( get_option( 'sforge_migrated_from_sstp' ) ) {
		return;
	}

	$legacy_settings = get_option( 'sstp_settings', null );
	if ( null !== $legacy_settings && false === get_option( 'sforge_settings' ) ) {
		update_option( 'sforge_settings', $legacy_settings );
	}

	$legacy_log = get_option( 'sstp_log', null );
	if ( null !== $legacy_log && false === get_option( 'sforge_log' ) ) {
		update_option( 'sforge_log', $legacy_log );
	}

	// Move any pending cron job to the new hook name.
	$next = wp_next_scheduled( 'sstp_full_rebuild' );
	if ( $next ) {
		wp_unschedule_event( $next, 'sstp_full_rebuild' );
		if ( ! wp_next_scheduled( 'sforge_full_rebuild' ) ) {
			wp_schedule_single_event( $next, 'sforge_full_rebuild' );
		}
	}

	update_option( 'sforge_migrated_from_sstp', 1 );
}, 1 );

add_action( 'plugins_loaded', function() {
	// Backfill new option keys on existing installs so settings added in later
	// versions get sensible defaults without requiring a deactivate/reactivate.
	$o = get_option( SFORGE_OPT, [] );
	if ( is_array( $o ) ) {
		$defaults = [
			'inline_css'              => 1,
			'debounce'                => 120,
			'include_homepage'        => 1,
			'include_taxonomies'      => 1,
			'include_authors'         => 1,
			'seo_inject'              => 1,
			'seo_inject_force'        => 0,
			'featured_image_priority' => 1,
			'dashboard_block'         => 1,
			'sitemap_post_types'      => [ 'post', 'page' ],
			'sitemap_homepage'        => 1,
			'sitemap_taxonomies'      => 1,
			'sitemap_authors'         => 1,
			'sitemap_split'           => 0,
			'profile_schema'          => 1,
			'rewrite_wpcontent'       => 0,
			'bundle_uploads'          => 0,
			'redirect_pages_dev'      => 1,
		];
		$changed = false;
		foreach ( $defaults as $k => $v ) {
			if ( ! array_key_exists( $k, $o ) ) {
				$o[ $k ] = $v;
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( SFORGE_OPT, $o );
		}
	}

	new SFORGE_Settings();
	new SFORGE_Hooks();
	new SFORGE_Seo_Injector();
	new SFORGE_Profile_Schema();
	new SFORGE_Featured_Image();
	new SFORGE_Dashboard_Block();
} );

register_activation_hook( __FILE__, function() {
	if ( ! get_option( SFORGE_OPT ) ) {
		update_option( SFORGE_OPT, [
			'account_id'   => '',
			'api_token'    => '',
			'project_name' => '',
			'branch'       => 'main',
			'post_types'   => [ 'post', 'page' ],
			'auto_deploy'  => 1,
			'inline_css'   => 1,
			'cf_pages_url' => '',
			'export_dir'   => 'sforge-export',
			'debounce'     => 120,
			'include_taxonomies' => 1,
			'include_authors'    => 1,
			'include_homepage'   => 1,
			'robots_txt'         => '',
			'seo_inject'         => 1,
			'seo_inject_force'   => 0,
			'featured_image_priority' => 1,
			'dashboard_block'    => 1,
			'sitemap_post_types' => [ 'post', 'page' ],
			'sitemap_homepage'   => 1,
			'sitemap_taxonomies' => 1,
			'sitemap_authors'    => 1,
			'sitemap_split'      => 0,
			'profile_schema'     => 1,
			'rewrite_wpcontent'  => 0,
			'bundle_uploads'     => 0,
			'redirect_pages_dev' => 1,
		] );
	}
	if ( class_exists( 'SFORGE_Dashboard_Block' ) ) {
		SFORGE_Dashboard_Block::on_activate();
	}
} );

register_deactivation_hook( __FILE__, function() {
	wp_clear_scheduled_hook( 'sforge_full_rebuild' );
	if ( class_exists( 'SFORGE_Dashboard_Block' ) ) {
		SFORGE_Dashboard_Block::on_deactivate();
	}
} );

/**
 * Plugin action links: Settings + Support on Ko-fi (next to Deactivate).
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=sforge' ) ) . '">' . esc_html__( 'Settings', 'staticforge-for-cloudflare-pages' ) . '</a>';
	$kofi     = '<a href="https://ko-fi.com/gunjanjaswal" target="_blank" style="color:#0073aa; font-weight:bold;">' . esc_html__( 'Support on Ko-fi', 'staticforge-for-cloudflare-pages' ) . '</a>';
	array_unshift( $links, $settings, $kofi );
	return $links;
} );

/**
 * Plugin row meta: strip the auto-generated "Visit plugin site" link (points at
 * the Plugin URI header) and add Plugin Support + Contact Developer entries.
 * WordPress.org-hosted plugins already get a "View details" link auto-injected
 * by core, so we no longer add our own.
 */
add_filter( 'plugin_row_meta', function ( $links, $file ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}

	$plugin_slug = 'staticforge-for-cloudflare-pages';

	// Strip the auto-injected "Visit plugin site" link (points at the Plugin URI header).
	$plugin_uri = 'https://github.com/gunjanjaswal/staticforge-for-cloudflare-pages';
	foreach ( $links as $i => $link ) {
		if ( false !== strpos( $link, $plugin_uri ) ) {
			unset( $links[ $i ] );
		}
	}
	$links = array_values( $links );

	$links[] = '<a href="https://wordpress.org/support/plugin/' . $plugin_slug . '/" target="_blank">' . esc_html__( 'Plugin Support', 'staticforge-for-cloudflare-pages' ) . '</a>';
	$links[] = '<a href="mailto:hello@gunjanjaswal.me">' . esc_html__( 'Contact Developer', 'staticforge-for-cloudflare-pages' ) . '</a>';

	return $links;
}, 10, 2 );

/**
 * WordPress 7.0 Connectors API integration.
 *
 * The plugin stores the Cloudflare API token inside a `sforge_settings` array
 * alongside account_id, project_name, branch, etc. The Connectors API's
 * `api_key` auth method only handles a single top-level setting value, so the
 * array shape does not map cleanly. The connector is registered with
 * `method: none` as a navigation entry that surfaces this plugin on the
 * central Connections screen and links back to the settings page for
 * credential management. Falls back silently on WordPress < 7.0.
 */
add_action( 'wp_connectors_init', function ( $registry ) {
	if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
		do_action( 'sforge_register_connectors', false, null );
		return;
	}

	$registry->register(
		'sforge-cloudflare-pages',
		array(
			'name'           => __( 'StaticForge for Cloudflare Pages', 'staticforge-for-cloudflare-pages' ),
			'description'    => __( 'Cloudflare Pages Direct Upload deployment target. Manage the Account ID, API Token, project, and branch on the StaticForge settings screen.', 'staticforge-for-cloudflare-pages' ),
			'type'           => 'deployment_target',
			'authentication' => array(
				'method'          => 'none',
				'credentials_url' => admin_url( 'admin.php?page=sforge' ),
			),
			'plugin'         => array(
				'file'      => plugin_basename( __FILE__ ),
				'is_active' => function () {
					return defined( 'SFORGE_VERSION' );
				},
			),
		)
	);

	/**
	 * Fires after the plugin registers its Cloudflare Pages connector.
	 *
	 * @param bool                  $registered True if registration ran.
	 * @param WP_Connector_Registry $registry   Core connector registry.
	 */
	do_action( 'sforge_register_connectors', true, $registry );
} );
