<?php
/**
 * Server-side render for the Terminal block (required for imported post content).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode\Blocks;

use ForWP\AdvancedCode\Terminal_Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs terminal shell markup on the frontend when blocks are inserted via import/seed.
 */
class Terminal_Block_Render {

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved inner content (unused).
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render( array $attributes, string $content, \WP_Block $block ): string {
		unset( $content, $block );

		$profile = isset( $attributes['profile'] ) ? (string) $attributes['profile'] : 'wp-cli';
		if ( '' === $profile ) {
			$profile = 'embedded';
		}

		$welcome = isset( $attributes['welcomeMessage'] ) && is_string( $attributes['welcomeMessage'] )
			? $attributes['welcomeMessage']
			: __( 'Interactive Terminal — type "help" for available commands', '4wp-advanced-code' );

		$enable_fullview = ! isset( $attributes['enableFullView'] ) || false !== $attributes['enableFullView'];
		$enable_sticky   = ! isset( $attributes['enableSticky'] ) || false !== $attributes['enableSticky'];
		$sticky_side     = isset( $attributes['stickySide'] ) && 'left' === $attributes['stickySide'] ? 'left' : 'right';

		$rest_profile = ( 'embedded' === $profile ) ? 'wp-cli' : $profile;
		$rest_url     = Terminal_Rest::get_profile_rest_url( $rest_profile );
		$config_json  = wp_json_encode(
			array(
				'restUrl' => $rest_url,
				'profile' => $profile,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$title = ( 'embedded' === $profile )
			? __( 'Practice challenge', '4wp-advanced-code' )
			: $rest_profile . ' — bash';

		$is_challenge = 'embedded' === $profile;

		ob_start();
		?>
		<div
			class="wp-block-forwp-advanced-code-terminal"
			data-enable-fullview="<?php echo $enable_fullview ? '1' : '0'; ?>"
			data-enable-sticky="<?php echo $enable_sticky ? '1' : '0'; ?>"
			data-sticky-side="<?php echo esc_attr( $sticky_side ); ?>"
		>
			<div
				class="forwp-ac-terminal forwp-ac-terminal--booting<?php echo $is_challenge ? ' forwp-ac-terminal--challenge' : ''; ?>"
				data-forwp-config="<?php echo esc_attr( (string) $config_json ); ?>"
				data-profile="<?php echo esc_attr( $profile ); ?>"
				data-welcome="<?php echo esc_attr( $welcome ); ?>"
				data-enable-fullview="<?php echo $enable_fullview ? '1' : '0'; ?>"
				data-enable-sticky="<?php echo $enable_sticky ? '1' : '0'; ?>"
				data-sticky-side="<?php echo esc_attr( $sticky_side ); ?>"
			>
				<div class="forwp-ac-terminal__shell">
					<div class="forwp-ac-terminal__header">
						<span class="forwp-ac-terminal__dot forwp-ac-terminal__dot--red"></span>
						<span class="forwp-ac-terminal__dot forwp-ac-terminal__dot--yellow"></span>
						<span class="forwp-ac-terminal__dot forwp-ac-terminal__dot--green"></span>
						<span class="forwp-ac-terminal__title"><?php echo esc_html( $title ); ?></span>
					</div>
					<div class="forwp-ac-terminal__body">
						<div class="forwp-ac-terminal__main">
							<div class="forwp-ac-terminal__output" aria-live="polite"></div>
							<form class="forwp-ac-terminal__form">
								<label class="forwp-ac-terminal__input-row">
									<span class="forwp-ac-terminal__prompt">$</span>
									<input type="text" class="forwp-ac-terminal__input" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" aria-label="<?php esc_attr_e( 'Terminal command', '4wp-advanced-code' ); ?>" />
								</label>
							</form>
						</div>
						<aside class="forwp-ac-terminal__sidebar"<?php echo $is_challenge ? ' hidden' : ''; ?>>
							<h3 class="forwp-ac-terminal__sidebar-title"><?php esc_html_e( 'Command Categories', '4wp-advanced-code' ); ?></h3>
							<div class="forwp-ac-terminal__sidebar-list"></div>
						</aside>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
