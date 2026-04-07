<?php
/**
 * SBG_Admin_Controller
 *
 * The central wiring point for all WordPress admin hooks:
 *   – admin_menu        → registers Tools > Smart Blog Generator + Settings sub-page
 *   – admin_init        → delegates to SBG_Settings_Page for Settings API registration
 *   – admin_enqueue_scripts → loads CSS/JS only on our own admin pages
 *   – add_meta_boxes    → delegates to SBG_SEO_Meta_Box
 *   – wp_ajax_*         → handles AJAX post-generation request
 *
 * All capability checks (manage_options) and nonce verification happen here
 * before any business logic is invoked.
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton controller for all admin-side plugin behaviour.
 */
class SBG_Admin_Controller {

	/** Singleton instance. */
	private static ?SBG_Admin_Controller $instance = null;

	/** Hook suffix returned by add_management_page() for the generator page. */
	private string $generator_hook = '';

	/** Hook suffix returned by add_submenu_page() for the settings page. */
	private string $settings_hook = '';

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/** Returns (and on first call, creates) the singleton instance. */
	public static function instance(): SBG_Admin_Controller {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use instance(). */
	private function __construct() {
		$this->register_hooks();
	}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/** Attaches all WordPress hooks needed by the admin side of the plugin. */
	private function register_hooks(): void {
		// Admin menu pages.
		add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );

		// Settings API fields/sections — handled by SBG_Settings_Page.
		add_action( 'admin_init', [ SBG_Settings_Page::class, 'register_settings' ] );

		// Enqueue admin-only CSS and JS.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// SEO indicator meta box on post edit screens.
		add_action( 'add_meta_boxes', [ SBG_SEO_Meta_Box::class, 'register' ] );

		// AJAX handler — logged-in users only (no wp_ajax_nopriv_ needed for
		// an admin-only tool).
		add_action( 'wp_ajax_sbg_generate_post', [ $this, 'handle_ajax_generate' ] );
	}

	// -------------------------------------------------------------------------
	// Admin menu registration
	// -------------------------------------------------------------------------

	/**
	 * Registers the Tools > Smart Blog Generator page and its Settings sub-page.
	 *
	 * Both pages require manage_options. WordPress enforces the capability
	 * check for menu visibility, but we also check it inside each renderer
	 * for defence in depth.
	 */
	public function register_admin_menus(): void {
		// Primary generator page under Tools.
		$this->generator_hook = (string) add_management_page(
			__( 'Smart Blog Generator', 'smart-blog-generator' ), // page <title>
			__( 'Smart Blog Generator', 'smart-blog-generator' ), // menu label
			'manage_options',
			'smart-blog-generator',
			[ SBG_Generator_Page::class, 'render' ]
		);

		// Settings sub-page — appears as a child of the generator page entry.
		$this->settings_hook = (string) add_submenu_page(
			'smart-blog-generator',                                // parent slug
			__( 'SBG Settings', 'smart-blog-generator' ),
			__( 'Settings', 'smart-blog-generator' ),
			'manage_options',
			'smart-blog-generator-settings',
			[ SBG_Settings_Page::class, 'render' ]
		);
	}

	// -------------------------------------------------------------------------
	// Asset enqueuing
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the generator JS and inline admin CSS only on our own pages.
	 *
	 * Limiting enqueues to our pages avoids polluting other admin screens and
	 * prevents accidental conflicts with other plugins.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on our plugin's own admin pages.
		$our_pages = array_filter( [ $this->generator_hook, $this->settings_hook ] );

		if ( ! in_array( $hook_suffix, $our_pages, true ) ) {
			return;
		}

		// Piggyback on the always-loaded wp-admin stylesheet to inject our
		// lightweight admin styles without a separate HTTP request.
		wp_add_inline_style( 'wp-admin', $this->inline_css() );

		// Generator AJAX script — depends on jQuery (bundled with WordPress).
		wp_enqueue_script(
			'sbg-generator',
			SBG_PLUGIN_URL . 'admin/js/generator.js',
			[ 'jquery' ],
			SBG_VERSION,
			true // load in footer
		);

