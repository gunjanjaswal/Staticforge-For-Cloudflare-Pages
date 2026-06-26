<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches /wp-content/uploads/* files from the WordPress origin and returns them as a
 * [ relative_path => binary_content ] map ready to be merged into the deploy.
 *
 * Used when the `bundle_uploads` setting is on so the CF Pages deploy is fully
 * self-contained (uploads served from the static host, not the dashboard origin).
 */
class SFORGE_Assets_Bundler {

	/**
	 * @param array $uploads  [ rel_path => origin_url ] — typically from SFORGE_Renderer::get_collected_uploads()
	 * @return array          [ rel_path => binary ]    — only successfully fetched entries.
	 */
	public function fetch( array $uploads ) {
		$out = [];
		if ( empty( $uploads ) ) {
			return $out;
		}
		$total = count( $uploads );
		SFORGE_Logger::log( sprintf( 'Bundling %d /wp-content/uploads/ file(s) into deploy...', $total ) );

		$ok = 0;
		$fail = 0;
		$bytes = 0;
		$step = max( 1, (int) round( $total / 10 ) );
		$i = 0;

		foreach ( $uploads as $rel => $url ) {
			$i++;
			$body = $this->fetch_one( $url );
			if ( $body === null ) {
				$fail++;
				SFORGE_Logger::log( 'Asset fetch fail: ' . esc_url( $url ), 'warn' );
				continue;
			}
			$out[ $rel ] = $body;
			$ok++;
			$bytes += strlen( $body );

			if ( ( $i % $step ) === 0 ) {
				SFORGE_Logger::log( sprintf( 'Asset bundle progress: %d / %d', $i, $total ) );
			}
		}

		SFORGE_Logger::log( sprintf( 'Asset bundle done: %d ok, %d failed, %s MB total.', $ok, $fail, round( $bytes / 1048576, 2 ) ) );
		return $out;
	}

	protected function fetch_one( $url ) {
		$fetch_url     = $url;
		$extra_headers = [];
		if ( class_exists( 'SFORGE_Renderer' ) ) {
			list( $fetch_url, $extra_headers ) = SFORGE_Renderer::localize_request( $url );
		}
		$resp = wp_remote_get( $fetch_url, [
			'timeout'     => 30,
			'sslverify'   => empty( $extra_headers ) ? apply_filters( 'sforge_sslverify', true ) : false,
			'redirection' => 5,
			'user-agent'  => 'StaticForge/' . SFORGE_VERSION,
			'headers'     => array_merge( [ 'X-SFORGE-Asset' => '1' ], $extra_headers ),
		] );
		if ( is_wp_error( $resp ) ) {
			return null;
		}
		if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $resp );
		if ( $body === '' ) {
			return null;
		}
		return $body;
	}
}
