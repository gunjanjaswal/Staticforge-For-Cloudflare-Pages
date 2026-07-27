<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies operator-nominated files and folders from the WordPress install into
 * the export mirror, so assets the crawler never sees in rendered HTML — a
 * plugin's icon font (Elementor's Font Awesome, say), a webfont directory, a
 * downloadable PDF — still ship inside the Cloudflare Pages deploy.
 *
 * Paths are given relative to the WordPress root and read straight off local
 * disk (this code runs on the same box as the files), so there is no HTTP
 * round-trip and no directory-listing guesswork. A whole folder is pulled in
 * recursively; a trailing-wildcard entry (uploads/2025/*.pdf) is expanded with
 * glob(); a single file is taken as-is.
 *
 * Everything is confined to the WordPress root: a path that resolves outside it
 * (via .. or a symlink) is dropped and logged, never copied. Hard ceilings on
 * file count and total size stop a careless entry — the whole wp-content tree,
 * say — from ballooning a deploy.
 */
class SFORGE_Extra_Assets {

	const MAX_FILES = 5000;
	const MAX_BYTES = 209715200; // 200 MB.

	/**
	 * The configured include paths, sanitised to safe root-relative strings.
	 *
	 * @return string[] e.g. [ 'wp-content/plugins/elementor/assets/lib/font-awesome' ]
	 */
	public static function paths() {
		$raw = SFORGE_Settings::get( 'extra_paths', [] );
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\r\n]+/', $raw );
		}
		$root = self::root();
		$out  = [];
		foreach ( self::sanitize_list( (array) $raw ) as $rel ) {
			$resolved = self::resolve_root_relative( $rel, $root );
			if ( $resolved !== '' && ! in_array( $resolved, $out, true ) ) {
				$out[] = $resolved;
			}
		}
		return $out;
	}

	/**
	 * Let an operator enter a path either from the WordPress root
	 * (`wp-content/astra-local-fonts`) or relative to wp-content/
	 * (`astra-local-fonts`) — whichever they happened to copy. When the entry as
	 * typed doesn't exist on disk but prefixing `wp-content/` finds it, the
	 * prefixed form is used, so both the copy and the URL rewrite act on the real
	 * files. An entry that already resolves, or that resolves under neither, is
	 * returned unchanged. This is what stops "I added the path but the fonts still
	 * point at the old domain" — the rewrite hinges on the `wp-content/` prefix
	 * (see wpcontent_prefixes()).
	 *
	 * @return string The effective root-relative path.
	 */
	protected static function resolve_root_relative( $rel, $root ) {
		if ( $rel === '' || strpos( $rel, 'wp-content/' ) === 0 ) {
			return $rel;
		}
		if ( strpos( $rel, '*' ) !== false ) {
			$base = self::glob_base( $rel );
			if ( $base !== '' && ! @is_dir( $root . '/' . $base ) && @is_dir( $root . '/wp-content/' . $base ) ) {
				return 'wp-content/' . $rel;
			}
			return $rel;
		}
		if ( ! @file_exists( $root . '/' . $rel ) && @file_exists( $root . '/wp-content/' . $rel ) ) {
			return 'wp-content/' . $rel;
		}
		return $rel;
	}

	/** The directory portion of a glob entry, before the first `*`. */
	protected static function glob_base( $rel ) {
		$star = strpos( $rel, '*' );
		if ( false === $star ) {
			return $rel;
		}
		$head  = substr( $rel, 0, $star );
		$slash = strrpos( $head, '/' );
		return ( false === $slash ) ? '' : substr( $head, 0, $slash );
	}

	/**
	 * Normalise a list of entered paths: forward slashes, no leading slash, no
	 * scheme, no traversal. Order preserved, duplicates and blanks dropped. Used
	 * both on save and on read so the stored value and the value acted on always
	 * agree.
	 *
	 * @return string[]
	 */
	public static function sanitize_list( array $list ) {
		$out = [];
		foreach ( $list as $line ) {
			$p = self::clean_path( (string) $line );
			if ( $p !== '' && ! in_array( $p, $out, true ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * One entered line -> a safe root-relative path, or '' to reject it. A pasted
	 * absolute URL is reduced to its path so someone copying a wp-content URL out
	 * of the browser still gets a usable entry.
	 */
	protected static function clean_path( $p ) {
		$p = trim( $p );
		if ( $p === '' ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $p ) ) {
			$path = wp_parse_url( $p, PHP_URL_PATH );
			$p    = is_string( $path ) ? $path : '';
		}
		$p = str_replace( '\\', '/', $p );
		$p = trim( ltrim( $p, '/' ) );
		if ( $p === '' ) {
			return '';
		}
		// Traversal is rejected outright; containment is re-checked at read time.
		foreach ( explode( '/', $p ) as $seg ) {
			if ( $seg === '..' ) {
				return '';
			}
		}
		return rtrim( $p, '/' );
	}

	/**
	 * Expand every configured path into concrete files.
	 *
	 * @return array [ deploy_relative_path => absolute_filesystem_path ]
	 */
	public function collect() {
		$paths = self::paths();
		if ( empty( $paths ) ) {
			return [];
		}

		$root   = self::root();
		$out    = [];
		$bytes  = 0;
		$capped = false;

		foreach ( $paths as $rel ) {
			foreach ( $this->expand( $rel, $root ) as $deploy_rel => $abs ) {
				if ( isset( $out[ $deploy_rel ] ) ) {
					continue;
				}
				$size = @filesize( $abs );
				$size = ( false === $size ) ? 0 : (int) $size;
				if ( count( $out ) >= self::MAX_FILES || ( $bytes + $size ) > self::MAX_BYTES ) {
					$capped = true;
					break 2;
				}
				$out[ $deploy_rel ] = $abs;
				$bytes            += $size;
			}
		}

		if ( $capped ) {
			SFORGE_Logger::log( sprintf(
				'Extra assets capped at %d files / %d MB — narrow the include paths if more was intended.',
				self::MAX_FILES, (int) round( self::MAX_BYTES / 1048576 )
			), 'warn' );
		}
		return $out;
	}

	/**
	 * The sub-paths, relative to /wp-content/, of every configured include that
	 * lives under wp-content. The renderer uses these to point the matching
	 * /wp-content/* URLs at the CF host instead of leaving them on the origin, so
	 * the bundled copy is what the deployed page actually loads. A directory or
	 * wildcard entry gets a trailing slash (prefix match); a single file stays
	 * exact. Entries outside /wp-content/ need no entry — rewrite_urls already
	 * swaps their host to CF.
	 *
	 * @return string[] e.g. [ 'plugins/elementor/assets/lib/font-awesome/' ]
	 */
	public static function wpcontent_prefixes() {
		$root = self::root();
		$out  = [];
		foreach ( self::paths() as $rel ) {
			if ( strpos( $rel, 'wp-content/' ) !== 0 ) {
				continue;
			}
			$sub = substr( $rel, strlen( 'wp-content/' ) );
			if ( $sub === '' ) {
				continue;
			}
			$star = strpos( $sub, '*' );
			if ( false !== $star ) {
				// uploads/2025/*.pdf -> uploads/2025/
				$sub   = substr( $sub, 0, $star );
				$slash = strrpos( $sub, '/' );
				$sub   = ( false === $slash ) ? '' : substr( $sub, 0, $slash + 1 );
			} elseif ( is_dir( $root . '/' . $rel ) ) {
				$sub = rtrim( $sub, '/' ) . '/';
			}
			if ( $sub !== '' && ! in_array( $sub, $out, true ) ) {
				$out[] = $sub;
			}
		}
		return $out;
	}

	/** WordPress root, no trailing slash, symlinks resolved where possible. */
	protected static function root() {
		$root = realpath( ABSPATH );
		return $root ? untrailingslashit( str_replace( '\\', '/', $root ) ) : untrailingslashit( str_replace( '\\', '/', ABSPATH ) );
	}

	/**
	 * One configured entry -> its files. Handles a single file, a directory
	 * (recursive) and a trailing glob. Anything resolving outside the WP root is
	 * dropped with a log line.
	 *
	 * @return array [ deploy_relative_path => absolute_path ]
	 */
	protected function expand( $rel, $root ) {
		if ( strpos( $rel, '*' ) !== false ) {
			return $this->collect_files( glob( $root . '/' . $rel, GLOB_NOSORT ) ?: [], $root );
		}
		if ( ! self::contained( $root . '/' . $rel, $root ) ) {
			SFORGE_Logger::log( 'Extra asset path skipped (outside WordPress root): ' . $rel, 'warn' );
			return [];
		}
		$abs = $root . '/' . $rel;
		if ( is_file( $abs ) ) {
			return [ $rel => $abs ];
		}
		if ( is_dir( $abs ) ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			$files = [];
			foreach ( $it as $file ) {
				$files[] = $file->getPathname();
			}
			return $this->collect_files( $files, $root );
		}
		SFORGE_Logger::log( 'Extra asset path not found: ' . $rel, 'warn' );
		return [];
	}

	/**
	 * Turn a list of absolute paths into a [ deploy_rel => abs ] map, keeping only
	 * regular files that stay inside the root (a symlink pointing out is dropped).
	 */
	protected function collect_files( array $paths, $root ) {
		$out    = [];
		$prefix = strlen( $root ) + 1;
		foreach ( $paths as $path ) {
			if ( ! is_file( $path ) || ! self::contained( $path, $root ) ) {
				continue;
			}
			$deploy_rel = ltrim( str_replace( '\\', '/', substr( str_replace( '\\', '/', $path ), $prefix ) ), '/' );
			if ( $deploy_rel !== '' ) {
				$out[ $deploy_rel ] = $path;
			}
		}
		return $out;
	}

	/**
	 * True when $path resolves to something inside $root. realpath() collapses ..
	 * and symlinks; a not-yet-existing path fails closed.
	 */
	protected static function contained( $path, $root ) {
		$rp = realpath( $path );
		if ( false === $rp ) {
			return false;
		}
		$rp = str_replace( '\\', '/', $rp );
		return $rp === $root || strpos( $rp, $root . '/' ) === 0;
	}
}
