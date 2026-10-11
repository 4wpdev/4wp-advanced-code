<?php
/**
 * Diagram module settings (credit toggle) and dismissible review notice.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Diagram-specific options and admin notice (SEO Helper Notices pattern).
 */
class Diagram_Settings {

	public const OPTION_CREDIT         = 'forwp_ac_diagram_credit_enabled';
	public const OPTION_SHOW_SOURCE    = 'forwp_ac_diagram_show_source';
	public const OPTION_CAPTION_ALIGN  = 'forwp_ac_diagram_caption_align';
	public const NOTICE_REVIEW         = 'diagram_review';
	public const DISMISS_META          = 'forwp_ac_dismissed_notice_';

	/**
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'render_review_notice' ) );
		add_action( 'wp_ajax_forwp_ac_dismiss_notice', array( self::class, 'handle_dismiss' ) );
	}

	public static function is_credit_enabled(): bool {
		return (bool) get_option( self::OPTION_CREDIT, false );
	}

	/**
	 * Brand watermark on exported images (diagram PNGs now; code images later).
	 * Same option as schema credit — Site Identity name.
	 */
	public static function is_watermark_enabled(): bool {
		return self::is_credit_enabled();
	}

	/**
	 * Whether the front-end “Diagram code” disclosure is shown.
	 */
	public static function is_source_visible(): bool {
		return (bool) get_option( self::OPTION_SHOW_SOURCE, false );
	}

	/**
	 * Caption text-align: left | center | right.
	 */
	public static function caption_align(): string {
		$align = sanitize_key( (string) get_option( self::OPTION_CAPTION_ALIGN, 'center' ) );

		return in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
	}

	/**
	 * @param mixed $value Raw POST value.
	 */
	public static function sanitize_caption_align( $value ): string {
		$align = sanitize_key( (string) $value );

		return in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
	}

	/**
	 * Site Identity name for credit / watermark (never a vendor marketing URL).
	 */
	public static function site_credit_text(): string {
		return trim( (string) get_bloginfo( 'name' ) );
	}

	/**
	 * @return void
	 */
	public static function render_review_notice(): void {
		if ( ! Modules::is_diagrams_enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::should_show_review() ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible forwp-ac-persistent-notice" data-notice="%1$s"><p>%2$s</p></div>',
			esc_attr( self::NOTICE_REVIEW ),
			esc_html__( 'Using Diagrams? If it helps your workflow, consider leaving a review on WordPress.org.', '4wp-advanced-code' )
		);
	}

	/**
	 * @return void
	 */
	public static function handle_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		check_ajax_referer( 'forwp_ac_dismiss_notice', 'nonce' );

		$notice = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( (string) $_POST['notice'] ) ) : '';
		if ( self::NOTICE_REVIEW !== $notice ) {
			wp_send_json_error( array( 'message' => 'unknown_notice' ), 400 );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META . $notice, '1' );
		wp_send_json_success();
	}

	private static function should_show_review(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'forwp-advanced-code' ) ) {
			return false;
		}

		return ! (bool) get_user_meta( get_current_user_id(), self::DISMISS_META . self::NOTICE_REVIEW, true );
	}
}
