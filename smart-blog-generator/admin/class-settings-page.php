<?php
/**
 * SBG_Settings_Page
 *
 * Registers and renders the plugin's Settings API page.
 *
 * All methods are static so WordPress hook callbacks can reference the class
 * without needing an instantiated object (add_action( 'admin_init', [
 * SBG_Settings_Page::class, 'register_settings' ] )).
 *
 * Options stored:
 *   sbg_anthropic_api_key   – Anthropic secret key
 *   sbg_unsplash_access_key – Unsplash access key
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Settings API registration and the settings page renderer.
 */
class SBG_Settings_Page {

	/** Settings option group used by settings_fields() / options.php. */
	private const OPTION_GROUP = 'sbg_settings_group';

	/** Page slug this class renders to (matches add_submenu_page slug). */
	private const PAGE_SLUG = 'smart-blog-generator-settings';

	// -------------------------------------------------------------------------
	// Settings API registration
	// -------------------------------------------------------------------------

	/**
	 * Registers settings, sections, and fields via the WordPress Settings API.
	 *
	 * Hooked to admin_init by SBG_Admin_Controller.
	 */
	public static function register_settings(): void {
		// Register each option with sanitization callbacks. WordPress calls
		// the callback before saving to the database, so the stored value is
		// always clean.
		register_setting(
			self::OPTION_GROUP,
			'sbg_anthropic_api_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false, // Never expose API keys via REST.
			]
		);

		register_setting(
			self::OPTION_GROUP,
			'sbg_unsplash_access_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			]
		);

		// ── Section ───────────────────────────────────────────────────────────
		add_settings_section(
			'sbg_section_api_keys',
			__( 'API Keys', 'smart-blog-generator' ),
			[ self::class, 'render_section_description' ],
			self::PAGE_SLUG
		);

		// ── Fields ────────────────────────────────────────────────────────────
		add_settings_field(
			'sbg_anthropic_api_key',
			__( 'Anthropic API Key', 'smart-blog-generator' ),
			[ self::class, 'render_field_anthropic_key' ],
			self::PAGE_SLUG,
			'sbg_section_api_keys'
		);

		add_settings_field(
			'sbg_unsplash_access_key',
			__( 'Unsplash Access Key', 'smart-blog-generator' ),
			[ self::class, 'render_field_unsplash_key' ],
			self::PAGE_SLUG,
			'sbg_section_api_keys'
		);
	}

	// -------------------------------------------------------------------------
	// Field / section renderers
	// -------------------------------------------------------------------------

	/**
	 * Outputs the description below the section heading.
	 */
	public static function render_section_description(): void {
		echo '<p>' . esc_html__( 'API keys are stored in wp_options and never exposed publicly. Keep them secret.', 'smart-blog-generator' ) . '</p>';
	}

	/**
	 * Renders the Anthropic API key input field.
	 *
	 * Uses type="password" to mask the value in the browser and disable
	 * password-manager autofill on this field specifically.
	 */
	public static function render_field_anthropic_key(): void {
		$value = esc_attr( get_option( 'sbg_anthropic_api_key', '' ) );
		?>
		<input
			type="password"
			id="sbg_anthropic_api_key"
			name="sbg_anthropic_api_key"
			value="<?php echo $value; ?>"
			class="regular-text"
			autocomplete="new-password"
			spellcheck="false"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: the word "Console" is a proper noun (Anthropic Console) */
				esc_html__( 'Obtain your key from the Anthropic Console → API Keys. Required for post generation.', 'smart-blog-generator' )
			);
			?>
		</p>
		<?php

		// Show a warning inline if the key is empty (not just on first activation).
		if ( '' === get_option( 'sbg_anthropic_api_key', '' ) ) {
			echo '<p class="description" style="color:#d63638;">';
			esc_html_e( '⚠ No key saved yet. Post generation is disabled until a key is provided.', 'smart-blog-generator' );
			echo '</p>';
		}
	}

	/**
	 * Renders the Unsplash access key input field.
	 */
	public static function render_field_unsplash_key(): void {
		$value = esc_attr( get_option( 'sbg_unsplash_access_key', '' ) );
		?>
		<input
			type="password"
			id="sbg_unsplash_access_key"
			name="sbg_unsplash_access_key"
			value="<?php echo $value; ?>"
			class="regular-text"
			autocomplete="new-password"
			spellcheck="false"
		/>
		<p class="description">
			<?php esc_html_e( 'Create a free application at unsplash.com/developers and copy the Access Key. Optional — leave blank to skip featured images.', 'smart-blog-generator' ); ?>
		</p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	/**
	 * Outputs the full settings page HTML.
	 *
	 * Called by WordPress when the user visits the settings sub-page. The
	 * capability guard here is a secondary check — the menu already enforces
	 * manage_options for visibility, but defence-in-depth matters.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-blog-generator' ) );
		}
		?>
		<div class="wrap sbg-wrap">
			<h1><?php esc_html_e( 'Smart Blog Generator — Settings', 'smart-blog-generator' ); ?></h1>

			<?php
			// settings_errors() displays save confirmations and any custom
			// errors added via add_settings_error().
			settings_errors( self::OPTION_GROUP );
			?>

			<form method="post" action="options.php" novalidate="novalidate">
				<?php
				// Output the hidden option_page field and _wpnonce for options.php.
				settings_fields( self::OPTION_GROUP );

				// Output all sections and fields registered to our page slug.
				do_settings_sections( self::PAGE_SLUG );

				submit_button( __( 'Save Settings', 'smart-blog-generator' ) );
				?>
			</form>

			<!-- How-to reference panel -->
			<div class="sbg-card" style="max-width:600px;margin-top:32px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Where to find your API keys', 'smart-blog-generator' ); ?></h2>

				<h3><?php esc_html_e( 'Anthropic (required)', 'smart-blog-generator' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Sign in at console.anthropic.com.', 'smart-blog-generator' ); ?></li>
					<li><?php esc_html_e( 'Go to Settings → API Keys.', 'smart-blog-generator' ); ?></li>
					<li><?php esc_html_e( 'Click "Create Key", name it, and copy the secret.', 'smart-blog-generator' ); ?></li>
					<li><?php esc_html_e( 'Paste it in the Anthropic API Key field above.', 'smart-blog-generator' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Unsplash (optional)', 'smart-blog-generator' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Create a developer account at unsplash.com/developers.', 'smart-blog-generator' ); ?></li>
					<li><?php esc_html_e( 'Click "New Application" and accept the API guidelines.', 'smart-blog-generator' ); ?></li>
					<li><?php esc_html_e( 'Scroll to "Keys" and copy the Access Key.', 'smart-blog-generator' ); ?></li>
					<li><?php esc_html_e( 'Paste it in the Unsplash Access Key field above.', 'smart-blog-generator' ); ?></li>
				</ol>

				<p style="color:#50575e;margin-bottom:0;">
					<?php
					printf(
						/* translators: %s: model name */
						esc_html__( 'Posts are generated using the %s model.', 'smart-blog-generator' ),
						'<strong>' . esc_html( SBG_ANTHROPIC_MODEL ) . '</strong>'
					);
					?>
				</p>
			</div>
		</div><!-- .sbg-wrap -->
		<?php
	}
}
