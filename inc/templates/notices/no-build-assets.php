<?php
/**
 * This file contains the markup for onemedia notice when there are no build assets present.
 *
 * @package OneMedia
 */

?>

<div class="notice notice-error ">
	<p>
		<?php
		printf(
			/* translators: %s is the plugin name. */
			esc_html__( 'You are running the %s plugin from the GitHub repository. Please build the assets and install composer dependencies to use the plugin.', 'onemedia' ),
			'<strong>' . esc_html__( 'OneMedia', 'onemedia' ) . '</strong>'
		);
		?>
	</p>
	<p>
		<?php
		printf(
			/* translators: %s is the command to run. */
			esc_html__( 'Run the following commands in the plugin directory: %s', 'onemedia' ),
			'<code>composer install && npm install && npm run build:prod</code>'
		);
		?>
	</p>
	<p>
		<?php
		printf(
			/* translators: %s is the plugin name. */
			esc_html__( 'Please refer to the %s for more information.', 'onemedia' ),
			sprintf(
				/* translators: %1$s is the link to OneMedia GitHub repository, %2$s is the link text. */
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( 'https://github.com/rtCamp/OneMedia' ),
				esc_html__( 'OneMedia GitHub repository', 'onemedia' )
			)
		);
		?>
	</p>
</div>
