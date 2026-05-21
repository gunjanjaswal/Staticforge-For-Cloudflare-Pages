<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the WordPress dashboard install out of search engines while the plugin
 * is active — so editors keep using WP, search results show only the public
 * Cloudflare Pages site.
 *
 * Layered defence (all bypassed when the plugin's own renderer is fetching,
 * detected by the X-SFORGE-Export header, AND for social/messaging scrapers and
 * /wp-content/uploads/* media so og:image previews keep working):
 *   1. Physical robots.txt at webroot — disallow everything for general bots,
 *      but explicitly allow /wp-content/uploads/ for known social scrapers.
 *   2. `robots_txt` filter — covers WP's dynamic robots.txt generation.
 *   3. `wp_robots` filter — adds `noindex,nofollow` to the meta robots tag.
 *   4. `send_headers` action — emits `X-Robots-Tag: noindex,nofollow` HTTP
 *      header so search engines see noindex even on non-HTML responses.
 *      Skipped for media paths and social-scraper user agents.
 */
class SFORGE_Dashboard_Block {

	const ROBOTS_FILE = 'robots.txt';
	const BACKUP_FILE = 'robots.txt.sforge-backup';
	const MARKER      = '# StaticForge for Cloudflare Pages — dashboard noindex';

	/**
	 * User-agent substrings for social / messaging / preview scrapers that need
	 * access to /wp-content/uploads/* so og:image, oEmbed thumbnails, etc. work
	 * when posts are shared. All match case-insensitive via stripos.
	 */
	const SOCIAL_BOTS = [
		'facebookexternalhit',
		'facebookcatalog',
		'meta-externalagent',
		'Twitterbot',
		'LinkedInBot',
		'Pinterestbot',
		'WhatsApp',
		'Slackbot-LinkExpanding',
		'Slackbot',
		'Discordbot',
		'TelegramBot',
		'Applebot',
		'redditbot',
		'Tumblr',
		'iframely',
		'Embedly',
		'Mastodon',
		'Bluesky',
	];

	public function __construct() {
		if ( ! (int) SFORGE_Settings::get( 'dashboard_block', 1 ) ) {
			return;
		}
		add_filter( 'robots_txt',   [ $this, 'robots_txt' ], 999, 2 );
		add_filter( 'wp_robots',    [ $this, 'wp_robots' ], 999 );
		add_action( 'send_headers', [ $this, 'send_headers' ] );
	}

	public static function is_export_request() {
		return ! empty( $_SERVER['HTTP_X_SFORGE_EXPORT'] );
	}

	public static function is_social_scraper() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
		if ( $ua === '' ) {
			return false;
		}
		foreach ( self::SOCIAL_BOTS as $bot ) {
			if ( stripos( $ua, $bot ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public static function is_media_request() {
		$path = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		return strpos( $path, '/wp-content/uploads/' ) !== false;
	}

	public function robots_txt( $output, $public ) {
		if ( self::is_export_request() ) {
			return $output;
		}
		return self::build_robots_content();
	}

	public function wp_robots( $robots ) {
		if ( self::is_export_request() || self::is_social_scraper() ) {
			return $robots;
		}
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
		return $robots;
	}

	public function send_headers() {
		if ( self::is_export_request() || self::is_social_scraper() || self::is_media_request() ) {
			return;
		}
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true );
		}
	}

	/**
	 * Build the dashboard robots.txt content with explicit Allow rules for social
	 * scrapers so og:image previews and oEmbed thumbnails resolve correctly when
	 * posts get shared on Facebook / LinkedIn / Twitter / etc.
	 */
	public static function build_robots_content() {
		$lines = [];
		$lines[] = self::MARKER;
		$lines[] = '# Social / messaging / preview scrapers need /wp-content/uploads/';
		$lines[] = '# for og:image and oEmbed thumbnails to render.';
		$lines[] = '';
		foreach ( self::SOCIAL_BOTS as $bot ) {
			$lines[] = 'User-agent: ' . $bot;
			$lines[] = 'Allow: /wp-content/uploads/';
			$lines[] = 'Disallow: /';
			$lines[] = '';
		}
		$lines[] = '# Block everything else from indexing the dashboard.';
		$lines[] = 'User-agent: *';
		$lines[] = 'Disallow: /';
		return implode( "\n", $lines ) . "\n";
	}

	/* ------------------------------------------------------------------ *
	 * Activation / deactivation file handling
	 * ------------------------------------------------------------------ */

	protected static function fs() {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}
		return $wp_filesystem;
	}

	public static function on_activate() {
		$root   = trailingslashit( ABSPATH );
		$path   = $root . self::ROBOTS_FILE;
		$backup = $root . self::BACKUP_FILE;
		$fs     = self::fs();

		if ( $fs && $fs->exists( $path ) && ! $fs->exists( $backup ) ) {
			$existing = $fs->get_contents( $path );
			// Don't back up our own previously-written file.
			if ( $existing !== false && strpos( $existing, self::MARKER ) === false ) {
				$fs->move( $path, $backup, true );
			}
		}
		$content = self::build_robots_content() . "\n# Auto-restored when the plugin is deactivated.\n";
		if ( $fs ) {
			$fs->put_contents( $path, $content, FS_CHMOD_FILE );
		}
	}

	public static function on_deactivate() {
		$root   = trailingslashit( ABSPATH );
		$path   = $root . self::ROBOTS_FILE;
		$backup = $root . self::BACKUP_FILE;
		$fs     = self::fs();

		// Only remove our file (don't clobber if user replaced it manually).
		if ( $fs && $fs->exists( $path ) ) {
			$existing = $fs->get_contents( $path );
			if ( $existing !== false && strpos( $existing, self::MARKER ) !== false ) {
				wp_delete_file( $path );
			}
		}
		if ( $fs && $fs->exists( $backup ) && ! $fs->exists( $path ) ) {
			$fs->move( $backup, $path, true );
		}
	}
}
