<?php
/**
 * SBG_Post_Creator
 *
 * Assembles a WordPress draft post from the validated content array returned
 * by SBG_API_Handler, then persists everything to the database.
 *
 * Responsibilities
 * ----------------
 * 1. Build the full post_content string:
 *    – H1 heading
 *    – Main body HTML (with [LINK:…] placeholders converted to editor hints)
 *    – Visible FAQ definition list
 * 2. Insert the draft post via wp_insert_post().
 * 3. Assign the chosen category via wp_set_object_terms().
 * 4. Set the featured image if an attachment ID was provided.
 * 5. Save Yoast SEO meta fields (harmless if Yoast is inactive — they are
 *    just post meta keys that Yoast reads when present).
 * 6. Save the FAQ JSON-LD schema to post meta for front-end output via
 *    the wp_head hook registered in the main plugin file.
 * 7. Save plugin bookkeeping meta (keyword, generation timestamp, etc.).
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a WordPress draft post from generated blog content.
 */
class SBG_Post_Creator {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Creates the draft post and returns its ID.
	 *
	 * @param array  $content       Validated data from SBG_API_Handler::generate().
	 *                              Keys: seo_title, meta_description, h1,
	 *                                    content_html, faq, internal_links.
	 * @param string $keyword       The target SEO keyword.
	 * @param int    $attachment_id Featured image attachment ID (0 = none).
	 * @param int    $category_id   Category term ID to assign (0 = Uncategorized).
	 *
	 * @return int|WP_Error New post ID, or WP_Error on failure.
	 */
	public function create(
		array  $content,
		string $keyword,
		int    $attachment_id,
		int    $category_id
	): int|WP_Error {
		// Build the HTML that will be stored as post_content.
		$post_content = $this->build_post_content( $content );

		// wp_insert_post() expects slashed data — wp_slash() handles that.
		// Read post status from Settings → Content Defaults (draft or pending).
		$post_status = (string) get_option( 'sbg_post_status', 'draft' );
		$allowed_statuses = [ 'draft', 'pending' ];
		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
			$post_status = 'draft';
		}

		$postarr = [
			'post_title'   => $content['h1'],
			'post_content' => $post_content,
			'post_excerpt' => $content['meta_description'],
			'post_status'  => $post_status,
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		];

		$post_id = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Assign category.
		$this->assign_category( $post_id, $category_id );

