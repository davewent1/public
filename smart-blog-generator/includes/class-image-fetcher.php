<?php
/**
 * SBG_Image_Fetcher
 *
 * Searches Unsplash for a landscape photo matching the target keyword,
 * downloads it into the WordPress temp directory, and sideloads it into the
 * Media Library using WordPress core helpers.
 *
 * All failures are returned as WP_Error instances so the calling code (the
 * admin controller) can decide whether to surface a notice or silently skip
 * the featured image without aborting the entire post-creation flow.
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches a relevant photo from Unsplash and inserts it into the Media Library.
 */
class SBG_Image_Fetcher {

	/** Number of results to request from Unsplash (we pick the first). */
	private const SEARCH_PER_PAGE = 1;

	/** Preferred image orientation. */
	private const ORIENTATION = 'landscape';

	/** HTTP timeout for the Unsplash search request. */
	private const SEARCH_TIMEOUT = 15;

	/** HTTP timeout for the image download (larger file). */
	private const DOWNLOAD_TIMEOUT = 30;

	/** @var string Unsplash Access Key. */
	private string $access_key;

	/**
	 * @param string $access_key Unsplash API access key from wp_options.
	 */
	public function __construct( string $access_key ) {
		$this->access_key = $access_key;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Searches Unsplash for $keyword, downloads the best match, inserts it
	 * into the WP Media Library, and returns the new attachment post ID.
	 *
	 * @param string $keyword  Search term — the post's target keyword.
	 * @param string $alt_text Alt text / title to store on the attachment.
	 *
	 * @return int|WP_Error New attachment ID, or WP_Error on any failure.
	 */
	public function fetch_and_attach( string $keyword, string $alt_text ): int|WP_Error {
		// Step 1 — find a photo on Unsplash.
		$photo = $this->search( $keyword );
		if ( is_wp_error( $photo ) ) {
			return $photo;
		}

		// Step 2 — download the image to the server's temp directory.
		$tmp_path = $this->download( $photo['url'] );
		if ( is_wp_error( $tmp_path ) ) {
			return $tmp_path;
		}

		// Step 3 — sideload into the Media Library and return the attachment ID.
		return $this->sideload( $tmp_path, $keyword, $alt_text, $photo );
	}

	// -------------------------------------------------------------------------
	// Unsplash search
	// -------------------------------------------------------------------------

	/**
	 * Queries the Unsplash search API and returns metadata for the top result.
	 *
	 * @param string $keyword Search term.
	 *
	 * @return array{url: string, credit_name: string, credit_url: string, description: string}|WP_Error
	 */
	private function search( string $keyword ): array|WP_Error {
		// Build the search URL with query parameters.
		$endpoint = add_query_arg(
			[
				'query'       => rawurlencode( $keyword ),
				'per_page'    => self::SEARCH_PER_PAGE,
				'orientation' => self::ORIENTATION,
			],
			SBG_UNSPLASH_ENDPOINT
		);

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => [
					// Unsplash requires both the Authorization header and the
					// Accept-Version header for v1 API access.
					'Authorization'  => 'Client-ID ' . $this->access_key,
					'Accept-Version' => 'v1',
				],
				'timeout' => self::SEARCH_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'sbg_unsplash_transport',
				sprintf(
					/* translators: %s: WP HTTP error message */
					__( 'Unsplash search request failed: %s', 'smart-blog-generator' ),
					$response->get_error_message()
				)
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $http_code ) {
			return new WP_Error(
				'sbg_unsplash_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Unsplash API responded with status %d.', 'smart-blog-generator' ),
					$http_code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Unsplash search response shape: { "results": [ { ... }, ... ], "total": N }.
		if ( empty( $body['results'][0] ) || ! is_array( $body['results'][0] ) ) {
			return new WP_Error(
				'sbg_unsplash_no_results',
				sprintf(
					/* translators: %s: search keyword */
					__( 'No Unsplash images found for keyword "%s".', 'smart-blog-generator' ),
					$keyword
				)
			);
		}

		$photo = $body['results'][0];

		// 'regular' size is ~1080 px wide — a good balance of quality and file size.
		// Fall back to 'full' if 'regular' is absent (very unusual).
		$url = $photo['urls']['regular'] ?? $photo['urls']['full'] ?? '';

		if ( empty( $url ) ) {
			return new WP_Error( 'sbg_unsplash_no_url', __( 'Unsplash result contained no usable image URL.', 'smart-blog-generator' ) );
		}

		return [
			'url'          => $url,
			'credit_name'  => $photo['user']['name']          ?? 'Unknown',
			'credit_url'   => $photo['user']['links']['html']  ?? 'https://unsplash.com',
			'description'  => $photo['description'] ?? $photo['alt_description'] ?? $keyword,
		];
	}

	// -------------------------------------------------------------------------
	// Image download
	// -------------------------------------------------------------------------

	/**
	 * Downloads the remote image to a temporary file using WordPress's
	 * download_url() helper, which streams the file and validates the
	 * HTTP response before returning.
	 *
	 * @param string $url Remote image URL.
	 *
	 * @return string|WP_Error Absolute path to the temp file, or WP_Error.
	 */
	private function download( string $url ): string|WP_Error {
		// WP admin file helpers must be loaded manually outside of admin context.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = download_url( $url, self::DOWNLOAD_TIMEOUT );

		if ( is_wp_error( $tmp ) ) {
			return new WP_Error(
				'sbg_image_download_error',
				sprintf(
					/* translators: %s: underlying error */
					__( 'Image download failed: %s', 'smart-blog-generator' ),
					$tmp->get_error_message()
				)
			);
		}

		return $tmp;
	}

	// -------------------------------------------------------------------------
	// Media Library sideload
	// -------------------------------------------------------------------------

	/**
	 * Moves the temp file into the WordPress uploads directory via
	 * media_handle_sideload() and creates an attachment post with
	 * alt text and photo credit metadata.
	 *
	 * media_handle_sideload() validates the MIME type against WordPress's
	 * allowed upload types, so dangerous file types are rejected automatically.
	 *
	 * If sideload fails, the temp file is cleaned up before returning the error.
	 *
	 * @param string $tmp_path   Absolute path to the downloaded temp file.
	 * @param string $keyword    Used to build the attachment filename slug.
	 * @param string $alt_text   Alt text and caption for the attachment.
	 * @param array  $photo_meta Credit metadata from Unsplash.
	 *
	 * @return int|WP_Error Attachment post ID, or WP_Error.
	 */
	private function sideload(
		string $tmp_path,
		string $keyword,
		string $alt_text,
		array  $photo_meta
	): int|WP_Error {
		// Ensure WP media/image helpers are available.
		foreach ( [ 'file', 'image', 'media' ] as $include ) {
			$path = ABSPATH . "wp-admin/includes/{$include}.php";
			if ( ! function_exists( 'media_handle_sideload' ) || ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once $path;
			}
		}

		// Build a clean filename: "target-keyword-unsplash.jpg".
		$filename = sanitize_file_name( sanitize_title( $keyword ) . '-unsplash.jpg' );

		// media_handle_sideload() expects a $_FILES-style array.
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_path,
		];

		// Post ID 0 = unattached initially; featured-image assignment happens
		// in SBG_Post_Creator after the post has been created.
		$attachment_id = media_handle_sideload(
			$file_array,
			0,
			sanitize_text_field( $alt_text )
		);

		// Clean up the temp file if the sideload failed to avoid orphaned files.
		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unlink is non-critical cleanup.
			@unlink( $tmp_path );

			return new WP_Error(
				'sbg_sideload_error',
				sprintf(
					/* translators: %s: underlying error */
					__( 'Image sideload failed: %s', 'smart-blog-generator' ),
					$attachment_id->get_error_message()
				)
			);
		}

		// Store alt text on the attachment for accessibility.
		update_post_meta(
			$attachment_id,
			'_wp_attachment_image_alt',
			sanitize_text_field( $alt_text )
		);

		// Store the Unsplash photo credit as attachment meta.
		// This is informational; editors can add a caption if required by
		// Unsplash's attribution guidelines.
		update_post_meta(
			$attachment_id,
			'_sbg_photo_credit',
			sanitize_text_field(
				sprintf( 'Photo by %s on Unsplash — %s', $photo_meta['credit_name'], $photo_meta['credit_url'] )
			)
		);

		return $attachment_id;
	}
}
