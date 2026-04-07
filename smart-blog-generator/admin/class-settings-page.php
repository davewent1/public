<?php
/**
 * SBG_Settings_Page
 *
 * Registers and renders the plugin's Settings page, split into four sections:
 *
 *   1. API Keys           – Anthropic + Unsplash credentials
 *   2. Generation         – model, tokens, timeout, word count, FAQ/link counts
 *   3. Content Defaults   – default tone, category, post status
 *   4. Image Settings     – Unsplash orientation and image size
 *
 * All methods are static so WordPress hooks can reference them by class name
 * without requiring an instantiated object.
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Settings API registration and page rendering.
 */
class SBG_Settings_Page {

	private const OPTION_GROUP = 'sbg_settings_group';
	private const PAGE_SLUG    = 'smart-blog-generator-settings';

	// -------------------------------------------------------------------------
	// Settings API registration
	// -------------------------------------------------------------------------

	/**
	 * Registers all settings, sections, and fields.
	 * Hooked to admin_init by SBG_Admin_Controller.
	 */
	public static function register_settings(): void {
		self::register_options();
		self::register_sections();
		self::register_fields();
	}

	// ── Option registration ───────────────────────────────────────────────────

	/** Registers every wp_option with its type and sanitize callback. */
	private static function register_options(): void {
		$options = [
			// API keys.
			'sbg_anthropic_api_key'   => [ 'string',  'sanitize_text_field' ],
			'sbg_unsplash_access_key' => [ 'string',  'sanitize_text_field' ],

			// Generation settings.
			'sbg_anthropic_model'     => [ 'string',  [ self::class, 'sanitize_model' ] ],
			'sbg_max_tokens'          => [ 'integer', [ self::class, 'sanitize_max_tokens' ] ],
			'sbg_api_timeout'         => [ 'integer', [ self::class, 'sanitize_api_timeout' ] ],
			'sbg_word_count_min'      => [ 'integer', [ self::class, 'sanitize_word_count_min' ] ],
			'sbg_word_count_max'      => [ 'integer', [ self::class, 'sanitize_word_count_max' ] ],
			'sbg_faq_count'           => [ 'integer', [ self::class, 'sanitize_faq_count' ] ],
			'sbg_link_count'          => [ 'integer', [ self::class, 'sanitize_link_count' ] ],

			// Content defaults.
			'sbg_default_tone'        => [ 'string',  [ self::class, 'sanitize_tone' ] ],
			'sbg_default_category'    => [ 'integer', 'absint' ],
			'sbg_post_status'         => [ 'string',  [ self::class, 'sanitize_post_status' ] ],

			// Image settings.
			'sbg_image_orientation'   => [ 'string',  [ self::class, 'sanitize_orientation' ] ],
			'sbg_image_size'          => [ 'string',  [ self::class, 'sanitize_image_size' ] ],
		];

		foreach ( $options as $name => [ $type, $callback ] ) {
			register_setting(
				self::OPTION_GROUP,
				$name,
				[
					'type'              => $type,
					'sanitize_callback' => $callback,
					'show_in_rest'      => false,
				]
			);
		}
	}

	// ── Sections ──────────────────────────────────────────────────────────────

	private static function register_sections(): void {
		$sections = [
			'sbg_section_api_keys'   => __( '🔑 API Keys',           'smart-blog-generator' ),
			'sbg_section_generation' => __( '⚙️ Generation Settings', 'smart-blog-generator' ),
			'sbg_section_content'    => __( '📝 Content Defaults',    'smart-blog-generator' ),
			'sbg_section_image'      => __( '🖼️ Image Settings',      'smart-blog-generator' ),
		];

		foreach ( $sections as $id => $title ) {
			add_settings_section(
				$id,
				$title,
				[ self::class, 'render_section_' . str_replace( 'sbg_section_', '', $id ) ],
				self::PAGE_SLUG
			);
		}
	}

	// ── Fields ────────────────────────────────────────────────────────────────

