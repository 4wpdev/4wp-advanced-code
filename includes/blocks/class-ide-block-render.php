<?php
/**
 * Server-side render for the IDE wizard block.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode\Blocks;

use ForWP\AdvancedCode\Ide_Catalog;
use ForWP\AdvancedCode\Ide_Rest;
use ForWP\AdvancedCode\Ide_Seo_Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs the VS Code–style lesson shell. view.js hydrates the tour.
 */
class Ide_Block_Render {

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved inner content (unused).
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render( array $attributes, string $content, \WP_Block $block ): string {
		unset( $content, $block );

		$type = Ide_Catalog::sanitize_type( isset( $attributes['hookType'] ) ? (string) $attributes['hookType'] : 'action' );
		$slug = Ide_Catalog::sanitize_slug( isset( $attributes['hookSlug'] ) ? (string) $attributes['hookSlug'] : 'wp_head' );
		if ( '' === $slug ) {
			$slug = 'wp_head';
		}

		$tour_mode = isset( $attributes['tourMode'] ) && in_array( $attributes['tourMode'], array( 'group', 'custom' ), true )
			? (string) $attributes['tourMode']
			: 'single';
		$selected  = Ide_Catalog::sanitize_hook_refs( $attributes['selectedHooks'] ?? array() );
		if ( ! empty( $selected ) ) {
			$tour_mode = 'custom';
		}

		$rest_url  = 'custom' === $tour_mode
			? Ide_Rest::get_set_rest_url( $selected )
			: ( 'group' === $tour_mode
				? Ide_Rest::get_group_rest_url( $type, $slug )
				: Ide_Rest::get_wizard_rest_url( $type, $slug ) );
		$config_json = wp_json_encode(
			array(
				'restUrl'  => $rest_url,
				'hookType' => $type,
				'hookSlug' => $slug,
				'tourMode' => $tour_mode,
				'hooks'    => $selected,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$bootstrap = Ide_Catalog::ssr_bootstrap( $type, $slug, $tour_mode, $selected );
		if ( is_array( $bootstrap ) ) {
			Ide_Seo_Handler::register( $bootstrap );
		}

		$title           = $slug . ' — functions.php';
		$root_class      = 'forwp-ac-ide';
		$root_attrs      = '';
		$kicker          = 'custom' === $tour_mode
			? esc_html__( 'Hook quiz', '4wp-advanced-code' )
			: ( 'group' === $tour_mode
				? esc_html__( 'Group tour', '4wp-advanced-code' )
				: esc_html__( 'Hook wizard', '4wp-advanced-code' ) );
		$progress        = '';
		$step_title      = '';
		$tour_body       = '';
		$starter         = '';
		$status_progress = '';

		if ( is_array( $bootstrap ) ) {
			$root_class .= '';
			$root_attrs  = ' data-forwp-ssr="1"';

			$wizard = $bootstrap['wizard'];
			$step   = $bootstrap['step'];
			$title  = (string) ( $wizard['file'] ?? 'functions.php' ) . ' — ' . (string) ( $wizard['slug'] ?? $slug );

			$kicker = 'custom' === $bootstrap['tour_mode']
				? esc_html__( 'Hook quiz', '4wp-advanced-code' )
				: ( 'group' === $bootstrap['tour_mode']
					? esc_html__( 'Group tour', '4wp-advanced-code' )
					: esc_html__( 'Hook wizard', '4wp-advanced-code' ) );

			$progress = self::format_progress(
				$wizard,
				0,
				(int) $bootstrap['wizards_count'],
				1,
				(int) $bootstrap['total_steps']
			);

			$step_title      = (string) ( $step['title'] ?? '' );
			$tour_body       = self::render_ssr_tour_body(
				$step,
				(string) ( $bootstrap['signature'] ?? '' ),
				(string) ( $bootstrap['example_code'] ?? '' )
			);
			$starter         = (string) ( $bootstrap['starter'] ?? '' );
			$status_progress = $progress;
		} else {
			$root_class .= ' forwp-ac-ide--booting';
		}

		ob_start();
		?>
		<div class="wp-block-forwp-advanced-code-ide" id="forwp-ide-wizard">
			<div
				class="<?php echo esc_attr( $root_class ); ?>"
				data-forwp-config="<?php echo esc_attr( (string) $config_json ); ?>"
				data-hook-type="<?php echo esc_attr( $type ); ?>"
				data-hook-slug="<?php echo esc_attr( $slug ); ?>"
				data-tour-mode="<?php echo esc_attr( $tour_mode ); ?>"
				data-enable-fullview="1"<?php echo $root_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			>
				<div class="forwp-ac-ide__shell">
					<div class="forwp-ac-ide__titlebar">
						<span class="forwp-ac-ide__dot forwp-ac-ide__dot--red"></span>
						<span class="forwp-ac-ide__dot forwp-ac-ide__dot--yellow"></span>
						<span class="forwp-ac-ide__dot forwp-ac-ide__dot--green"></span>
						<span class="forwp-ac-ide__title"><?php echo esc_html( $title ); ?></span>
					</div>
					<div class="forwp-ac-ide__layout">
						<aside class="forwp-ac-ide__explorer">
							<div class="forwp-ac-ide__explorer-label"><?php esc_html_e( 'Explorer', '4wp-advanced-code' ); ?></div>
							<button type="button" class="forwp-ac-ide__file is-active" tabindex="-1">functions.php</button>
						</aside>
						<div class="forwp-ac-ide__main">
							<div class="forwp-ac-ide__tabs">
								<span class="forwp-ac-ide__tab is-active">functions.php</span>
							</div>
							<div class="forwp-ac-ide__editor">
								<div class="forwp-ac-ide__gutter" aria-hidden="true"></div>
								<div class="forwp-ac-ide__code-wrap">
									<pre class="forwp-ac-ide__highlight" aria-hidden="true"><code class="language-php"></code></pre>
									<textarea
										class="forwp-ac-ide__code"
										spellcheck="false"
										autocomplete="off"
										autocorrect="off"
										autocapitalize="off"
										aria-label="<?php esc_attr_e( 'PHP practice editor', '4wp-advanced-code' ); ?>"
									><?php echo esc_textarea( $starter ); ?></textarea>
								</div>
							</div>
						</div>
						<aside class="forwp-ac-ide__tour" aria-live="polite">
							<div class="forwp-ac-ide__tour-kicker"><?php echo esc_html( $kicker ); ?></div>
							<div class="forwp-ac-ide__tour-progress"><?php echo esc_html( $progress ); ?></div>
							<h3 class="forwp-ac-ide__tour-title"><?php echo esc_html( $step_title ); ?></h3>
							<div class="forwp-ac-ide__tour-body"><?php echo $tour_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div class="forwp-ac-ide__tour-feedback" hidden></div>
							<div class="forwp-ac-ide__tour-actions">
								<button type="button" class="forwp-ac-ide__btn forwp-ac-ide__btn--ghost" data-ide-back disabled><?php esc_html_e( 'Back', '4wp-advanced-code' ); ?></button>
								<button type="button" class="forwp-ac-ide__btn" data-ide-check hidden><?php esc_html_e( 'Check', '4wp-advanced-code' ); ?></button>
								<button type="button" class="forwp-ac-ide__btn forwp-ac-ide__btn--primary" data-ide-next><?php esc_html_e( 'Next', '4wp-advanced-code' ); ?></button>
							</div>
						</aside>
					</div>
					<div class="forwp-ac-ide__statusbar">
						<span data-ide-status-lang>PHP</span>
						<span data-ide-status-step><?php echo esc_html( $status_progress ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $wizard         Wizard payload.
	 * @param int                  $wizard_index   Zero-based wizard index.
	 * @param int                  $wizards_length Total wizards in group.
	 * @param int                  $current        One-based step index.
	 * @param int                  $total          Total steps in wizard.
	 */
	private static function format_progress( array $wizard, int $wizard_index, int $wizards_length, int $current, int $total ): string {
		$step = sprintf(
			/* translators: 1: current step number, 2: total steps */
			__( 'Step %1$s of %2$s', '4wp-advanced-code' ),
			(string) $current,
			(string) $total
		);

		if ( $wizards_length <= 1 ) {
			return $step;
		}

		$slug = (string) ( $wizard['slug'] ?? $wizard['name'] ?? '' );
		$hook = sprintf(
			/* translators: 1: current hook number, 2: total hooks */
			__( 'Hook %1$s of %2$s', '4wp-advanced-code' ),
			(string) ( $wizard_index + 1 ),
			(string) $wizards_length
		);

		return trim( $slug . ' · ' . $hook . ' · ' . $step );
	}

	/**
	 * @param array<string, mixed> $step         First tour step.
	 * @param string               $signature    Hook signature when available.
	 * @param string               $example_code First example snippet.
	 */
	private static function render_ssr_tour_body( array $step, string $signature, string $example_code ): string {
		$html = '';

		if ( ! empty( $step['tagline'] ) ) {
			$html .= '<p class="forwp-ac-ide__tagline">' . esc_html( (string) $step['tagline'] ) . '</p>';
		}

		if ( ! empty( $step['body'] ) ) {
			$html .= '<p>' . esc_html( (string) $step['body'] ) . '</p>';
		}

		if ( ! empty( $step['meta'] ) && is_array( $step['meta'] ) ) {
			$html .= self::render_meta_chips( $step['meta'] );
		}

		if ( '' !== $signature ) {
			$html .= '<pre class="forwp-ac-ide__pre"><code>' . esc_html( $signature ) . '</code></pre>';
		}

		if ( '' !== $example_code ) {
			$html .= '<pre class="forwp-ac-ide__pre forwp-ac-ide__pre--example"><code>' . esc_html( $example_code ) . '</code></pre>';
		}

		return $html;
	}

	/**
	 * @param array<string, mixed> $meta Step meta chips.
	 */
	private static function render_meta_chips( array $meta ): string {
		$chips = array();
		foreach ( array( 'type', 'phase', 'since', 'source' ) as $key ) {
			if ( empty( $meta[ $key ] ) ) {
				continue;
			}
			$chips[] = '<span class="forwp-ac-ide__chip"><em>' . esc_html( $key ) . '</em> ' . esc_html( (string) $meta[ $key ] ) . '</span>';
		}

		if ( empty( $chips ) ) {
			return '';
		}

		return '<div class="forwp-ac-ide__chips">' . implode( '', $chips ) . '</div>';
	}
}
