<?php
/**
 * Static utility functions.
 *
 * @package OneMedia
 */

declare( strict_types = 1 );

namespace OneMedia;

/**
 * Class - Utils
 */
final class Utils {
	/**
	 * The templates dir.
	 */
	private const ONEMEDIA_PLUGIN_TEMPLATES_PATH = ONEMEDIA_DIR . '/templates';

	/**
	 * Return onemedia template content.
	 *
	 * @param string $slug Template path.
	 * @param array  $vars Template variables.
	 *
	 * @return string Template markup.
	 */
	public static function get_template_content( string $slug, array $vars = [] ): string {
		ob_start();

		$template = sprintf( '%s.php', $slug );

		$located_template = '';
		if ( file_exists( self::ONEMEDIA_PLUGIN_TEMPLATES_PATH . '/' . $template ) ) {
			$located_template = self::ONEMEDIA_PLUGIN_TEMPLATES_PATH . '/' . $template;
		}

		if ( '' === $located_template ) {
			return '';
		}

		$vars = $vars;

		include $located_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		return ob_get_clean() ?: '';
	}

	/**
	 * Wrapper for term_exists to support WPVIP.
	 *
	 * @param string|int $term      Term ID or slug to check.
	 * @param string     $taxonomy  Optional. Taxonomy name. Default empty.
	 * @param int        $parent_id Optional. Parent term ID. Default 0.
	 *
	 * @return ($term is 0 ? 0 : ($term is '' ? null : ($taxonomy is '' ? string|null : array{term_id: string, term_taxonomy_id: string}|null)))
	 */
	public static function term_exists( $term, $taxonomy = '', $parent_id = 0 ) {
		if ( function_exists( 'wpcom_vip_term_exists' ) ) {
			return wpcom_vip_term_exists( $term, $taxonomy, $parent_id );
		}

		return term_exists( $term, $taxonomy, $parent_id );
	}
}