		// Pass data to the JS: AJAX URL, nonce, and localised strings.
		wp_localize_script(
			'sbg-generator',
			'SBG', // global JS object name
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sbg_generate_post' ),
				'i18n'    => [
					'generating' => __( 'Generating…', 'smart-blog-generator' ),
					'generate'   => __( 'Generate Post', 'smart-blog-generator' ),
					'error'      => __( 'An unexpected error occurred. Please try again.', 'smart-blog-generator' ),
					'editPost'   => __( 'Edit Draft Post →', 'smart-blog-generator' ),
				],
			]
		);
	}

	/**
	 * Returns the inline CSS string injected into the admin.
	 *
	 * Kept intentionally minimal — just enough to style the plugin's own UI
	 * elements without risking collisions with core or third-party styles.
	 *
	 * @return string CSS rules.
	 */
	private function inline_css(): string {
		return '
/* ── Smart Blog Generator admin styles ─────────────────────────────── */
.sbg-wrap .sbg-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 3px;
	padding: 20px 24px;
	margin-top: 16px;
}
.sbg-wrap .form-table th { width: 200px; }
#sbg-result { display: none; margin-top: 20px; }
#sbg-result .notice { margin: 0; }
.sbg-spinner {
	display: none;
	vertical-align: middle;
	margin-left: 8px;
}
/* SEO indicator badges */
.sbg-badge {
	display: inline-block;
	padding: 2px 7px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: .3px;
}
.sbg-badge-pass { background: #d4edda; color: #155724; }
.sbg-badge-warn { background: #fff3cd; color: #856404; }
.sbg-badge-fail { background: #f8d7da; color: #721c24; }
/* Internal-link placeholder spans inside post content */
.sbg-link-placeholder {
	background: #fff8e1;
	border: 1px dashed #f0ad4e;
	border-radius: 2px;
	padding: 0 3px;
	font-size: .9em;
	cursor: help;
}
/* Meta box table */
#sbg-seo-metabox-content td:first-child { color: #50575e; width: 55%; }
#sbg-seo-metabox-content td:last-child  { text-align: right; }
		';
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Handles the wp_ajax_sbg_generate_post action.
	 *
	 * Security checks performed (in order):
	 *   1. Nonce verification via check_ajax_referer().
	 *   2. Capability check — current user must have manage_options.
	 *
	 * Orchestration flow:
	 *   1. Sanitize and validate form inputs.
	 *   2. Verify required API keys are configured.
	 *   3. Call SBG_API_Handler::generate().
	 *   4. Call SBG_Image_Fetcher::fetch_and_attach() (non-fatal if it fails).
	 *   5. Call SBG_Post_Creator::create().
	 *   6. Return JSON success or error payload.
	 */
	public function handle_ajax_generate(): void {
		// ── 1. Nonce verification ─────────────────────────────────────────────
		// check_ajax_referer() calls wp_die() on failure, so no return needed.
		check_ajax_referer( 'sbg_generate_post', 'nonce' );

		// ── 2. Capability check ───────────────────────────────────────────────
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You do not have permission to perform this action.', 'smart-blog-generator' ) ],
				403
			);
		}

		// ── 3. Sanitize inputs ────────────────────────────────────────────────
		$topic       = isset( $_POST['sbg_topic'] )    ? sanitize_text_field( wp_unslash( $_POST['sbg_topic'] ) )    : '';
		$keyword     = isset( $_POST['sbg_keyword'] )  ? sanitize_text_field( wp_unslash( $_POST['sbg_keyword'] ) )  : '';
		$tone        = isset( $_POST['sbg_tone'] )     ? sanitize_text_field( wp_unslash( $_POST['sbg_tone'] ) )     : 'informational';
		$category_id = isset( $_POST['sbg_category'] ) ? absint( $_POST['sbg_category'] )                           : 0;

		// Whitelist tone values.
		$allowed_tones = [ 'informational', 'how-to', 'listicle' ];
		if ( ! in_array( $tone, $allowed_tones, true ) ) {
			$tone = 'informational';
		}

		// Validate required fields.
		if ( '' === $topic || '' === $keyword ) {
			wp_send_json_error(
				[ 'message' => __( 'Topic and target keyword are both required.', 'smart-blog-generator' ) ]
			);
		}

		// ── 4. Retrieve API keys ──────────────────────────────────────────────
		$anthropic_key = get_option( 'sbg_anthropic_api_key', '' );
		$unsplash_key  = get_option( 'sbg_unsplash_access_key', '' );

		if ( empty( $anthropic_key ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Anthropic API key is not set. Please configure it in Settings.', 'smart-blog-generator' ) ]
			);
		}

		// ── 5. Generate content ───────────────────────────────────────────────
		$api_handler = new SBG_API_Handler( $anthropic_key );
		$content     = $api_handler->generate( $topic, $keyword, $tone );

		if ( is_wp_error( $content ) ) {
			wp_send_json_error( [ 'message' => $content->get_error_message() ] );
		}

		// ── 6. Fetch featured image (non-fatal) ───────────────────────────────
		$attachment_id  = 0;
		$image_notice   = '';

		if ( ! empty( $unsplash_key ) ) {
			$fetcher       = new SBG_Image_Fetcher( $unsplash_key );
			$image_result  = $fetcher->fetch_and_attach( $keyword, $content['h1'] );

			if ( is_wp_error( $image_result ) ) {
				// Log the image failure but continue — post creation proceeds
				// without a featured image, and the caller is informed via
				// the image_notice field in the response.
				$image_notice = sprintf(
					/* translators: %s: image error message */
					__( 'Featured image could not be set: %s', 'smart-blog-generator' ),
					$image_result->get_error_message()
				);
			} else {
				$attachment_id = $image_result;
			}
		} else {
			$image_notice = __( 'No Unsplash key configured — post created without a featured image.', 'smart-blog-generator' );
		}

		// ── 7. Create draft post ──────────────────────────────────────────────
		$post_creator = new SBG_Post_Creator();
		$post_id      = $post_creator->create( $content, $keyword, $attachment_id, $category_id );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
		}

		// ── 8. Return success payload ─────────────────────────────────────────
		wp_send_json_success( [
			'post_id'      => $post_id,
			'edit_url'     => get_edit_post_link( $post_id, 'raw' ),
			'post_title'   => esc_html( $content['h1'] ),
			'seo_title'    => esc_html( $content['seo_title'] ),
			'has_image'    => $attachment_id > 0,
			'image_notice' => $image_notice,
		] );
	}
}
