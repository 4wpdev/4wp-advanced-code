<?php
/**
 * Server-side render for the Diagram (Mermaid) block.
 *
 * Outputs static HTML (inline SVG). Progressive enhancement is view.js.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode\Blocks;

use ForWP\AdvancedCode\Diagram_Seo;
use ForWP\AdvancedCode\Diagram_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Diagram block frontend markup.
 */
class Diagram_Block_Render {

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved inner content (unused).
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render( array $attributes, string $content, \WP_Block $block ): string {
		unset( $content, $block );

		$code        = isset( $attributes['code'] ) && is_string( $attributes['code'] ) ? $attributes['code'] : '';
		$svg         = isset( $attributes['svg'] ) && is_string( $attributes['svg'] ) ? $attributes['svg'] : '';
		$title       = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? trim( $attributes['title'] ) : '';
		$description = isset( $attributes['description'] ) && is_string( $attributes['description'] ) ? trim( $attributes['description'] ) : '';
		$caption     = isset( $attributes['caption'] ) && is_string( $attributes['caption'] ) ? trim( $attributes['caption'] ) : '';
		$png_id      = isset( $attributes['pngId'] ) ? absint( $attributes['pngId'] ) : 0;
		$theme       = isset( $attributes['theme'] ) && is_string( $attributes['theme'] ) ? sanitize_key( $attributes['theme'] ) : 'auto';

		if ( '' === $code && '' === $svg ) {
			return '';
		}

		if ( '' === $title ) {
			$title = __( 'Diagram', '4wp-advanced-code' );
		}

		$steps = self::parse_steps( $code );
		$theme = self::resolve_theme( $theme );

		Diagram_Seo::register(
			array(
				'title'       => $title,
				'description' => $description,
				'caption'     => $caption !== '' ? $caption : $title,
				'png_id'      => $png_id,
			)
		);

		$classes = array(
			'wp-block-forwp-diagram',
			'forwp-diagram',
			'forwp-diagram--theme-' . $theme,
		);
		if ( ! empty( $steps ) ) {
			$classes[] = 'forwp-diagram--has-steps';
		}

		$img_html = self::image_html( $png_id, $title, $description );
		$need_svg = '' !== $svg && ( '' === $img_html || ! empty( $steps ) );
		$svg_html = $need_svg ? self::decorate_svg( $svg, $title, $description ) : '';

		if ( '' !== $img_html ) {
			$classes[] = 'forwp-diagram--has-image';
		}
		if ( '' !== $svg_html ) {
			$classes[] = 'forwp-diagram--has-svg';
		}

		ob_start();
		?>
		<figure
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php if ( ! empty( $steps ) ) : ?>
				data-forwp-steps="<?php echo esc_attr( implode( ',', $steps ) ); ?>"
			<?php endif; ?>
			<?php if ( $png_id > 0 ) : ?>
				data-forwp-png-id="<?php echo esc_attr( (string) $png_id ); ?>"
			<?php endif; ?>
		>
			<div class="forwp-diagram__canvas">
				<?php
				if ( '' !== $img_html ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
					echo $img_html;
				}
				if ( '' !== $svg_html ) {
					echo '<div class="forwp-diagram__svg">';
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses.
					echo $svg_html;
					echo '</div>';
				}
				if ( '' === $img_html && '' === $svg_html ) {
					?>
					<pre class="forwp-diagram__fallback"><code><?php echo esc_html( $code ); ?></code></pre>
					<?php
				}
				?>
			</div>
			<?php if ( $caption !== '' ) : ?>
				<figcaption class="forwp-diagram__caption forwp-diagram__caption--<?php echo esc_attr( Diagram_Settings::caption_align() ); ?>">
					<?php echo esc_html( $caption ); ?>
				</figcaption>
			<?php endif; ?>
			<?php if ( ! empty( $steps ) ) : ?>
				<div class="forwp-diagram__controls" hidden>
					<button type="button" class="forwp-diagram__btn forwp-diagram__btn--prev" data-forwp-diagram-action="prev">
						<?php esc_html_e( 'Previous', '4wp-advanced-code' ); ?>
					</button>
					<button type="button" class="forwp-diagram__btn forwp-diagram__btn--play" data-forwp-diagram-action="play">
						<?php esc_html_e( 'Play', '4wp-advanced-code' ); ?>
					</button>
					<button type="button" class="forwp-diagram__btn forwp-diagram__btn--next" data-forwp-diagram-action="next">
						<?php esc_html_e( 'Next', '4wp-advanced-code' ); ?>
					</button>
				</div>
			<?php endif; ?>
			<?php if ( Diagram_Settings::is_source_visible() && '' !== $code ) : ?>
				<details class="forwp-diagram__source">
					<summary><?php esc_html_e( 'Diagram code', '4wp-advanced-code' ); ?></summary>
					<pre><code><?php echo esc_html( $code ); ?></code></pre>
				</details>
			<?php endif; ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Always emit a real &lt;img&gt; when the Media Library PNG exists.
	 */
	private static function image_html( int $png_id, string $title, string $description ): string {
		if ( $png_id <= 0 || ! wp_attachment_is_image( $png_id ) ) {
			return '';
		}

		$alt = '' !== $description ? $description : $title;

		$html = wp_get_attachment_image(
			$png_id,
			'full',
			false,
			array(
				'class'    => 'forwp-diagram__image',
				'alt'      => $alt,
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);

		return is_string( $html ) ? $html : '';
	}

	/**
	 * @param string $code Mermaid source.
	 * @return list<string>
	 */
	public static function parse_steps( string $code ): array {
		if ( ! preg_match( '/%%\s*steps:\s*([^\n\r]+)/i', $code, $match ) ) {
			return array();
		}

		$parts = preg_split( '/\s*,\s*/', trim( $match[1] ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$steps = array();
		foreach ( $parts as $part ) {
			$part = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $part );
			if ( is_string( $part ) && '' !== $part ) {
				$steps[] = $part;
			}
		}

		return array_values( array_unique( $steps ) );
	}

	/**
	 * Map block theme to light/dark using plugin setting when auto.
	 */
	private static function resolve_theme( string $theme ): string {
		if ( in_array( $theme, array( 'light', 'dark' ), true ) ) {
			return $theme;
		}

		$plugin = get_option( 'forwp_advanced_code_theme', 'light' );

		return in_array( $plugin, array( 'dark', 'terminal' ), true ) ? 'dark' : 'light';
	}

	/**
	 * Inject title/desc into SVG and allowlist tags.
	 */
	private static function decorate_svg( string $svg, string $title, string $description ): string {
		$svg = trim( $svg );
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return '';
		}

		$svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg ) ?? $svg;
		$svg = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $svg ) ?? $svg;

		if ( ! preg_match( '/\srole=/i', $svg ) ) {
			$svg = preg_replace( '/<svg\b/i', '<svg role="img"', $svg, 1 ) ?? $svg;
		}

		$title_xml = '<title>' . esc_html( $title ) . '</title>';
		$desc_xml  = '' !== $description ? '<desc>' . esc_html( $description ) . '</desc>' : '';

		$svg = preg_replace( '/<title\b[^>]*>.*?<\/title>/is', '', $svg ) ?? $svg;
		$svg = preg_replace( '/<desc\b[^>]*>.*?<\/desc>/is', '', $svg ) ?? $svg;

		$inject = $title_xml . $desc_xml;
		$svg    = preg_replace( '/(<svg\b[^>]*>)/i', '$1' . $inject, $svg, 1 ) ?? $svg;

		$svg = self::normalize_svg_root( $svg );

		return wp_kses( $svg, self::svg_allowed_html() );
	}

