<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the full list of URLs to export for a site rebuild.
 */
class SFORGE_Crawler {

	public function build_url_list() {
		$urls       = [];
		$post_types = (array) SFORGE_Settings::get( 'post_types', [] );

		if ( SFORGE_Settings::get( 'include_homepage' ) ) {
			$urls[] = home_url( '/' );
			$blog_id = (int) get_option( 'page_for_posts' );
			if ( $blog_id ) {
				$urls[] = get_permalink( $blog_id );
			}
		}

		if ( ! empty( $post_types ) ) {
			$q = new WP_Query( [
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );
			foreach ( $q->posts as $pid ) {
				$p = get_permalink( $pid );
				if ( $p ) {
					$urls[] = $p;
				}
			}

			foreach ( $post_types as $pt ) {
				$archive = get_post_type_archive_link( $pt );
				if ( $archive ) {
					$urls[] = $archive;
				}
			}
		}

		if ( SFORGE_Settings::get( 'include_taxonomies' ) ) {
			$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
			foreach ( $taxonomies as $tax ) {
				$terms = get_terms( [
					'taxonomy'   => $tax,
					'hide_empty' => true,
				] );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) && $link ) {
						$urls[] = $link;
					}
				}
			}
		}

		if ( SFORGE_Settings::get( 'include_authors' ) ) {
			$authors = get_users( [
				'has_published_posts' => true,
				'fields'              => [ 'ID' ],
			] );
			foreach ( $authors as $u ) {
				$link = get_author_posts_url( $u->ID );
				if ( $link ) {
					$urls[] = $link;
				}
			}
		}

		$urls = apply_filters( 'sforge_url_list', $urls );
		return array_values( array_unique( array_filter( $urls ) ) );
	}
}
