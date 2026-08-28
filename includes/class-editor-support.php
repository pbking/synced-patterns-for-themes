<?php
/**
 * Composes patterns for everything the editor loads.
 *
 * @package SyncedPatternsForThemes
 */

namespace TwentyBellows\SyncedPatternsForThemes;

use WP_Block_Patterns_Registry;
use WP_Block_Template;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Runs `Pattern_Resolver` over the markup the block editor loads.
 *
 * Since WordPress 6.6 core flattens `core/pattern` blocks server side so the
 * editor doesn't have to (`resolve_pattern_blocks()`), and flattening throws
 * away everything but the pattern's slug. These two hooks compose the patterns
 * first, so what core flattens already has its content in place.
 *
 * Both are cheap on sites that don't use the feature: the markup is only parsed
 * when a substring check says it might be worth it.
 */
class Editor_Support {

	/**
	 * REST route the block editor loads patterns from.
	 */
	const PATTERNS_ROUTE = '/wp/v2/block-patterns/patterns';

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path to the main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Registers the editor hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'rest_request_after_callbacks', array( $this, 'resolve_patterns_response' ), 10, 3 );
		add_filter( 'get_block_templates', array( $this, 'resolve_templates' ) );
		add_filter( 'get_block_template', array( $this, 'resolve_template' ) );
		add_filter( 'get_block_file_template', array( $this, 'resolve_template' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Composes the patterns the editor lists, previews and inserts.
	 *
	 * Core has already flattened the response by the time this runs, so the
	 * content is recomposed from the pattern registry rather than patched.
	 *
	 * @param WP_REST_Response|mixed $response Result to send to the client.
	 * @param array|mixed            $handler  Route handler used for the request.
	 * @param WP_REST_Request|mixed  $request  Request used to generate the response.
	 * @return WP_REST_Response|mixed The response.
	 */
	public function resolve_patterns_response( $response, $handler, $request ) {
		if ( ! $response instanceof WP_REST_Response
			|| ! $request instanceof WP_REST_Request
			|| self::PATTERNS_ROUTE !== $request->get_route()
		) {
			return $response;
		}

		$patterns = $response->get_data();

		if ( ! is_array( $patterns ) ) {
			return $response;
		}

		$registry = WP_Block_Patterns_Registry::get_instance();
		$changed  = false;

		foreach ( $patterns as $index => $pattern ) {
			if ( ! isset( $pattern['name'], $pattern['content'] ) || ! $registry->is_registered( $pattern['name'] ) ) {
				continue;
			}

			$source_slug = Synced_Patterns::get_source_slug( $pattern['name'] );

			/*
			 * Core has just flattened this companion pattern into the blocks it
			 * points at. Put the reference back: inserting it should link the
			 * pattern, not copy it.
			 */
			if ( null !== $source_slug ) {
				$patterns[ $index ]['content'] = Synced_Patterns::get_reference_markup( $source_slug );
				$changed                       = true;
				continue;
			}

			$registered = $registry->get_registered( $pattern['name'] );
			$markup     = $registered['content'] ?? '';
			$resolved   = Pattern_Resolver::resolve( $markup );

			if ( $resolved === $markup ) {
				continue;
			}

			// Let core finish the job for any plain pattern blocks left over.
			$patterns[ $index ]['content'] = serialize_blocks( resolve_pattern_blocks( parse_blocks( $resolved ) ) );

			$changed = true;
		}

		if ( $changed ) {
			$response->set_data( $patterns );
		}

		return $response;
	}

	/**
	 * Composes the patterns used by a list of templates.
	 *
	 * @param WP_Block_Template[]|mixed $templates Templates being returned.
	 * @return WP_Block_Template[]|mixed The templates.
	 */
	public function resolve_templates( $templates ) {
		if ( ! is_array( $templates ) ) {
			return $templates;
		}

		foreach ( $templates as $index => $template ) {
			$templates[ $index ] = $this->resolve_template( $template );
		}

		return $templates;
	}

	/**
	 * Composes the patterns used by a single template or template part.
	 *
	 * Only for the editor: on the front end `Pattern_Block` renders the
	 * template's pattern blocks with their content already in context.
	 *
	 * @param WP_Block_Template|mixed $template Template being returned.
	 * @return WP_Block_Template|mixed The template.
	 */
	public function resolve_template( $template ) {
		if ( ! $template instanceof WP_Block_Template || ! self::is_editor_request() ) {
			return $template;
		}

		$template->content = Pattern_Resolver::resolve( $template->content );

		return $template;
	}

	/**
	 * Determines whether the current request is loading content for the editor.
	 *
	 * @return bool Whether template content should be composed for this request.
	 */
	public static function is_editor_request(): bool {
		/**
		 * Filters whether template content is composed for the current request.
		 *
		 * Templates only need composing for the block editor, which loads them
		 * over the REST API. The front end renders their pattern blocks
		 * directly, with the content already in block context.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $is_editor_request Whether this request loads content for the editor.
		 */
		return (bool) apply_filters(
			'synced_patterns_for_themes_is_editor_request',
			wp_is_serving_rest_request()
		);
	}

	/**
	 * Enqueues the editor script.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = plugin_dir_path( $this->file ) . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'synced-patterns-for-themes',
			plugins_url( 'build/index.js', $this->file ),
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);

		wp_add_inline_script(
			'synced-patterns-for-themes',
			sprintf(
				'window.syncedPatternsForThemes = %s;',
				wp_json_encode( array( 'syncedPatterns' => Synced_Patterns::get_slugs() ) )
			),
			'before'
		);
	}
}