		// Set featured image if we have an attachment.
		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );

			// Attach the media item to the post so it appears in the editor's
			// media panel and is included in post-specific media queries.
			wp_update_post( [
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			] );
		}

		// Save Yoast SEO-compatible meta fields.
		$this->save_seo_meta( $post_id, $content, $keyword );

		// Save the FAQ JSON-LD schema to post meta (output via wp_head hook).
		$this->save_faq_schema( $post_id, $content['faq'] );

		// Save plugin bookkeeping meta used by the SEO meta box.
		$this->save_plugin_meta( $post_id, $keyword, $content['internal_links'] );

		return $post_id;
	}

	// -------------------------------------------------------------------------
	// Post content assembly
	// -------------------------------------------------------------------------

	/**
	 * Combines H1, body content, and the FAQ HTML section into the final
	 * post_content string.
	 *
	 * [LINK:anchor text] placeholders in content_html are converted to an
	 * HTML span with a data-attribute so editors can visually locate them and
	 * replace them with real internal links before publishing.
	 *
	 * @param array $content Structured content from the API handler.
	 *
	 * @return string Full HTML post body.
	 */
	private function build_post_content( array $content ): string {
		$parts = [];

		// H1 heading — kept in content so block editor users see it in preview.
		$parts[] = '<h1>' . esc_html( $content['h1'] ) . '</h1>';

		// Body content: replace [LINK:anchor] with a styled placeholder span.
		$body = $this->replace_link_placeholders( $content['content_html'] );
		$parts[] = $body;

		// Visible FAQ section using a definition list.
		if ( ! empty( $content['faq'] ) ) {
			$parts[] = $this->build_faq_html( $content['faq'] );
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Replaces [LINK:anchor text] placeholders with a visually distinct span
	 * that editors can find and convert to real links before publishing.
	 *
	 * Example input:  "…read our guide on [LINK:puppy training basics]…"
	 * Example output: "…read our guide on <span class="sbg-link-placeholder"
	 *                    data-anchor="puppy training basics"
	 *                    title="Internal link placeholder: puppy training basics">
	 *                    [Internal Link: puppy training basics]</span>…"
	 *
	 * @param string $html Content HTML from the API.
	 *
	 * @return string HTML with placeholders replaced.
	 */
	private function replace_link_placeholders( string $html ): string {
		return preg_replace_callback(
			'/\[LINK:([^\]]+)\]/i',
			static function ( array $matches ): string {
				$anchor = sanitize_text_field( $matches[1] );
				return sprintf(
					'<span class="sbg-link-placeholder" data-anchor="%1$s" title="%2$s">[Internal Link: %1$s]</span>',
					esc_attr( $anchor ),
					esc_attr(
						sprintf(
							/* translators: %s: anchor text */
							__( 'Internal link placeholder: %s', 'smart-blog-generator' ),
							$anchor
						)
					)
				);
			},
			$html
		) ?? $html;
	}

	/**
	 * Builds an accessible HTML FAQ section using a definition list.
	 *
	 * The JSON-LD schema for the FAQ is stored separately in post meta
	 * (see save_faq_schema()) and output via the wp_head hook, because
	 * <script> tags are stripped by wp_kses_post during post saving.
	 *
	 * @param array $faq Array of { question: string, answer: string } items.
	 *
	 * @return string HTML definition-list FAQ block.
	 */
	private function build_faq_html( array $faq ): string {
		$html  = '<h2>' . esc_html__( 'Frequently Asked Questions', 'smart-blog-generator' ) . "</h2>\n";
		$html .= '<dl class="sbg-faq">' . "\n";

		foreach ( $faq as $item ) {
			$q = sanitize_text_field( $item['question'] ?? '' );
			$a = sanitize_text_field( $item['answer']   ?? '' );

			if ( '' === $q || '' === $a ) {
				continue;
			}

			$html .= "\t<dt><strong>" . esc_html( $q ) . "</strong></dt>\n";
			$html .= "\t<dd>" . esc_html( $a ) . "</dd>\n";
		}

		$html .= '</dl>';

		return $html;
	}

	// -------------------------------------------------------------------------
	// Category assignment
	// -------------------------------------------------------------------------

	/**
	 * Assigns the post to the specified category.
	 *
	 * Falls back to WordPress's built-in "Uncategorized" (term ID 1) if the
	 * provided ID is 0 or does not correspond to a real category term.
	 *
	 * @param int $post_id     Post ID.
	 * @param int $category_id Desired category term ID.
	 */
	private function assign_category( int $post_id, int $category_id ): void {
		$term_id = ( $category_id > 0 && term_exists( $category_id, 'category' ) )
			? $category_id
			: 1; // WordPress default "Uncategorized".

		wp_set_object_terms( $post_id, [ $term_id ], 'category' );
	}

	// -------------------------------------------------------------------------
	// SEO meta — Yoast-compatible (also works with RankMath)
	// -------------------------------------------------------------------------

	/**
	 * Saves SEO post meta using keys that Yoast SEO reads natively.
	 *
	 * Writing these keys is safe whether or not Yoast is installed — they are
	 * plain post meta entries and have no side effects when Yoast is absent.
	 * When Yoast is active, it will automatically display these values in the
	 * SEO analysis panel when the editor opens the draft.
	 *
	 * RankMath uses different meta keys; we write both in parallel so the post
	 * works regardless of which SEO plugin is active.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $content Content array (seo_title, meta_description).
	 * @param string $keyword Focus keyword.
	 */
	private function save_seo_meta( int $post_id, array $content, string $keyword ): void {
		// ── Yoast SEO meta keys ───────────────────────────────────────────────
		update_post_meta( $post_id, '_yoast_wpseo_focuskw',  sanitize_text_field( $keyword ) );
		update_post_meta( $post_id, '_yoast_wpseo_title',    sanitize_text_field( $content['seo_title'] ) );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $content['meta_description'] ) );

		// Reset Yoast's cached content score so it recalculates on next edit.
		update_post_meta( $post_id, '_yoast_wpseo_content_score', '' );

		// ── RankMath meta keys ────────────────────────────────────────────────
		update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $keyword ) );
		update_post_meta( $post_id, 'rank_math_title',         sanitize_text_field( $content['seo_title'] ) );
		update_post_meta( $post_id, 'rank_math_description',   sanitize_text_field( $content['meta_description'] ) );
	}

	// -------------------------------------------------------------------------
	// FAQ JSON-LD schema
	// -------------------------------------------------------------------------

	/**
	 * Encodes the FAQ array as a FAQPage JSON-LD schema object and stores it
	 * in post meta.
	 *
	 * The schema is output in <head> via the sbg_output_faq_schema() function
	 * hooked to wp_head in smart-blog-generator.php. We store it as meta
	 * rather than embedding it in post_content because wp_kses_post strips
	 * <script> tags during post saving.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $faq     Array of { question, answer } items.
	 */
	private function save_faq_schema( int $post_id, array $faq ): void {
		if ( empty( $faq ) ) {
			return;
		}

		// Build the FAQPage schema entity array.
		$entities = [];

		foreach ( $faq as $item ) {
			$q = sanitize_text_field( $item['question'] ?? '' );
			$a = sanitize_text_field( $item['answer']   ?? '' );

			if ( '' === $q || '' === $a ) {
				continue;
			}

			$entities[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					// Schema.org answers must be plain text (no HTML).
					'text'  => wp_strip_all_tags( $a ),
				],
			];
		}

		if ( empty( $entities ) ) {
			return;
		}

		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];

		// JSON_UNESCAPED_UNICODE prevents unnecessary \uXXXX sequences.
		// JSON_UNESCAPED_SLASHES keeps URLs readable.
		$json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		if ( false !== $json ) {
			update_post_meta( $post_id, '_sbg_faq_schema', $json );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin bookkeeping meta
	// -------------------------------------------------------------------------

	/**
	 * Saves plugin-specific meta used by the SEO indicator meta box and for
	 * general bookkeeping (audit trail of generated posts).
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $keyword        Target keyword.
	 * @param array  $internal_links Internal link suggestions from the API.
	 */
	private function save_plugin_meta( int $post_id, string $keyword, array $internal_links ): void {
		// Mark the post as SBG-generated so the meta box knows to render.
		update_post_meta( $post_id, '_sbg_generated',      '1' );
		update_post_meta( $post_id, '_sbg_keyword',         sanitize_text_field( $keyword ) );
		update_post_meta( $post_id, '_sbg_generated_at',    current_time( 'mysql' ) );

		// Serialised internal-link suggestions for the editor's reference.
		// These are informational — the editor decides which links to keep.
		if ( ! empty( $internal_links ) ) {
			// Each element is already sanitized by SBG_API_Handler.
			update_post_meta( $post_id, '_sbg_internal_links', $internal_links );
		}
	}
}
