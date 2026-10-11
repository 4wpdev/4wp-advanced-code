<?php
/**
 * Extends core/code block output on the frontend.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Core block wrapper functionality.
 */
class Block_Wrapper {

	/**
	 * Initialize wrapper.
	 */
	public static function init(): void {
		add_filter( 'render_block', array( self::class, 'render_advanced_code' ), 10, 2 );
		add_filter( 'register_block_type_args', array( self::class, 'register_core_code_attributes' ), 10, 2 );
	}

	/**
	 * Register language attrs on core/code (server-side) so saved overrides reach render_block.
	 *
	 * @param array<string, mixed> $args       Block type args.
	 * @param string               $block_type Block name.
	 * @return array<string, mixed>
	 */
	public static function register_core_code_attributes( array $args, string $block_type ): array {
		if ( 'core/code' !== $block_type ) {
			return $args;
		}

		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		$args['attributes']['language'] = array(
			'type'    => 'string',
			'default' => 'auto',
		);
		$args['attributes']['theme'] = array(
			'type'    => 'string',
			'default' => '',
		);
		$args['attributes']['showCopy'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
		$args['attributes']['showShare'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
		$args['attributes']['advancedEnabled'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
		$args['attributes']['note'] = array(
			'type'    => 'string',
			'default' => '',
		);

		return $args;
	}

	/**
	 * Enhance core/code block markup on the frontend.
	 *
	 * @param string               $block_content Block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string
	 */
	public static function render_advanced_code( string $block_content, array $block ): string {
		if ( ( $block['blockName'] ?? '' ) !== 'core/code' ) {
			return $block_content;
		}

		if ( self::should_skip_wrapper( $block ) ) {
			return $block_content;
		}

		if ( ! self::is_advanced_enabled( $block ) ) {
			return $block_content;
		}

		$code = self::extract_code_content( $block_content );

		return self::build_enhanced_block( $code, $block );
	}

	/**
	 * Pages/sections that ship their own terminal code styling.
	 *
	 * @param array<string, mixed> $block Block data.
	 */
	private static function should_skip_wrapper( array $block ): bool {
		$class = (string) ( $block['attrs']['className'] ?? '' );

		if ( str_contains( $class, 'ddd-panel__code' ) || str_contains( $class, 'forwp-plain-code' ) ) {
			return true;
		}

		// Functions hub uses dark terminal code blocks (no Copy/Share chrome).
		if ( is_page( 'functions' ) ) {
			return true;
		}

		$template = (string) get_page_template_slug( get_queried_object_id() );
		if ( in_array( $template, array( 'page-functions', 'templates/page-functions.html' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether advanced features apply to this block.
	 *
	 * @param array<string, mixed> $block Block data.
	 */
	private static function is_advanced_enabled( array $block ): bool {
		if ( ! Modules::is_code_enabled() ) {
			return false;
		}

		$block_enabled = $block['attrs']['advancedEnabled'] ?? true;

		return $block_enabled;
	}

	/**
	 * Extract code text from saved core/code HTML.
	 */
	private static function extract_code_content( string $block_content ): string {
		if ( preg_match( '/<code[^>]*>(.*?)<\/code>/s', $block_content, $matches ) ) {
			return html_entity_decode( wp_strip_all_tags( $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return '';
	}

	/**
	 * Build enhanced markup shared with the custom block render template.
	 *
	 * @param string               $code  Code sample text.
	 * @param array<string, mixed> $block Block data.
	 */
	public static function build_enhanced_block( string $code, array $block ): string {
		$attrs = $block['attrs'] ?? array();

		$language = $attrs['language'] ?? 'auto';
		if ( 'auto' === $language ) {
			$language = Seo_Handler::detect_language( $code );
		}

		$theme     = $attrs['theme'] ?? get_option( 'forwp_advanced_code_theme', 'light' );
		$show_copy = $attrs['showCopy'] ?? true;
		$show_share = $attrs['showShare'] ?? true;
		$note      = $attrs['note'] ?? '';

		$block_id = 'forwp-code-' . wp_generate_uuid4();

		ob_start();
		?>
		<?php
		$lang_attr  = strtolower( (string) $language );
		$lang_label = 'plaintext' === $lang_attr ? 'TEXT' : strtoupper( $lang_attr );
		$code_class = ( '' !== $lang_attr && 'auto' !== $lang_attr && 'plaintext' !== $lang_attr )
			? 'language-' . $lang_attr
			: '';
		?>
		<div class="forwp-advanced-code wp-block-code-advanced" data-theme="<?php echo esc_attr( $theme ); ?>" data-language="<?php echo esc_attr( $lang_attr ); ?>">
			<?php if ( $note ) : ?>
				<div class="forwp-advanced-code__note code-note"><?php echo wp_kses_post( $note ); ?></div>
			<?php endif; ?>

			<div class="forwp-advanced-code__container code-container">
				<div class="forwp-advanced-code__header code-header">
					<span class="forwp-advanced-code__language code-language"><?php echo esc_html( $lang_label ); ?></span>
					<div class="forwp-advanced-code__actions code-actions">
						<?php if ( $show_copy ) : ?>
							<button type="button" class="forwp-advanced-code__copy code-copy-btn" data-code="<?php echo esc_attr( $code ); ?>" aria-label="<?php esc_attr_e( 'Copy code', '4wp-advanced-code' ); ?>">
								<?php esc_html_e( 'Copy', '4wp-advanced-code' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( $show_share ) : ?>
							<button type="button" class="forwp-advanced-code__share code-share-btn" data-url="<?php echo esc_url( get_permalink() . '#' . $block_id ); ?>" aria-label="<?php esc_attr_e( 'Share code snippet link', '4wp-advanced-code' ); ?>">
								<?php esc_html_e( 'Share', '4wp-advanced-code' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<pre id="<?php echo esc_attr( $block_id ); ?>"><code class="<?php echo esc_attr( $code_class ); ?>"><?php echo esc_html( $code ); ?></code></pre>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
