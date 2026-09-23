<?php
/**
 * IDE wizard payloads compiled from the theme hooks JSON catalog.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Reads hook JSON (one file per hook) and builds a Back/Next tour.
 * Quiz steps come from plugin Ide_Quiz storage, not from theme SEO JSON.
 */
class Ide_Catalog {

	public const MAX_SET = 12;

	/**
	 * Directory that holds actions/ and filters/ JSON, if any.
	 */
	public static function hooks_dir(): string {
		$filtered = apply_filters( 'forwp_ac_ide_hooks_dir', '' );
		if ( is_string( $filtered ) && '' !== $filtered && is_dir( $filtered ) ) {
			return $filtered;
		}

		if ( class_exists( '\FourWPDev_Hooks_Catalog' ) ) {
			$dir = \FourWPDev_Hooks_Catalog::dir();
			if ( is_string( $dir ) && is_dir( $dir ) ) {
				return $dir;
			}
		}

		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $base ) {
			$candidate = $base . '/inc/data/hooks';
			if ( is_dir( $candidate . '/actions' ) || is_dir( $candidate . '/filters' ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Published hooks for the block picker.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function list_hooks(): array {
		$out = array();

		foreach ( self::all_hooks() as $hook ) {
			if ( ! self::is_published( $hook ) ) {
				continue;
			}

			$out[] = array(
				'type'    => (string) ( $hook['type'] ?? 'action' ),
				'slug'    => (string) ( $hook['slug'] ?? '' ),
				'name'    => (string) ( $hook['name'] ?? $hook['slug'] ?? '' ),
				'tagline' => (string) ( $hook['tagline'] ?? '' ),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$type = strcasecmp( $a['type'], $b['type'] );
				return 0 !== $type ? $type : strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * Wizard payload for one hook.
	 *
	 * @param string $type action|filter.
	 * @param string $slug Hook name (wp_head).
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_wizard( string $type, string $slug ) {
		$type = self::sanitize_type( $type );
		$slug = self::sanitize_slug( $slug );

		if ( '' === $slug ) {
			return new \WP_Error(
				'forwp_ac_ide_bad_slug',
				__( 'Missing hook slug.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$hook = self::get_hook( $type, $slug );
		if ( null === $hook ) {
			return new \WP_Error(
				'forwp_ac_ide_not_found',
				__( 'Hook not found in the catalog JSON.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		$wizard = self::compile_wizard( $hook );

		/**
		 * Filter the compiled IDE wizard for a single hook.
		 *
		 * @param array<string, mixed> $wizard Compiled tour.
		 * @param array<string, mixed> $hook   Source JSON.
		 */
		return apply_filters( 'forwp_ac_ide_wizard', $wizard, $hook );
	}

	/**
	 * Current hook plus related_hooks, each compiled with get_wizard().
	 *
	 * @param string $type action|filter.
	 * @param string $slug Hook name (wp_head).
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_group( string $type, string $slug ) {
		$type = self::sanitize_type( $type );
		$slug = self::sanitize_slug( $slug );

		if ( '' === $slug ) {
			return new \WP_Error(
				'forwp_ac_ide_bad_slug',
				__( 'Missing hook slug.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$current = self::get_hook( $type, $slug );
		if ( null === $current ) {
			return new \WP_Error(
				'forwp_ac_ide_not_found',
				__( 'Hook not found in the catalog JSON.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		$queue = array( $current );
		$seen  = array( $type . ':' . $slug => true );

		if ( ! empty( $current['related_hooks'] ) && is_array( $current['related_hooks'] ) ) {
			foreach ( $current['related_hooks'] as $item ) {
				if ( ! is_array( $item ) || empty( $item['slug'] ) ) {
					continue;
				}

				$rel_slug = self::sanitize_slug( (string) $item['slug'] );
				if ( '' === $rel_slug ) {
					continue;
				}

				$related = null;
				if ( ! empty( $item['type'] ) ) {
					$related = self::get_hook( self::sanitize_type( (string) $item['type'] ), $rel_slug );
				}
				if ( null === $related ) {
					$related = self::find_hook_by_slug( $rel_slug );
				}
				if ( null === $related || ! self::is_published( $related ) ) {
					continue;
				}

				$rel_type = self::sanitize_type( (string) ( $related['type'] ?? 'action' ) );
				$rel_name = (string) ( $related['slug'] ?? $rel_slug );
				$key      = $rel_type . ':' . $rel_name;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$queue[]      = $related;
			}
		}

		$wizards = self::compile_wizards( $queue );

		if ( empty( $wizards ) ) {
			return new \WP_Error(
				'forwp_ac_ide_not_found',
				__( 'Hook not found in the catalog JSON.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		$payload = array(
			'type'    => $type,
			'slug'    => $slug,
			'wizards' => $wizards,
		);

		/**
		 * Filter the compiled IDE group tour.
		 *
		 * @param array<string, mixed> $payload Group payload with wizards.
		 * @param array<string, mixed> $current Source hook JSON.
		 */
		return apply_filters( 'forwp_ac_ide_group', $payload, $current );
	}

	/**
	 * Author-picked hooks compiled as a group of wizards (quiz steps included).
	 *
	 * @param array<int, array<string, string>|string> $refs type+slug pairs or "action:wp_head" tokens.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_set( $refs ) {
		$refs  = self::sanitize_hook_refs( $refs );
		$queue = array();
		$seen  = array();

		foreach ( $refs as $ref ) {
			$hook = self::get_hook( $ref['type'], $ref['slug'] );
			if ( null === $hook ) {
				$hook = self::find_hook_by_slug( $ref['slug'] );
			}
			if ( null === $hook || ! self::is_published( $hook ) ) {
				continue;
			}

			$type = self::sanitize_type( (string) ( $hook['type'] ?? $ref['type'] ) );
			$slug = (string) ( $hook['slug'] ?? $ref['slug'] );
			$key  = $type . ':' . $slug;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$queue[]      = $hook;
		}

		$wizards = self::compile_wizards( $queue );
		if ( empty( $wizards ) ) {
			return new \WP_Error(
				'forwp_ac_ide_empty_set',
				__( 'None of the selected hooks were found in the catalog.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		$first = $wizards[0];
		$payload = array(
			'type'    => (string) ( $first['type'] ?? 'action' ),
			'slug'    => (string) ( $first['slug'] ?? '' ),
			'mode'    => 'custom',
			'wizards' => $wizards,
		);

		/**
		 * Filter a custom IDE hook set compiled for a post.
		 *
		 * @param array<string, mixed> $payload Set payload with wizards.
		 * @param array<int, array<string, string>> $refs Sanitized refs.
		 */
		return apply_filters( 'forwp_ac_ide_set', $payload, $refs );
	}

	/**
	 * Normalize block / REST hook refs. Preserves order. Caps at MAX_SET.
	 *
	 * @param mixed $raw Array of objects, tokens, or comma-separated string.
	 * @return array<int, array{type: string, slug: string}>
	 */
	public static function sanitize_hook_refs( $raw ): array {
		$items = array();

		if ( is_string( $raw ) ) {
			$parts = preg_split( '/[,\s]+/', $raw );
			$raw   = is_array( $parts ) ? $parts : array();
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		foreach ( $raw as $item ) {
			$token = '';
			$type  = '';
			$slug  = '';

			if ( is_string( $item ) ) {
				$token = $item;
			} elseif ( is_array( $item ) ) {
				if ( ! empty( $item['slug'] ) ) {
					$type = (string) ( $item['type'] ?? 'action' );
					$slug = (string) $item['slug'];
				} elseif ( ! empty( $item['value'] ) ) {
					$token = (string) $item['value'];
				}
			}

			if ( '' !== $token && false !== strpos( $token, ':' ) ) {
				$bits  = explode( ':', $token, 2 );
				$type  = $bits[0];
				$slug  = $bits[1] ?? '';
			} elseif ( '' !== $token ) {
				$slug = $token;
				$type = 'action';
			}

			$type = self::sanitize_type( $type );
			$slug = self::sanitize_slug( $slug );
			if ( '' === $slug ) {
				continue;
			}

			$items[] = array(
				'type' => $type,
				'slug' => $slug,
			);
		}

		$out  = array();
		$seen = array();
		foreach ( $items as $ref ) {
			if ( count( $out ) >= self::MAX_SET ) {
				break;
			}
			$key = $ref['type'] . ':' . $ref['slug'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $ref;
		}

		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $hooks Hook JSON records.
	 * @return array<int, array<string, mixed>>
	 */
	private static function compile_wizards( array $hooks ): array {
		$wizards = array();

		foreach ( $hooks as $hook ) {
			if ( ! is_array( $hook ) ) {
				continue;
			}
			$wizard = self::get_wizard(
				(string) ( $hook['type'] ?? 'action' ),
				(string) ( $hook['slug'] ?? '' )
			);
			if ( is_wp_error( $wizard ) ) {
				continue;
			}
			$wizards[] = $wizard;
		}

		return $wizards;
	}

	/**
	 * Server-render bootstrap: first wizard step + starter code for crawlers.
	 *
	 * @param string                              $type      action|filter.
	 * @param string                              $slug      Hook name.
	 * @param string                              $tour_mode single|group|custom.
	 * @param array<int, array<string, string>>   $selected  Author-picked refs for custom mode.
	 * @return array<string, mixed>|null
	 */
	public static function ssr_bootstrap( string $type, string $slug, string $tour_mode, array $selected = array() ): ?array {
		$type      = self::sanitize_type( $type );
		$slug      = self::sanitize_slug( $slug );
		$selected  = self::sanitize_hook_refs( $selected );
		if ( ! empty( $selected ) ) {
			$tour_mode = 'custom';
		} elseif ( 'custom' === $tour_mode ) {
			$tour_mode = 'single';
		} else {
			$tour_mode = 'group' === $tour_mode ? 'group' : 'single';
		}

		if ( 'custom' !== $tour_mode && '' === $slug ) {
			return null;
		}

		$wizards_count = 1;
		if ( 'custom' === $tour_mode ) {
			$set = self::get_set( $selected );
			if ( is_wp_error( $set ) ) {
				return null;
			}
			$wizards = isset( $set['wizards'] ) && is_array( $set['wizards'] ) ? $set['wizards'] : array();
			if ( empty( $wizards ) ) {
				return null;
			}
			$wizard        = $wizards[0];
			$wizards_count = count( $wizards );
			$type          = (string) ( $wizard['type'] ?? $type );
			$slug          = (string) ( $wizard['slug'] ?? $slug );
		} elseif ( 'group' === $tour_mode ) {
			$group = self::get_group( $type, $slug );
			if ( is_wp_error( $group ) ) {
				return null;
			}
			$wizards = isset( $group['wizards'] ) && is_array( $group['wizards'] ) ? $group['wizards'] : array();
			if ( empty( $wizards ) ) {
				return null;
			}
			$wizard        = $wizards[0];
			$wizards_count = count( $wizards );
		} else {
			$wizard = self::get_wizard( $type, $slug );
			if ( is_wp_error( $wizard ) ) {
				return null;
			}
		}

		$steps = isset( $wizard['steps'] ) && is_array( $wizard['steps'] ) ? $wizard['steps'] : array();
		if ( empty( $steps ) ) {
			return null;
		}

		$signature    = '';
		$example_code = '';
		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			if ( '' === $signature && ! empty( $step['signature'] ) ) {
				$signature = (string) $step['signature'];
			}
			if ( '' === $example_code && ! empty( $step['code'] ) && 'example' === ( $step['kind'] ?? '' ) ) {
				$example_code = (string) $step['code'];
			}
		}

		return array(
			'type'          => $type,
			'slug'          => $slug,
			'tour_mode'     => $tour_mode,
			'wizard'        => $wizard,
			'step'          => $steps[0],
			'starter'       => (string) ( $wizard['starter'] ?? '' ),
			'signature'     => $signature,
			'example_code'  => $example_code,
			'total_steps'   => count( $steps ),
			'wizards_count' => $wizards_count,
		);
	}

	/**
	 * @param string $type action|filter.
	 */
	public static function sanitize_type( $type ): string {
		$type = is_string( $type ) ? $type : '';

		return 'filter' === $type ? 'filter' : 'action';
	}

	/**
	 * Exact WordPress hook name: letters, numbers, underscore.
	 *
	 * @param mixed $slug Raw slug.
	 */
	public static function sanitize_slug( $slug ): string {
		$slug = is_string( $slug ) ? strtolower( trim( $slug ) ) : '';
		$slug = preg_replace( '/[^a-z0-9_]/', '', $slug );

		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * @param array<string, mixed> $hook Hook JSON.
	 */
	private static function is_published( array $hook ): bool {
		$status = isset( $hook['status'] ) ? (string) $hook['status'] : 'publish';

		return 'draft' !== $status && 'private' !== $status;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function all_hooks(): array {
		if ( class_exists( '\FourWPDev_Hooks_Catalog' ) ) {
			$all = \FourWPDev_Hooks_Catalog::all();
			return is_array( $all ) ? $all : array();
		}

		return self::load_from_dir( self::hooks_dir() );
	}

	/**
	 * @param string $type action|filter.
	 * @param string $slug Hook name.
	 * @return array<string, mixed>|null
	 */
	private static function get_hook( string $type, string $slug ): ?array {
		if ( class_exists( '\FourWPDev_Hooks_Catalog' ) ) {
			$hook = \FourWPDev_Hooks_Catalog::get( $type, $slug );
			return is_array( $hook ) ? $hook : null;
		}

		$key  = $type . ':' . $slug;
		$all  = self::load_from_dir( self::hooks_dir() );

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Resolve a related hook when JSON only has slug (no type).
	 *
	 * @param string $slug Hook name.
	 * @return array<string, mixed>|null
	 */
	private static function find_hook_by_slug( string $slug ): ?array {
		if ( class_exists( '\FourWPDev_Hooks_Catalog' ) ) {
			$hook = \FourWPDev_Hooks_Catalog::find_by_slug( $slug );
			return is_array( $hook ) ? $hook : null;
		}

		foreach ( self::all_hooks() as $hook ) {
			if ( ( $hook['slug'] ?? '' ) === $slug ) {
				return $hook;
			}
		}

		return null;
	}

	/**
	 * Fallback loader when the theme catalog class is not present.
	 *
	 * @param string $dir Catalog root.
	 * @return array<string, array<string, mixed>>
	 */
	private static function load_from_dir( string $dir ): array {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return array();
		}

		$items = array();
		$map   = array(
			'action' => 'actions',
			'filter' => 'filters',
		);

		foreach ( $map as $type => $folder ) {
			$files = glob( $dir . '/' . $folder . '/*.json' );
			if ( ! is_array( $files ) ) {
				continue;
			}

			foreach ( $files as $file ) {
				$base = basename( $file );
				if ( 0 === strpos( $base, '_' ) ) {
					continue;
				}

				$raw  = file_get_contents( $file );
				$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( ! is_array( $data ) ) {
					continue;
				}

				$slug = isset( $data['slug'] ) ? self::sanitize_slug( (string) $data['slug'] ) : '';
				if ( '' === $slug ) {
					$slug = self::sanitize_slug( (string) pathinfo( $file, PATHINFO_FILENAME ) );
				}
				if ( '' === $slug ) {
					continue;
				}

				$data['slug'] = $slug;
				$data['type'] = $type;
				$items[ $type . ':' . $slug ] = $data;
			}
		}

		return $items;
	}

	/**
	 * One wizard per hook: read → example → practice → wrap-up.
	 *
	 * @param array<string, mixed> $hook Hook JSON.
	 * @return array<string, mixed>
	 */
	private static function compile_wizard( array $hook ): array {
		$type = self::sanitize_type( (string) ( $hook['type'] ?? 'action' ) );
		$slug = (string) ( $hook['slug'] ?? '' );
		$name = (string) ( $hook['name'] ?? $slug );
		$add  = 'filter' === $type ? 'add_filter' : 'add_action';

		$starter = self::starter_code( $hook, $type, $slug, $add );
		$steps   = array();

		$intro = trim( (string) ( $hook['intro'] ?? '' ) );
		if ( '' === $intro ) {
			$intro = trim( (string) ( $hook['description'] ?? '' ) );
		}

		$steps[] = array(
			'id'      => 'meet',
			'kind'    => 'read',
			'title'   => sprintf(
				/* translators: %s: hook name */
				__( 'Meet %s', '4wp-advanced-code' ),
				$name
			),
			'body'    => $intro,
			'tagline' => (string) ( $hook['tagline'] ?? '' ),
			'meta'    => array(
				'type'   => $type,
				'phase'  => (string) ( $hook['phase'] ?? '' ),
				'since'  => (string) ( $hook['since'] ?? '' ),
				'source' => (string) ( $hook['source'] ?? '' ),
			),
		);

		$when_body = trim( (string) ( $hook['execution_context'] ?? '' ) );
		$signature = trim( (string) ( $hook['signature'] ?? '' ) );
		$params    = self::string_list( $hook['parameters'] ?? array(), true );
		if ( '' !== $when_body || '' !== $signature ) {
			$steps[] = array(
				'id'         => 'when',
				'kind'       => 'read',
				'title'      => __( 'When it runs', '4wp-advanced-code' ),
				'body'       => $when_body,
				'signature'  => $signature,
				'parameters' => $params,
			);
		}

		$example = self::primary_example( $hook );
		if ( null !== $example ) {
			$steps[] = array(
				'id'    => 'example',
				'kind'  => 'example',
				'title' => $example['title'],
				'body'  => $example['explanation'],
				'code'  => $example['code'],
			);
		}

		$practice_body = 'filter' === $type
			? sprintf(
				/* translators: 1: add_filter, 2: hook slug */
				__( 'Write a callback with %1$s( \'%2$s\', … ) and return the value. This is practice, not a quiz — Next is always available.', '4wp-advanced-code' ),
				$add,
				$slug
			)
			: sprintf(
				/* translators: 1: add_action, 2: hook slug */
				__( 'Write a callback with %1$s( \'%2$s\', … ). This is practice, not a quiz — Next is always available.', '4wp-advanced-code' ),
				$add,
				$slug
			);

		$steps[] = array(
			'id'      => 'practice',
			'kind'    => 'practice',
			'title'   => __( 'Practice in the editor', '4wp-advanced-code' ),
			'body'    => $practice_body,
			'starter' => $starter,
			'check'   => array(
				'hook'     => $slug,
				'function' => $add,
				'returns'  => 'filter' === $type,
			),
		);

		$mistakes = self::string_list( $hook['common_mistakes'] ?? array() );
		$uses     = self::string_list( $hook['use_cases'] ?? array() );
		if ( $mistakes || $uses ) {
			$steps[] = array(
				'id'        => 'watchouts',
				'kind'      => 'read',
				'title'     => __( 'Watch-outs', '4wp-advanced-code' ),
				'body'      => '',
				'mistakes'  => $mistakes,
				'use_cases' => $uses,
			);
		}

		foreach ( self::quiz_steps( $slug ) as $quiz_step ) {
			$steps[] = $quiz_step;
		}

		$related = array();
		if ( ! empty( $hook['related_hooks'] ) && is_array( $hook['related_hooks'] ) ) {
			foreach ( $hook['related_hooks'] as $item ) {
				if ( ! is_array( $item ) || empty( $item['slug'] ) ) {
					continue;
				}
				$related[] = array(
					'slug'     => (string) $item['slug'],
					'relation' => isset( $item['relation'] ) ? (string) $item['relation'] : '',
				);
			}
		}

		$docs = (string) ( $hook['docs_url'] ?? '' );
		$ref  = '';
		if ( class_exists( '\FourWPDev_Hooks_Catalog' ) ) {
			$ref = (string) \FourWPDev_Hooks_Catalog::permalink( $hook );
		}

		$steps[] = array(
			'id'       => 'done',
			'kind'     => 'done',
			'title'    => __( 'You are set', '4wp-advanced-code' ),
			'body'     => sprintf(
				/* translators: %s: hook name */
				__( 'That was the %s tour. Keep the snippet, open the reference page, or restart.', '4wp-advanced-code' ),
				$name
			),
			'related'  => $related,
			'docs_url' => $docs,
			'ref_url'  => $ref,
		);

		return array(
			'type'    => $type,
			'slug'    => $slug,
			'name'    => $name,
			'title'   => sprintf(
				/* translators: %s: hook name */
				__( '%s wizard', '4wp-advanced-code' ),
				$name
			),
			'file'    => 'functions.php',
			'starter' => $starter,
			'steps'   => $steps,
		);
	}

	/**
	 * Think-and-click quiz steps from plugin storage. Skipped when none exist.
	 *
	 * @param string $slug Hook name.
	 * @return array<int, array<string, mixed>>
	 */
	private static function quiz_steps( string $slug ): array {
		if ( ! class_exists( __NAMESPACE__ . '\Ide_Quiz' ) ) {
			return array();
		}

		$questions = Ide_Quiz::questions_for( $slug );
		if ( empty( $questions ) ) {
			return array();
		}

		$steps = array();
		foreach ( $questions as $index => $question ) {
			$steps[] = array(
				'id'       => 'quiz-' . (string) ( $question['id'] ?? ( $index + 1 ) ),
				'kind'     => 'quiz',
				'title'    => __( 'Quick check', '4wp-advanced-code' ),
				'body'     => (string) ( $question['question'] ?? '' ),
				'options'  => $question['options'] ?? array(),
				'feedback' => $question['feedback'] ?? array(),
			);
		}

		return $steps;
	}

	/**
	 * @param array<string, mixed> $hook Hook JSON.
	 * @param string               $type action|filter.
	 * @param string               $slug Hook name.
	 * @param string               $add  add_action|add_filter.
	 */
	private static function starter_code( array $hook, string $type, string $slug, string $add ): string {
		$args     = self::callback_args( $hook, $type );
		$arglist  = '' === $args ? '' : ' ' . $args . ' ';
		$comment  = 'filter' === $type
			? "\t// Write your callback here. Always return the value.\n"
			: "\t// Write your callback here.\n";
		$return   = 'filter' === $type && '' !== $args
			? "\n\treturn " . self::first_arg_name( $args ) . ";\n"
			: "\n";

		return "<?php\n/**\n * Practice: hook into {$slug}\n */\n{$add}( '{$slug}', function({$arglist}) {\n{$comment}{$return}} );\n";
	}

	/**
	 * @param array<string, mixed> $hook Hook JSON.
	 * @param string               $type action|filter.
	 */
	private static function callback_args( array $hook, string $type ): string {
		$names = array();
		if ( ! empty( $hook['parameters'] ) && is_array( $hook['parameters'] ) ) {
			foreach ( $hook['parameters'] as $param ) {
				if ( ! is_array( $param ) || empty( $param['name'] ) ) {
					continue;
				}
				$name = (string) $param['name'];
				if ( '$' !== substr( $name, 0, 1 ) ) {
					$name = '$' . $name;
				}
				$names[] = $name;
			}
		}

		if ( empty( $names ) && 'filter' === $type ) {
			$names[] = '$value';
		}

		return implode( ', ', $names );
	}

	private static function first_arg_name( string $args ): string {
		$parts = explode( ',', $args );
		$first = trim( (string) $parts[0] );

		return '' !== $first ? $first : '$value';
	}

	/**
	 * @param array<string, mixed> $hook Hook JSON.
	 * @return array{title:string,code:string,explanation:string}|null
	 */
	private static function primary_example( array $hook ): ?array {
		if ( empty( $hook['examples'] ) || ! is_array( $hook['examples'] ) ) {
			return null;
		}

		foreach ( $hook['examples'] as $example ) {
			if ( ! is_array( $example ) ) {
				continue;
			}
			$code = trim( (string) ( $example['code'] ?? '' ) );
			if ( '' === $code ) {
				continue;
			}

			$title = trim( (string) ( $example['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = __( 'Example', '4wp-advanced-code' );
			}

			return array(
				'title'       => $title,
				'code'        => $code,
				'explanation' => trim( (string) ( $example['explanation'] ?? '' ) ),
			);
		}

		return null;
	}

	/**
	 * @param mixed $list Raw list.
	 * @param bool  $as_params Parameter objects vs plain strings.
	 * @return array<int, mixed>
	 */
	private static function string_list( $list, bool $as_params = false ): array {
		if ( ! is_array( $list ) ) {
			return array();
		}

		$out = array();
		foreach ( $list as $item ) {
			if ( $as_params && is_array( $item ) ) {
				$name = trim( (string) ( $item['name'] ?? '' ) );
				if ( '' === $name ) {
					continue;
				}
				$out[] = array(
					'name'        => $name,
					'type'        => (string) ( $item['type'] ?? '' ),
					'description' => (string) ( $item['description'] ?? '' ),
				);
				continue;
			}

			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$out[] = trim( $item );
			}
		}

		return $out;
	}
}