	private static function register_fields(): void {
		$fields = [
			// Section: api_keys.
			[ 'sbg_anthropic_api_key',   __( 'Anthropic API Key',   'smart-blog-generator' ), 'render_field_anthropic_key',   'sbg_section_api_keys' ],
			[ 'sbg_unsplash_access_key', __( 'Unsplash Access Key', 'smart-blog-generator' ), 'render_field_unsplash_key',    'sbg_section_api_keys' ],

			// Section: generation.
			[ 'sbg_anthropic_model',  __( 'Claude Model',          'smart-blog-generator' ), 'render_field_model',         'sbg_section_generation' ],
			[ 'sbg_max_tokens',       __( 'Max Tokens',             'smart-blog-generator' ), 'render_field_max_tokens',    'sbg_section_generation' ],
			[ 'sbg_api_timeout',      __( 'API Timeout (seconds)',  'smart-blog-generator' ), 'render_field_api_timeout',   'sbg_section_generation' ],
			[ 'sbg_word_count_min',   __( 'Min Word Count',         'smart-blog-generator' ), 'render_field_word_min',      'sbg_section_generation' ],
			[ 'sbg_word_count_max',   __( 'Max Word Count',         'smart-blog-generator' ), 'render_field_word_max',      'sbg_section_generation' ],
			[ 'sbg_faq_count',        __( 'FAQ Item Count',         'smart-blog-generator' ), 'render_field_faq_count',     'sbg_section_generation' ],
			[ 'sbg_link_count',       __( 'Internal Link Count',    'smart-blog-generator' ), 'render_field_link_count',    'sbg_section_generation' ],

			// Section: content.
			[ 'sbg_default_tone',     __( 'Default Tone',           'smart-blog-generator' ), 'render_field_default_tone',  'sbg_section_content' ],
			[ 'sbg_default_category', __( 'Default Category',       'smart-blog-generator' ), 'render_field_default_cat',   'sbg_section_content' ],
			[ 'sbg_post_status',      __( 'Save Post As',           'smart-blog-generator' ), 'render_field_post_status',   'sbg_section_content' ],

			// Section: image.
			[ 'sbg_image_orientation', __( 'Image Orientation',     'smart-blog-generator' ), 'render_field_orientation',   'sbg_section_image' ],
			[ 'sbg_image_size',        __( 'Image Size',            'smart-blog-generator' ), 'render_field_image_size',    'sbg_section_image' ],
		];

		foreach ( $fields as [ $id, $label, $cb, $section ] ) {
			add_settings_field( $id, $label, [ self::class, $cb ], self::PAGE_SLUG, $section );
		}
	}

	// -------------------------------------------------------------------------
	// Section description renderers
	// -------------------------------------------------------------------------

