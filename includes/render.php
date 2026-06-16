<?php
/**
 * Advanced Code block server-side render.
 *
 * @package ForWP\AdvancedCode
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Block content.
 * @var WP_Block             $block      Block instance.
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

$global_enabled   = (bool) get_option( 'forwp_advanced_code_enabled', true );
$advanced_enabled = $attributes['advancedEnabled'] ?? true;
$code             = (string) ( $attributes['content'] ?? '' );

if ( ! $global_enabled || ! $advanced_enabled ) {
	return sprintf(
		'<pre class="wp-block-code"><code>%s</code></pre>',
		esc_html( $code )
	);
}

return Block_Wrapper::build_enhanced_block(
	$code,
	array(
		'attrs' => $attributes,
	)
);
