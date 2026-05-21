<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds fetchpriority="high", loading="eager", decoding="async" to the post's
 * featured image so it's prioritised by the browser as the LCP candidate.
 *
 * Triggers cleanly on any theme that calls the_post_thumbnail() /
 * get_the_post_thumbnail() because both fire begin_fetch_post_thumbnail_html
 * and end_fetch_post_thumbnail_html actions around their image rendering.
 */
class SFORGE_Featured_Image {

	protected $in_thumbnail = false;

	public function __construct() {
		if ( ! (int) SFORGE_Settings::get( 'featured_image_priority', 1 ) ) {
			return;
		}
		add_action( 'begin_fetch_post_thumbnail_html', [ $this, 'begin' ] );
		add_action( 'end_fetch_post_thumbnail_html',   [ $this, 'end' ] );
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'attrs' ], 99, 1 );
	}

	public function begin() {
		$this->in_thumbnail = true;
	}

	public function end() {
		$this->in_thumbnail = false;
	}

	public function attrs( $attrs ) {
		if ( ! $this->in_thumbnail ) {
			return $attrs;
		}
		$attrs['fetchpriority'] = 'high';
		$attrs['loading']       = 'eager';
		if ( empty( $attrs['decoding'] ) ) {
			$attrs['decoding']  = 'async';
		}
		return $attrs;
	}
}
