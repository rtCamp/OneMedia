<?php
/**
 * Admin lifecycle handlers for Media Sharing.
 *
 * Manages the admin-side lifecycle of synced media, including pre-update
 * guards, deletion cleanup, and propagation of attachment changes to Brand Sites.
 *
 * @package OneMedia\Modules\Post_Types;
 */

namespace OneMedia\Modules\MediaSharing;

/**
 * Class Admin
 */
class MediaReplacement {

	/**
	 * Replace image across all post types.
	 *
	 * @param int    $attachment_id        The attachment ID.
	 * @param string $attachment_permalink The permalink of the attachment.
	 * @param string $alt_text             The alt text for the image.
	 * @param string $caption              The caption for the image.
	 *
	 * @return void
	 */
	public static function replace_image_across_all_post_types( int $attachment_id, string $attachment_permalink, string $alt_text = '', string $caption = '' ): void {
		global $wpdb;

		// First, get all posts that contain the wp-image-{id} class.
		$cache_key        = 'onemedia_posts_with_image_' . $attachment_id;
		$posts_with_image = wp_cache_get( $cache_key, 'onemedia' );
		if ( false === $posts_with_image ) {
			$posts_with_image = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT ID, post_content, post_type
				FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND post_status IN ('publish', 'draft', 'private', 'future', 'pending')
				AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')",
					'%wp-image-' . $attachment_id . '%'
				)
			);
			wp_cache_set( $cache_key, $posts_with_image, 'onemedia', 600 ); // Cache for 10 minutes.
		}

		if ( empty( $posts_with_image ) ) {
			return;
		}

		// Updated regex pattern to match both block editor and classic editor images.
		$patterns = [
			// Block editor pattern (more flexible).
			'/<!-- wp:image \{[^}]*?"id":\s*' . $attachment_id . '[^}]*?\} -->\s*<figure[^>]*>.*?<img[^>]+wp-image-' . $attachment_id . '[^>]*>.*?(?:<figcaption[^>]*>.*?<\/figcaption>)?\s*<\/figure>\s*<!-- \/wp:image -->/is',
			// Classic editor / HTML pattern.
			'/<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>\s*<img[^>]+wp-image-' . $attachment_id . '[^>]*>.*?(?:<figcaption[^>]*>.*?<\/figcaption>)?\s*<\/figure>/is',
			// Simple img tag with wp-image class.
			'/<img[^>]+wp-image-' . $attachment_id . '[^>]*>/is',
		];

		foreach ( $posts_with_image as $post ) {
			$original_content = $post->post_content;
			$updated_content  = $original_content;

			// Try each pattern.
			foreach ( $patterns as $pattern ) {
				$updated_content = preg_replace_callback(
					$pattern,
					static function ( $matches ) use ( $attachment_permalink, $alt_text, $caption ) {
						$content = $matches[0];

						// Replace src attribute.
						$content = preg_replace(
							'/src="[^"]+"/',
							'src="' . esc_url( $attachment_permalink ) . '"',
							$content
						);

						// Replace srcset attribute (remove it as it's no longer valid).
						$content = preg_replace( '/\s+srcset="[^"]*"/', '', $content );

						// Replace sizes attribute (remove it as it's no longer valid).
						$content = preg_replace( '/\s+sizes="[^"]*"/', '', $content );

						// Replace alt text.
						if ( ! empty( $alt_text ) ) {
							if ( preg_match( '/alt="[^"]*"/', $content ) ) {
								$content = preg_replace(
									'/alt="[^"]*"/',
									'alt="' . esc_attr( $alt_text ) . '"',
									$content
								);
							} else {
								// Add alt if missing.
								$content = preg_replace(
									'/(<img[^>]+)(\/?>)/',
									'$1 alt="' . esc_attr( $alt_text ) . '"$2',
									$content
								);
							}
						}

						// Handle caption for figure elements.
						if ( ! empty( $caption ) && false !== strpos( $content, '<figure' ) ) {
							if ( preg_match( '/<figcaption[^>]*>.*?<\/figcaption>/s', $content ) ) {
								// Replace existing caption.
								$content = preg_replace(
									'/<figcaption[^>]*>.*?<\/figcaption>/s',
									'<figcaption class="wp-element-caption">' . wp_kses_post( $caption ) . '</figcaption>',
									$content
								);
							} else {
								// Add caption before closing </figure>.
								$content = preg_replace(
									'/<\/figure>/',
									'<figcaption class="wp-element-caption">' . wp_kses_post( $caption ) . '</figcaption></figure>',
									$content
								);
							}
						}

						return $content;
					},
					$updated_content
				);
			}

			// Update the post if content changed.
			if ( $updated_content === $original_content ) {
				continue;
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->posts,
				[ 'post_content' => $updated_content ],
				[ 'ID' => $post->ID ],
				[ '%s' ],
				[ '%d' ]
			);

			// Clear any caches for this post.
			clean_post_cache( $post->ID );

			// For wp_template posts, also clear template cache.
			if ( 'wp_template' !== $post->post_type ) {
				continue;
			}

			wp_cache_delete( 'theme_template_' . $post->ID, 'themes' );
		}

		// Also search and replace in meta fields that might contain image references.
		$meta_cache_key = 'onemedia_meta_results_' . $attachment_id;
		$meta_results   = wp_cache_get( $meta_cache_key, 'onemedia' );
		if ( false === $meta_results ) {
			$meta_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_value LIKE %s",
					'%wp-image-' . $attachment_id . '%'
				)
			);
			wp_cache_set( $meta_cache_key, $meta_results, 'onemedia', 600 ); // Cache for 10 minutes.
		}

		foreach ( $meta_results as $meta ) {
			$updated_meta = $meta->meta_value;

			// Apply the same patterns to meta values.
			foreach ( $patterns as $pattern ) {
				$updated_meta = preg_replace_callback(
					$pattern,
					static function ( $matches ) use ( $attachment_permalink, $alt_text, $caption ) {
						$content = $matches[0];

						// Same replacement logic as above.
						$content = preg_replace( '/src="[^"]+"/', 'src="' . esc_url( $attachment_permalink ) . '"', $content );
						$content = preg_replace( '/\s+srcset="[^"]*"/', '', $content );
						$content = preg_replace( '/\s+sizes="[^"]*"/', '', $content );

						if ( ! empty( $alt_text ) ) {
							if ( preg_match( '/alt="[^"]*"/', $content ) ) {
								$content = preg_replace( '/alt="[^"]*"/', 'alt="' . esc_attr( $alt_text ) . '"', $content );
							} else {
								$content = preg_replace( '/(<img[^>]+)(\/?>)/', '$1 alt="' . esc_attr( $alt_text ) . '"$2', $content );
							}
						}

						if ( ! empty( $caption ) && false !== strpos( $content, '<figure' ) ) {
							if ( preg_match( '/<figcaption[^>]*>.*?<\/figcaption>/s', $content ) ) {
								$content = preg_replace(
									'/<figcaption[^>]*>.*?<\/figcaption>/s',
									'<figcaption class="wp-element-caption">' . wp_kses_post( $caption ) . '</figcaption>',
									$content
								);
							} else {
								$content = preg_replace(
									'/<\/figure>/',
									'<figcaption class="wp-element-caption">' . wp_kses_post( $caption ) . '</figcaption></figure>',
									$content
								);
							}
						}

						return $content;
					},
					$updated_meta
				);
			}

			// Update meta if changed.
			if ( $updated_meta === $meta->meta_value ) {
				continue;
			}

			update_post_meta( $meta->post_id, $meta->meta_key, $updated_meta );
		}
	}
}
