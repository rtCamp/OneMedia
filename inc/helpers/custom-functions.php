<?php
/**
 * Custom functions for the plugin.
 *
 * @package OneMedia
 */

use OneMedia\Utils;

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
function onemedia_replace_image_across_all_post_types( int $attachment_id, string $attachment_permalink, string $alt_text = '', string $caption = '' ): void {
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
	$patterns = array(
		// Block editor pattern (more flexible).
		'/<!-- wp:image \{[^}]*?"id":\s*' . $attachment_id . '[^}]*?\} -->\s*<figure[^>]*>.*?<img[^>]+wp-image-' . $attachment_id . '[^>]*>.*?(?:<figcaption[^>]*>.*?<\/figcaption>)?\s*<\/figure>\s*<!-- \/wp:image -->/is',
		// Classic editor / HTML pattern.
		'/<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>\s*<img[^>]+wp-image-' . $attachment_id . '[^>]*>.*?(?:<figcaption[^>]*>.*?<\/figcaption>)?\s*<\/figure>/is',
		// Simple img tag with wp-image class.
		'/<img[^>]+wp-image-' . $attachment_id . '[^>]*>/is',
	);

	foreach ( $posts_with_image as $post ) {
		$original_content = $post->post_content;
		$updated_content  = $original_content;

		// Try each pattern.
		foreach ( $patterns as $pattern ) {
			$updated_content = preg_replace_callback(
				$pattern,
				function ( $matches ) use ( $attachment_permalink, $alt_text, $caption, $attachment_id ) {
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
		if ( $updated_content !== $original_content ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->posts,
				array( 'post_content' => $updated_content ),
				array( 'ID' => $post->ID ),
				array( '%s' ),
				array( '%d' )
			);

			// Clear any caches for this post.
			clean_post_cache( $post->ID );

			// For wp_template posts, also clear template cache.
			if ( 'wp_template' === $post->post_type ) {
				wp_cache_delete( 'theme_template_' . $post->ID, 'themes' );
			}
		}
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
				function ( $matches ) use ( $attachment_permalink, $alt_text, $caption, $attachment_id ) {
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
		if ( $updated_meta !== $meta->meta_value ) {
			update_post_meta( $meta->post_id, $meta->meta_key, $updated_meta );
		}
	}
}

/**
 * Validate Rest API for general request.
 *
 * @return bool True if valid, false otherwise.
 */
function onemedia_validate_rest_api(): bool {
	return onemedia_rest_api_validation( false );
}

/**
 * Validating Rest API request.
 *
 * @param bool $is_health_check Whether the request is for health check or not.
 *
 * @return bool|\WP_REST_Response|\WP_Error True or WP_REST_Response if valid, WP_Error otherwise.
 */
function onemedia_rest_api_validation( bool $is_health_check ): bool|\WP_REST_Response|\WP_Error {
	$success_response = new \WP_REST_Response(
		array(
			'message' => __( 'Health check passed successfully.', 'onemedia' ),
			'success' => true,
		),
		200
	);

	$already_connected_error = new \WP_Error(
		'governing_site_url_already_connected',
		__( 'This Brand Site is already connected to another Governing Site.', 'onemedia' ),
		array(
			'status'  => 500,
			'success' => false,
		)
	);

	// Check X-OneMedia-Token header.
	if ( isset( $_SERVER['HTTP_X_ONEMEDIA_TOKEN'] ) && ! empty( $_SERVER['HTTP_X_ONEMEDIA_TOKEN'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONEMEDIA_TOKEN'] ) );
		// Get the API key from options.
		$api_key = Settings::get_api_key;

		// Check if site type is set or not.
		$is_site_type_set = Utils::is_site_type_set();

		// Governing site url.
		$governing_site_url = Settings::get_parent_site_url();

		$request_origin   = Utils::get_origin_url( $_SERVER );
		$current_site_url = get_site_url();
		$is_token_valid   = hash_equals( $token, $api_key );
		$is_same_domain   = Utils::is_same_domain( $current_site_url, $request_origin );
		$multisite_type   = Utils::get_multisite_type();
		$is_multisite     = 'single' !== $multisite_type;

		// Check if token is valid.
		if ( ! $is_token_valid ) {
			if ( $is_health_check ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'Health check failed. Please ensure the API key is correct.', 'onemedia' ),
					array(
						'status'  => 403,
						'success' => false,
					)
				);
			}
			return false;
		}

		// If site type is not set, allow only same domain request.
		if ( ! $is_site_type_set ) {
			if ( $is_same_domain ) {
				return $is_health_check ? $success_response : true;
			}
			if ( $is_health_check ) {
				return new \WP_Error(
					'site_type_not_set',
					__( 'Health check failed. Please ensure the site type is set.', 'onemedia' ),
					array(
						'status'  => 500,
						'success' => false,
					)
				);
			}
			return false;
		}

		// Check if the request is to governing site.
		if ( Settings::is_governing_site() ) {
			// If request is from governing site (same domain).
			// TODO: Currently there is no request from brand site to governing site. But in future if such requests are added, this check will bypass such requests for subdirectory multisite.
			if ( $is_same_domain && Utils::check_user_permissions() ) {
				return $is_health_check ? $success_response : true;
			}
		}

		// Check if the request is to a brand site.
		if ( Settings::is_consumer_site() ) {

			// For all requests of non-multisite setups & subdomain multisite setups.
			if ( ! $is_multisite || ( 'subdomain' === $multisite_type ) ) {

				// If request is from brand site (same domain).
				if ( $is_same_domain ) {
					return $is_health_check ? $success_response : true;
				} elseif ( ! $is_same_domain && ! empty( $governing_site_url ) ) { // If request is from governing site to brand site (different domain).

					// If governing site URL is set on this brand site, check if current request is from the same governing site.
					if ( Utils::is_same_domain( $governing_site_url, $request_origin ) ) {
						return $is_health_check ? $success_response : true;
					} else {
						return $is_health_check ? $already_connected_error : false;
					}
				} elseif ( ! $is_same_domain && empty( $governing_site_url ) && $is_health_check ) { // If governing site URL is not set on this brand site, this brand site is not currently connected to any governing site.

					// Save the governing site URL on this brand site.
					$update_result = Utils::set_governing_site_url( $request_origin );
					return is_wp_error( $update_result ) ? $update_result : $success_response;
				}
			} elseif ( $is_multisite && ( 'subdirectory' === $multisite_type ) && $is_same_domain ) { // For all requests of subdirectory multisite setups (the domains will be same for all requests).

				// TODO: With current implementation, a subdirectory multisite brand site can only be connected to same domain's governing site. To allow cross-domain governing site connection, we need to remove $is_same_domain from the above check and update the below logic accordingly.
				// If governing site URL is set on this brand site, check if current request is from the same domain (ideally it should always be true for subdirectory multisite).
				if ( ! empty( $governing_site_url ) ) {

					if ( Utils::is_same_domain( $governing_site_url, $request_origin ) ) {
						return $is_health_check ? $success_response : true;
					} else {
						return $is_health_check ? $already_connected_error : false;
					}
				} else { // If governing site URL is not set on this brand site, this brand site is not currently connected to any governing site.

					// Save the governing site URL on this brand site.
					$update_result = Utils::set_governing_site_url( $request_origin );

					if ( is_wp_error( $update_result ) ) {
						return $is_health_check ? $update_result : false;
					}

					return $is_health_check ? $success_response : true;
				}
			}
		}
	}
	if ( $is_health_check ) {
		return new \WP_Error(
			'site_not_accessible',
			__( 'Health check failed. Please ensure the site is accessible.', 'onemedia' ),
			array(
				'status'  => 500,
				'success' => false,
			)
		);
	}
	return false;
}

/**
 * Return onemedia template content.
 *
 * @param string $slug Template path.
 * @param array  $vars Template variables.
 *
 * @return string Template markup.
 */
function onemedia_get_template_content( string $slug, array $vars = array() ): string {
	ob_start();

	$template = sprintf( '%s.php', $slug );

	$located_template = '';
	if ( file_exists( ONEMEDIA_PLUGIN_TEMPLATES_PATH . '/' . $template ) ) {
		$located_template = ONEMEDIA_PLUGIN_TEMPLATES_PATH . '/' . $template;
	}

	if ( '' === $located_template ) {
		return '';
	}

	// Ensure vars is an array.
	if ( ! is_array( $vars ) ) {
		$vars = array();
	}

	include $located_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

	$markup = ob_get_clean();

	return $markup;
}