	public static function render_section_api_keys(): void {
		echo '<p>' . esc_html__( 'Secret credentials stored in wp_options. Never exposed via REST or front end.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_section_generation(): void {
		echo '<p>' . esc_html__( 'Control how the Claude API generates content — model choice, token budget, timeouts, and the structure of each post.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_section_content(): void {
		echo '<p>' . esc_html__( 'Default values pre-selected on the generator form. Editors can still override them per post.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_section_image(): void {
		echo '<p>' . esc_html__( 'Controls the Unsplash photo query. Has no effect if no Unsplash key is configured.', 'smart-blog-generator' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Field renderers — API Keys
	// -------------------------------------------------------------------------

	public static function render_field_anthropic_key(): void {
		$val = esc_attr( get_option( 'sbg_anthropic_api_key', '' ) );
		echo '<input type="password" id="sbg_anthropic_api_key" name="sbg_anthropic_api_key"
			class="regular-text" value="' . $val . '" autocomplete="new-password" spellcheck="false" />';
		echo '<p class="description">' . esc_html__( 'Required. Obtain from console.anthropic.com → API Keys.', 'smart-blog-generator' ) . '</p>';
		if ( '' === get_option( 'sbg_anthropic_api_key', '' ) ) {
			echo '<p class="description" style="color:#d63638;">'
				. esc_html__( '⚠ Not set — post generation is disabled.', 'smart-blog-generator' )
				. '</p>';
		}
	}

	public static function render_field_unsplash_key(): void {
		$val = esc_attr( get_option( 'sbg_unsplash_access_key', '' ) );
		echo '<input type="password" id="sbg_unsplash_access_key" name="sbg_unsplash_access_key"
			class="regular-text" value="' . $val . '" autocomplete="new-password" spellcheck="false" />';
		echo '<p class="description">' . esc_html__( 'Optional. From unsplash.com/developers → New Application → Access Key. Leave blank to skip featured images.', 'smart-blog-generator' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Field renderers — Generation Settings
	// -------------------------------------------------------------------------

	public static function render_field_model(): void {
		$val = esc_attr( get_option( 'sbg_anthropic_model', SBG_ANTHROPIC_MODEL ) );
		// Well-known current models as quick-select options; free-text input
		// allows any future model ID without a plugin update.
		$known = [
			'claude-sonnet-4-20250514' => 'claude-sonnet-4-20250514 (default)',
			'claude-opus-4-20250514'   => 'claude-opus-4-20250514 (most capable)',
			'claude-haiku-4-5-20251001'=> 'claude-haiku-4-5 (fastest / cheapest)',
		];
		echo '<select id="sbg_anthropic_model" name="sbg_anthropic_model" class="regular-text">';
		foreach ( $known as $id => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $id ),
				selected( $val, $id, false ),
				esc_html( $label )
			);
		}
		// If the saved value is not in the known list, add it as a selected option.
		if ( ! array_key_exists( $val, $known ) && '' !== $val ) {
			printf( '<option value="%s" selected>%s</option>', esc_attr( $val ), esc_html( $val ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'The Anthropic model used for all generation requests.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_max_tokens(): void {
		$val = (int) get_option( 'sbg_max_tokens', 4096 );
		echo '<input type="number" id="sbg_max_tokens" name="sbg_max_tokens"
			class="small-text" value="' . esc_attr( $val ) . '" min="512" max="8192" step="256" />';
		echo '<p class="description">' . esc_html__( 'Maximum tokens the model may use in its response. Range: 512–8192. Default: 4096.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_api_timeout(): void {
		$val = (int) get_option( 'sbg_api_timeout', 90 );
		echo '<input type="number" id="sbg_api_timeout" name="sbg_api_timeout"
			class="small-text" value="' . esc_attr( $val ) . '" min="30" max="300" step="10" />';
		echo '<p class="description">' . esc_html__( 'Seconds to wait for the Anthropic API before timing out. Range: 30–300. Default: 90.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_word_min(): void {
		$val = (int) get_option( 'sbg_word_count_min', 800 );
		echo '<input type="number" id="sbg_word_count_min" name="sbg_word_count_min"
			class="small-text" value="' . esc_attr( $val ) . '" min="200" max="3000" step="50" />';
		echo '<p class="description">' . esc_html__( 'Minimum target word count for the post body. Default: 800.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_word_max(): void {
		$val = (int) get_option( 'sbg_word_count_max', 1200 );
		echo '<input type="number" id="sbg_word_count_max" name="sbg_word_count_max"
			class="small-text" value="' . esc_attr( $val ) . '" min="300" max="5000" step="50" />';
		echo '<p class="description">' . esc_html__( 'Maximum target word count for the post body. Default: 1200.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_faq_count(): void {
		$val = (int) get_option( 'sbg_faq_count', 5 );
		echo '<input type="number" id="sbg_faq_count" name="sbg_faq_count"
			class="small-text" value="' . esc_attr( $val ) . '" min="3" max="10" step="1" />';
		echo '<p class="description">' . esc_html__( 'Number of FAQ items to generate per post. Range: 3–10. Default: 5.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_link_count(): void {
		$val = (int) get_option( 'sbg_link_count', 3 );
		echo '<input type="number" id="sbg_link_count" name="sbg_link_count"
			class="small-text" value="' . esc_attr( $val ) . '" min="1" max="6" step="1" />';
		echo '<p class="description">' . esc_html__( 'Number of internal-link placeholders ([LINK:…]) to embed in the content. Range: 1–6. Default: 3.', 'smart-blog-generator' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Field renderers — Content Defaults
	// -------------------------------------------------------------------------

	public static function render_field_default_tone(): void {
		$val = get_option( 'sbg_default_tone', 'informational' );
		$tones = [
			'informational' => __( 'Informational — neutral educational prose', 'smart-blog-generator' ),
			'how-to'        => __( 'How-To — step-by-step instructions', 'smart-blog-generator' ),
			'listicle'      => __( 'Listicle — list-based format', 'smart-blog-generator' ),
		];
		echo '<select id="sbg_default_tone" name="sbg_default_tone">';
		foreach ( $tones as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $val, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Pre-selected tone on the generator form. Editors can change it per post.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_default_cat(): void {
		$val = (int) get_option( 'sbg_default_category', 0 );
		wp_dropdown_categories( [
			'name'              => 'sbg_default_category',
			'id'                => 'sbg_default_category',
			'selected'          => $val,
			'show_option_none'  => __( '— None (Uncategorized) —', 'smart-blog-generator' ),
			'option_none_value' => '0',
			'hide_empty'        => false,
			'orderby'           => 'name',
		] );
		echo '<p class="description">' . esc_html__( 'Category pre-selected on the generator form. Editors can override per post.', 'smart-blog-generator' ) . '</p>';
	}

	public static function render_field_post_status(): void {
		$val = get_option( 'sbg_post_status', 'draft' );
		$statuses = [
			'draft'   => __( 'Draft — saved for review before publishing', 'smart-blog-generator' ),
			'pending' => __( 'Pending Review — appears in the review queue', 'smart-blog-generator' ),
		];
		echo '<select id="sbg_post_status" name="sbg_post_status">';
		foreach ( $statuses as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $val, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'WordPress post status assigned to every generated post. "Draft" is safest — posts are never live until manually published.', 'smart-blog-generator' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Field renderers — Image Settings
	// -------------------------------------------------------------------------

	public static function render_field_orientation(): void {
		$val = get_option( 'sbg_image_orientation', 'landscape' );
		$opts = [
			'landscape' => __( 'Landscape (wide) — best for post headers', 'smart-blog-generator' ),
			'portrait'  => __( 'Portrait (tall)', 'smart-blog-generator' ),
			'squarish'  => __( 'Squarish', 'smart-blog-generator' ),
		];
		echo '<select id="sbg_image_orientation" name="sbg_image_orientation">';
		foreach ( $opts as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $val, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public static function render_field_image_size(): void {
		$val = get_option( 'sbg_image_size', 'regular' );
		$opts = [
			'thumb'   => __( 'Thumb (~200px) — smallest file', 'smart-blog-generator' ),
			'small'   => __( 'Small (~400px)', 'smart-blog-generator' ),
			'regular' => __( 'Regular (~1080px) — recommended', 'smart-blog-generator' ),
			'full'    => __( 'Full (original resolution) — largest file', 'smart-blog-generator' ),
		];
		echo '<select id="sbg_image_size" name="sbg_image_size">';
		foreach ( $opts as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $val, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Unsplash image size to download. "Regular" (~1080px wide) balances quality and file size.', 'smart-blog-generator' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Sanitize callbacks
	// -------------------------------------------------------------------------

	public static function sanitize_model( string $val ): string {
		$val = sanitize_text_field( $val );
		return '' !== $val ? $val : SBG_ANTHROPIC_MODEL;
	}

	public static function sanitize_max_tokens( $val ): int {
		$val = (int) $val;
		return max( 512, min( 8192, $val ) );
	}

	public static function sanitize_api_timeout( $val ): int {
		$val = (int) $val;
		return max( 30, min( 300, $val ) );
	}

	public static function sanitize_word_count_min( $val ): int {
		$val = (int) $val;
		return max( 200, min( 3000, $val ) );
	}

	public static function sanitize_word_count_max( $val ): int {
		$val = (int) $val;
		return max( 300, min( 5000, $val ) );
	}

	public static function sanitize_faq_count( $val ): int {
		$val = (int) $val;
		return max( 3, min( 10, $val ) );
	}

	public static function sanitize_link_count( $val ): int {
		$val = (int) $val;
		return max( 1, min( 6, $val ) );
	}

	public static function sanitize_tone( string $val ): string {
		$allowed = [ 'informational', 'how-to', 'listicle' ];
		return in_array( $val, $allowed, true ) ? $val : 'informational';
	}

	public static function sanitize_post_status( string $val ): string {
		return in_array( $val, [ 'draft', 'pending' ], true ) ? $val : 'draft';
	}

	public static function sanitize_orientation( string $val ): string {
		return in_array( $val, [ 'landscape', 'portrait', 'squarish' ], true ) ? $val : 'landscape';
	}

	public static function sanitize_image_size( string $val ): string {
		return in_array( $val, [ 'thumb', 'small', 'regular', 'full' ], true ) ? $val : 'regular';
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	/** Renders the full settings page. */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-blog-generator' ) );
		}
		?>
		<div class="wrap sbg-wrap">
			<h1><?php esc_html_e( 'Smart Blog Generator — Settings', 'smart-blog-generator' ); ?></h1>

			<?php settings_errors( self::OPTION_GROUP ); ?>

			<form method="post" action="options.php" novalidate="novalidate">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'smart-blog-generator' ) );
				?>
			</form>

			<p style="margin-top:24px;color:#50575e;">
				<?php
				printf(
					esc_html__( 'Plugin version %1$s · Default model: %2$s', 'smart-blog-generator' ),
					esc_html( SBG_VERSION ),
					'<strong>' . esc_html( SBG_ANTHROPIC_MODEL ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
