<?php
/**
 * SBG_API_Handler
 *
 * Sends a structured prompt to the Anthropic Messages API and enforces a
 * strict JSON contract on the response. Every field in the expected schema is
 * validated before data is passed to the rest of the plugin. Any deviation
 * from the schema — missing keys, wrong types, empty required strings — is
 * surfaced as a WP_Error so callers can display a meaningful admin notice.
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Anthropic API communication and response validation.
 */
class SBG_API_Handler {

	/** Anthropic API version header — this is an API protocol version, not the model. */
	private const ANTHROPIC_VERSION = '2023-06-01';

	/** @var string The Anthropic API key. */
	private string $api_key;

	/** @var string Model ID read from wp_options at construction time. */
	private string $model;

	/** @var int Max tokens read from wp_options. */
	private int $max_tokens;

	/** @var int HTTP timeout in seconds read from wp_options. */
	private int $http_timeout;

	/** @var int Minimum target word count for the prompt. */
	private int $word_count_min;

	/** @var int Maximum target word count for the prompt. */
	private int $word_count_max;

	/** @var int Number of FAQ items to request. */
	private int $faq_count;

	/** @var int Number of internal-link placeholders to request. */
	private int $link_count;

	/**
	 * Constructor — reads all generation options from wp_options so that
	 * admin-configured values are always used without needing to pass them
	 * through every method call.
	 *
	 * @param string $api_key Anthropic secret key from wp_options.
	 */
	public function __construct( string $api_key ) {
		$this->api_key        = $api_key;
		$this->model          = (string) get_option( 'sbg_anthropic_model',  SBG_ANTHROPIC_MODEL );
		$this->max_tokens     = (int)    get_option( 'sbg_max_tokens',       4096 );
		$this->http_timeout   = (int)    get_option( 'sbg_api_timeout',      90 );
		$this->word_count_min = (int)    get_option( 'sbg_word_count_min',   800 );
		$this->word_count_max = (int)    get_option( 'sbg_word_count_max',   1200 );
		$this->faq_count      = (int)    get_option( 'sbg_faq_count',        5 );
		$this->link_count     = (int)    get_option( 'sbg_link_count',       3 );
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Generates a complete blog post for the given inputs.
	 *
	 * @param string $topic   Human-readable post topic.
	 * @param string $keyword Primary SEO target keyword.
	 * @param string $tone    One of: informational | how-to | listicle.
	 *
	 * @return array{
	 *   seo_title: string,
	 *   meta_description: string,
	 *   h1: string,
	 *   content_html: string,
	 *   faq: list<array{question: string, answer: string}>,
	 *   internal_links: list<array{anchor: string, target_keyword: string}>
	 * }|WP_Error
	 */
	public function generate( string $topic, string $keyword, string $tone ): array|WP_Error {
		$prompt   = $this->build_prompt( $topic, $keyword, $tone );
		$response = $this->send_request( $prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_and_validate( $response );
	}

	// -------------------------------------------------------------------------
	// Prompt construction
	// -------------------------------------------------------------------------

	/**
	 * Builds the prompt that instructs the model to return a strict JSON object.
	 *
	 * The schema is spelled out verbatim with type annotations so the model has
	 * no ambiguity about what is expected. Tone-specific instructions are
	 * injected separately to keep the schema block clean.
	 *
	 * @param string $topic   Post topic.
	 * @param string $keyword Target keyword.
	 * @param string $tone    Desired tone.
	 *
	 * @return string Complete prompt text.
	 */
	private function build_prompt( string $topic, string $keyword, string $tone ): string {
		$tone_instruction = $this->tone_instruction( $tone );

		// Interpolate admin-configured values into the prompt so the model
		// always targets the word count, FAQ count, and link count set in Settings.
		$word_range = "{$this->word_count_min}–{$this->word_count_max}";
		$faq_count  = $this->faq_count;
		$link_count = $this->link_count;

		// The prompt uses a heredoc for readability. Indentation is intentional
		// (no leading spaces in the API call — models are sensitive to whitespace).
		return <<<PROMPT
You are a professional SEO content writer. Your ONLY output must be a single, valid JSON object — no markdown fences, no commentary, no explanation. Return nothing except the JSON.

## Assignment
- Topic:   {$topic}
- Keyword: {$keyword}
- Tone:    {$tone_instruction}

## Required JSON schema (return EXACTLY this structure, all fields required)
{
  "seo_title":        "<string — max 60 chars, include keyword, compelling>",
  "meta_description": "<string — max 160 chars, include keyword, ends with a CTA>",
  "h1":               "<string — engaging reader-facing headline, may differ from seo_title>",
  "content_html":     "<string — full HTML body, rules below>",
  "faq": [
    { "question": "<string>", "answer": "<string — 1 to 3 sentences>" }
  ],
  "internal_links": [
    { "anchor": "<string — exact anchor text used in content_html>", "target_keyword": "<string>" }
  ]
}

## content_html rules
- {$word_range} words.
- Use semantic HTML only: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>.
- Do NOT include <html>, <head>, <body>, <script>, or <style> tags.
- The very first <p> element MUST contain the exact phrase "{$keyword}".
- The H1 is in a separate field — do NOT include an <h1> in content_html.
- Include exactly {$link_count} internal-link placeholders using this format: [LINK:anchor text here]. Use natural anchor text that describes a related article. List each anchor text in the internal_links array.
- Write in active voice. Vary sentence length. Aim for a Flesch reading ease above 60.
- Include 4–6 <h2> sections. Close every opened tag properly.
- End with a concise conclusion <h2> and closing paragraph.

## faq rules
- Exactly {$faq_count} items.
- Questions must be phrased conversationally (long-tail, as a real user would ask).
- Answers must be factual, 1–3 sentences, and contain a natural variation of "{$keyword}".
- Do NOT duplicate any question.

## internal_links rules
- List all {$link_count} anchors from content_html here with a suggested target_keyword.

Return ONLY the raw JSON object. No other text whatsoever.
PROMPT;
	}

	/**
	 * Returns a one-sentence tone instruction for the prompt.
	 *
	 * @param string $tone Tone identifier.
	 *
	 * @return string Human-readable tone instruction.
	 */
	private function tone_instruction( string $tone ): string {
		return match ( $tone ) {
			'how-to'   => 'Step-by-step instructional — use numbered lists and clear action verbs.',
			'listicle' => 'List-based format (e.g. "10 Ways to…") — rely heavily on numbered or bullet lists.',
			default    => 'Informational — neutral, educational prose for a general audience.',
		};
	}

	// -------------------------------------------------------------------------
	// HTTP communication
	// -------------------------------------------------------------------------

	/**
	 * POSTs the prompt to the Anthropic Messages API and returns the decoded
	 * response body, or WP_Error on any failure.
	 *
	 * Uses WordPress's wp_remote_post() so the HTTP transport layer (cURL or
	 * streams) is determined by WordPress and respects the server's SSL, proxy,
	 * and timeout configuration.
	 *
	 * @param string $prompt The fully assembled prompt.
	 *
	 * @return array|WP_Error Decoded JSON response body or WP_Error.
	 */
	private function send_request( string $prompt ): array|WP_Error {
		// Build the Messages API payload using admin-configured model and token budget.
		$body = wp_json_encode( [
			'model'      => $this->model,
			'max_tokens' => $this->max_tokens,
			'messages'   => [
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
		] );

		if ( false === $body ) {
			return new WP_Error( 'sbg_encode_error', __( 'Failed to encode API request body.', 'smart-blog-generator' ) );
		}

		$response = wp_remote_post(
			SBG_ANTHROPIC_ENDPOINT,
			[
				'headers' => [
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::ANTHROPIC_VERSION,
				],
				'body'    => $body,
				'timeout' => $this->http_timeout,
			]
		);

		// wp_remote_post returns WP_Error on transport failures (DNS, timeout…).
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'sbg_transport_error',
				sprintf(
					/* translators: %s: underlying WP HTTP error message */
					__( 'Could not reach Anthropic API: %s', 'smart-blog-generator' ),
					$response->get_error_message()
				)
			);
		}

		$http_code   = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		// Anything other than 200 is an Anthropic-side error (auth, rate limit…).
		if ( 200 !== $http_code ) {
			$api_message = is_array( $decoded ) && isset( $decoded['error']['message'] )
				? $decoded['error']['message']
				: $raw_body;

			return new WP_Error(
				'sbg_api_http_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: API error message */
					__( 'Anthropic API responded with HTTP %1$d: %2$s', 'smart-blog-generator' ),
					$http_code,
					sanitize_text_field( (string) $api_message )
				)
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'sbg_response_decode_error',
				__( 'API response could not be decoded as JSON.', 'smart-blog-generator' )
			);
		}

		return $decoded;
	}

