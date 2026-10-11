<?php
/**
 * Diagram PNG upload + block data REST + optional GraphQL.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints for Mermaid diagram assets.
 */
class Diagram_Rest {

	public const NAMESPACE = 'forwp-advanced-code/v1';

	/**
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'graphql_register_types', array( self::class, 'register_graphql' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes(): void {
		if ( ! Modules::is_diagrams_enabled() ) {
			return;
		}

		register_rest_route(
			self::NAMESPACE,
			'/diagram/png',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'upload_png' ),
				'permission_callback' => array( self::class, 'can_upload' ),
				'args'                => array(
					'dataUrl' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'title'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'alt'     => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'postId'  => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'pngId'   => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagrams/(?P<post_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'list_for_post' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function can_upload( \WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'postId' ) );
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id ) && current_user_can( 'upload_files' );
		}

		return current_user_can( 'upload_files' );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function upload_png( \WP_REST_Request $request ) {
		$data_url = (string) $request->get_param( 'dataUrl' );
		if ( ! preg_match( '#^data:image/png;base64,(.+)$#', $data_url, $match ) ) {
			return new \WP_Error(
				'forwp_ac_diagram_invalid_png',
				__( 'Invalid PNG data URL.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$binary = base64_decode( $match[1], true );
		if ( false === $binary || '' === $binary ) {
			return new \WP_Error(
				'forwp_ac_diagram_decode_failed',
				__( 'Could not decode PNG data.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$title  = (string) $request->get_param( 'title' );
		$alt    = (string) $request->get_param( 'alt' );
		$post_id = absint( $request->get_param( 'postId' ) );
		$png_id  = absint( $request->get_param( 'pngId' ) );

		if ( '' === $title ) {
			$title = __( 'Diagram', '4wp-advanced-code' );
		}
		if ( '' === $alt ) {
			$alt = $title;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( 'forwp-diagram.png' );
		if ( ! $tmp ) {
			return new \WP_Error(
				'forwp_ac_diagram_temp_failed',
				__( 'Could not create a temporary file.', '4wp-advanced-code' ),
				array( 'status' => 500 )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp, $binary );

		$file = array(
			'name'     => sanitize_file_name( sanitize_title( $title ) . '-diagram.png' ),
			'type'     => 'image/png',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => strlen( $binary ),
		);

		$attachment_id = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

		if ( Diagram_Settings::is_credit_enabled() ) {
			$credit = Diagram_Settings::site_credit_text();
			if ( '' !== $credit ) {
				update_post_meta( $attachment_id, '_forwp_ac_diagram_credit', $credit );
				wp_update_post(
					array(
						'ID'           => $attachment_id,
						'post_content' => $credit,
					)
				);
			}
		}

		if ( $png_id > 0 && $png_id !== (int) $attachment_id && current_user_can( 'delete_post', $png_id ) ) {
			$old = get_post( $png_id );
			if ( $old instanceof \WP_Post && 'attachment' === $old->post_type ) {
				wp_delete_attachment( $png_id, true );
			}
		}

		$url = wp_get_attachment_url( $attachment_id );

		return array(
			'id'  => (int) $attachment_id,
			'url' => is_string( $url ) ? $url : '',
			'alt' => $alt,
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function list_for_post( \WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error(
				'forwp_ac_diagram_post_missing',
				__( 'Post not found.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'forwp_ac_diagram_forbidden',
				__( 'You cannot edit this post.', '4wp-advanced-code' ),
				array( 'status' => 403 )
			);
		}

		return array(
			'postId'    => $post_id,
			'diagrams'  => self::extract_from_content( $post->post_content ),
		);
	}

	/**
	 * @param string $content Post content.
	 * @return list<array<string, mixed>>
	 */
	public static function extract_from_content( string $content ): array {
		$blocks = parse_blocks( $content );
		$out    = array();

		$walk = static function ( array $blocks ) use ( &$walk, &$out ): void {
			foreach ( $blocks as $block ) {
				$name = $block['blockName'] ?? '';
				if ( 'forwp/diagram' === $name || '4wp/diagram' === $name ) {
					$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
					$png_id = isset( $attrs['pngId'] ) ? absint( $attrs['pngId'] ) : 0;
					$png_url = $png_id > 0 ? (string) wp_get_attachment_url( $png_id ) : '';
					$out[]   = array(
						'code'   => (string) ( $attrs['code'] ?? '' ),
						'svg'    => (string) ( $attrs['svg'] ?? '' ),
						'pngId'  => $png_id,
						'pngUrl' => $png_url,
						'title'  => (string) ( $attrs['title'] ?? '' ),
					);
				}
				if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};

		$walk( $blocks );

		return $out;
	}

	/**
	 * @return void
	 */
	public static function register_graphql(): void {
		if ( ! Modules::is_diagrams_enabled() || ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		register_graphql_object_type(
			'ForwpDiagram',
			array(
				'description' => __( 'Mermaid diagram block data.', '4wp-advanced-code' ),
				'fields'      => array(
					'code'   => array( 'type' => 'String' ),
					'svg'    => array( 'type' => 'String' ),
					'pngId'  => array( 'type' => 'Int' ),
					'pngUrl' => array( 'type' => 'String' ),
					'title'  => array( 'type' => 'String' ),
				),
			)
		);

		register_graphql_field(
			'ContentNode',
			'forwpDiagrams',
			array(
				'type'        => array( 'list_of' => 'ForwpDiagram' ),
				'description' => __( 'Diagram blocks on this content.', '4wp-advanced-code' ),
				'resolve'     => static function ( $source ) {
					$post_id = 0;
					if ( is_object( $source ) && isset( $source->ID ) ) {
						$post_id = (int) $source->ID;
					} elseif ( is_array( $source ) && isset( $source['databaseId'] ) ) {
						$post_id = (int) $source['databaseId'];
					}
					$post = get_post( $post_id );
					if ( ! $post instanceof \WP_Post ) {
						return array();
					}
					return Diagram_Rest::extract_from_content( $post->post_content );
				},
			)
		);
	}
}