	/**
	 * Drop Mermaid width=100% / max-width styles and pin intrinsic size to viewBox
	 * so narrow diagrams are not upscaled to the content column (elephant labels).
	 */
	private static function normalize_svg_root( string $svg ): string {
		return (string) preg_replace_callback(
			'/<svg\b([^>]*)>/i',
			static function ( array $match ): string {
				$attrs = $match[1];
				$attrs = preg_replace( '/\sstyle=(["\']).*?\1/i', '', $attrs ) ?? $attrs;
				$attrs = preg_replace( '/\swidth=(["\'])[^"\']*\1/i', '', $attrs ) ?? $attrs;
				$attrs = preg_replace( '/\sheight=(["\'])[^"\']*\1/i', '', $attrs ) ?? $attrs;

				$view_w = 0.0;
				$view_h = 0.0;
				if ( preg_match( '/\sviewBox=(["\'])([^"\']+)\1/i', $attrs, $vb )
					|| preg_match( '/\sviewbox=(["\'])([^"\']+)\1/i', $attrs, $vb )
				) {
					$parts = preg_split( '/[\s,]+/', trim( $vb[2] ) );
					if ( is_array( $parts ) && count( $parts ) >= 4 ) {
						$view_w = (float) $parts[2];
						$view_h = (float) $parts[3];
					}
				}

				// Natural Mermaid size — CSS max-width:100% may shrink, never inflate.
				if ( $view_w > 0 && $view_h > 0 ) {
					$attrs .= sprintf(
						' width="%s" height="%s"',
						rtrim( rtrim( sprintf( '%.4F', $view_w ), '0' ), '.' ),
						rtrim( rtrim( sprintf( '%.4F', $view_h ), '0' ), '.' )
					);
				}

				if ( ! preg_match( '/\spreserveAspectRatio=/i', $attrs ) ) {
					$attrs .= ' preserveAspectRatio="xMidYMid meet"';
				}

				return '<svg' . $attrs . '>';
			},
			$svg,
			1
		);
	}