	// -------------------------------------------------------------------------
	// Response parsing and strict validation
	// -------------------------------------------------------------------------

	/**
	 * Extracts the model's text output from the API envelope, decodes it as
	 * JSON, validates the strict schema, and sanitizes every field before
	 * returning a clean PHP array.
	 *
	 * The Anthropic Messages API wraps the model's reply in:
	 *   response['content'][0]['text']
	 *
	 * The text itself must be a JSON object matching the schema in build_prompt().
	 *
	 * @param array $api_response Raw decoded response from send_request().
	 *
	 * @return array|WP_Error Structured post data or WP_Error if validation fails.
	 */
	private function parse_and_validate( array $api_response ): array|WP_Error {
		// Navigate the Messages API envelope.
		$text = $api_response['content'][0]['text'] ?? '';

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'sbg_empty_content', __( 'The API returned an empty content block.', 'smart-blog-generator' ) );
		}

		// Strip accidental markdown code fences the model may still produce
		// despite instructions (e.g. ```json … ```).
		$text = preg_replace( '/^\s*```(?:json)?\s*/i', '', trim( $text ) );
		$text = preg_replace( '/\s*```\s*$/i', '', $text );

		$data = json_decode( $text, true );

		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'sbg_json_parse_error',
				__( 'API response was not valid JSON. Please try generating again.', 'smart-blog-generator' )
			);
		}

		// ------------------------------------------------------------------
		// Validate required scalar fields
		// ------------------------------------------------------------------
		$scalar_fields = [ 'seo_title', 'meta_description', 'h1', 'content_html' ];

		foreach ( $scalar_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || '' === trim( $data[ $field ] ) ) {
				return new WP_Error(
					'sbg_missing_field',
					sprintf(
						/* translators: %s: field name */
						__( 'API response is missing or empty required field: "%s". Try again.', 'smart-blog-generator' ),
						$field
					)
				);
			}
		}

		// ------------------------------------------------------------------
		// Validate faq array
		// ------------------------------------------------------------------
		if ( ! isset( $data['faq'] ) || ! is_array( $data['faq'] ) ) {
			return new WP_Error( 'sbg_missing_faq', __( 'API response is missing the "faq" array.', 'smart-blog-generator' ) );
		}

		foreach ( $data['faq'] as $i => $item ) {
			if ( ! is_array( $item )
				|| empty( $item['question'] ) || ! is_string( $item['question'] )
				|| empty( $item['answer'] )   || ! is_string( $item['answer'] )
			) {
				return new WP_Error(
					'sbg_invalid_faq_item',
					sprintf(
						/* translators: %d: FAQ item index (1-based) */
						__( 'FAQ item #%d is malformed (expected "question" and "answer" strings).', 'smart-blog-generator' ),
						$i + 1
					)
				);
			}
		}

		// ------------------------------------------------------------------
		// Validate internal_links array
		// ------------------------------------------------------------------
		if ( ! isset( $data['internal_links'] ) || ! is_array( $data['internal_links'] ) ) {
			return new WP_Error( 'sbg_missing_links', __( 'API response is missing the "internal_links" array.', 'smart-blog-generator' ) );
		}

		foreach ( $data['internal_links'] as $i => $link ) {
			if ( ! is_array( $link )
				|| empty( $link['anchor'] )          || ! is_string( $link['anchor'] )
				|| empty( $link['target_keyword'] )  || ! is_string( $link['target_keyword'] )
			) {
				return new WP_Error(
					'sbg_invalid_link_item',
					sprintf(
						/* translators: %d: link item index (1-based) */
						__( 'internal_links item #%d is malformed (expected "anchor" and "target_keyword").', 'smart-blog-generator' ),
						$i + 1
					)
				);
			}
		}

		// ------------------------------------------------------------------
		// Sanitize and enforce character limits
		// ------------------------------------------------------------------

		// Truncate seo_title and meta_description to spec limits rather than
		// erroring out — a graceful degradation.
		$seo_title        = sanitize_text_field( mb_substr( trim( $data['seo_title'] ), 0, 60 ) );
		$meta_description = sanitize_text_field( mb_substr( trim( $data['meta_description'] ), 0, 160 ) );
		$h1               = sanitize_text_field( trim( $data['h1'] ) );

		// content_html contains trusted HTML generated by the model but must
		// be stripped of any dangerous tags before storing.
		$content_html = wp_kses_post( $data['content_html'] );

		// Sanitize each FAQ item.
		$faq = array_values( array_map(
			static fn( array $item ) => [
				'question' => sanitize_text_field( $item['question'] ),
				'answer'   => sanitize_text_field( $item['answer'] ),
			],
			$data['faq']
		) );

		// Sanitize each internal link item.
		$internal_links = array_values( array_map(
			static fn( array $link ) => [
				'anchor'          => sanitize_text_field( $link['anchor'] ),
				'target_keyword'  => sanitize_text_field( $link['target_keyword'] ),
			],
			$data['internal_links']
		) );

		return [
			'seo_title'        => $seo_title,
			'meta_description' => $meta_description,
			'h1'               => $h1,
			'content_html'     => $content_html,
			'faq'              => $faq,
			'internal_links'   => $internal_links,
		];
	}
}