	/**
	 * Allowed SVG tags/attrs for Mermaid output.
	 * Attribute keys MUST be lowercase — wp_kses lowercases names before matching.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function svg_allowed_html(): array {
		$common = array(
			'id'                   => true,
			'class'                => true,
			'style'                => true,
			'fill'                 => true,
			'stroke'               => true,
			'stroke-width'         => true,
			'stroke-dasharray'     => true,
			'stroke-dashoffset'    => true,
			'stroke-linecap'       => true,
			'stroke-linejoin'      => true,
			'opacity'              => true,
			'transform'            => true,
			'clip-path'            => true,
			'marker-end'           => true,
			'marker-start'         => true,
			'marker-mid'           => true,
			'x'                    => true,
			'y'                    => true,
			'dx'                   => true,
			'dy'                   => true,
			'x1'                   => true,
			'y1'                   => true,
			'x2'                   => true,
			'y2'                   => true,
			'cx'                   => true,
			'cy'                   => true,
			'r'                    => true,
			'rx'                   => true,
			'ry'                   => true,
			'width'                => true,
			'height'               => true,
			'd'                    => true,
			'points'               => true,
			'viewbox'              => true,
			'preserveaspectratio'  => true,
			'xmlns'                => true,
			'xmlns:xlink'          => true,
			'role'                 => true,
			'aria-hidden'          => true,
			'aria-label'           => true,
			'aria-roledescription' => true,
			'data-id'              => true,
			'data-node'            => true,
			'font-family'          => true,
			'font-size'            => true,
			'font-weight'          => true,
			'font-style'           => true,
			'letter-spacing'       => true,
			'text-anchor'          => true,
			'dominant-baseline'    => true,
			'alignment-baseline'   => true,
			'baseline-shift'       => true,
			'xml:space'            => true,
			'markerwidth'          => true,
			'markerheight'         => true,
			'refx'                 => true,
			'refy'                 => true,
			'orient'               => true,
			'markerunits'          => true,
			'offset'               => true,
			'stop-color'           => true,
			'stop-opacity'         => true,
			'gradientunits'        => true,
			'gradienttransform'    => true,
			'patternunits'         => true,
			'fx'                   => true,
			'fy'                   => true,
			'href'                 => true,
			'xlink:href'           => true,
		);

		return array(
			'svg'            => $common + array( 'version' => true ),
			'g'              => $common,
			'path'           => $common,
			'rect'           => $common,
			'circle'         => $common,
			'ellipse'        => $common,
			'line'           => $common,
			'polyline'       => $common,
			'polygon'        => $common,
			'text'           => $common,
			'tspan'          => $common,
			'defs'           => $common,
			'marker'         => $common,
			'clippath'       => $common,
			'use'            => $common,
			'title'          => array(),
			'desc'           => array(),
			'style'          => array( 'type' => true ),
			'lineargradient' => $common,
			'radialgradient' => $common,
			'stop'           => $common,
			'pattern'        => $common,
			// Mermaid flowchart labels (HTML inside SVG).
			'foreignobject'  => $common,
			'div'            => array(
				'xmlns'   => true,
				'class'   => true,
				'style'   => true,
				'id'      => true,
				'dir'     => true,
				'lang'    => true,
			),
			'span'           => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'p'              => array(
				'class' => true,
				'style' => true,
			),
			'br'             => array(),
			'b'              => array(),
			'strong'         => array(),
			'i'              => array(),
			'em'             => array(),
			'code'           => array( 'class' => true ),
		);
	}
}
