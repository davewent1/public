<?php
/**
 * SBG_Generator_Page
 *
 * Renders the Tools > Smart Blog Generator page — the primary UI that
 * editors use to trigger post generation.
 *
 * The form submits via AJAX (handled by generator.js / SBG_Admin_Controller).
 * A results area is revealed by JS on completion, showing either a success
 * notice with an Edit Post link or an error notice with the failure reason.
 *
 * All output is escaped appropriately; no raw user input is echoed.
 *
 * @package Smart_Blog_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static renderer for the blog generator admin page.
 */
class SBG_Generator_Page {

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	/**
	 * Renders the full generator page HTML.
	 *
	 * Called by WordPress via the add_management_page() callback.
	 * Performs a capability check as a second layer of defence.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-blog-generator' ) );
		}

		$anthropic_configured = ! empty( get_option( 'sbg_anthropic_api_key', '' ) );
		$unsplash_configured  = ! empty( get_option( 'sbg_unsplash_access_key', '' ) );

		$settings_url = admin_url( 'admin.php?page=smart-blog-generator-settings' );
		?>
		<div class="wrap sbg-wrap">

			<h1><?php esc_html_e( 'Smart Blog Generator', 'smart-blog-generator' ); ?></h1>

			<?php if ( ! $anthropic_configured ) : ?>
				<!-- Configuration warning — shown until the Anthropic key is saved -->
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							/* translators: 1: opening <a>, 2: closing </a> */
							esc_html__( 'Anthropic API key is not configured. %1$sGo to Settings%2$s to add it before generating posts.', 'smart-blog-generator' ),
							'<a href="' . esc_url( $settings_url ) . '">',
							'</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $anthropic_configured && ! $unsplash_configured ) : ?>
				<!-- Soft warning — posts will still be created, just without images -->
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: 1: opening <a>, 2: closing </a> */
							esc_html__( 'No Unsplash key configured — posts will be created without a featured image. %1$sAdd one in Settings%2$s.', 'smart-blog-generator' ),
							'<a href="' . esc_url( $settings_url ) . '">',
							'</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<p class="description" style="font-size:14px;margin-top:8px;">
				<?php esc_html_e( 'Fill in the fields below and click Generate Post. The draft will be created in the background and a link to edit it will appear here.', 'smart-blog-generator' ); ?>
			</p>

			<!-- ── Generator form ─────────────────────────────────────────── -->
			<div class="sbg-card" style="max-width:680px;">
				<form id="sbg-generator-form" method="post" novalidate>

					<!-- Topic -->
					<table class="form-table" role="presentation">
						<tbody>

							<tr>
								<th scope="row">
									<label for="sbg_topic">
										<?php esc_html_e( 'Topic', 'smart-blog-generator' ); ?>
										<span aria-hidden="true" style="color:#d63638;">*</span>
									</label>
								</th>
								<td>
									<input
										type="text"
										id="sbg_topic"
										name="sbg_topic"
										class="regular-text"
										required
										placeholder="<?php esc_attr_e( 'e.g. How to train a rescue puppy', 'smart-blog-generator' ); ?>"
										maxlength="200"
									/>
									<p class="description">
										<?php esc_html_e( 'The subject of the blog post. Be specific for better results.', 'smart-blog-generator' ); ?>
									</p>
								</td>
							</tr>

							<!-- Target keyword -->
							<tr>
								<th scope="row">
									<label for="sbg_keyword">
										<?php esc_html_e( 'Target Keyword', 'smart-blog-generator' ); ?>
										<span aria-hidden="true" style="color:#d63638;">*</span>
									</label>
								</th>
								<td>
									<input
										type="text"
										id="sbg_keyword"
										name="sbg_keyword"
										class="regular-text"
										required
										placeholder="<?php esc_attr_e( 'e.g. rescue puppy training', 'smart-blog-generator' ); ?>"
										maxlength="100"
									/>
									<p class="description">
										<?php esc_html_e( 'The primary SEO keyword to target. It will appear in the title, meta description, first paragraph, and FAQ answers.', 'smart-blog-generator' ); ?>
									</p>
								</td>
							</tr>

							<!-- Tone -->
							<tr>
								<th scope="row">
									<label for="sbg_tone">
										<?php esc_html_e( 'Content Tone', 'smart-blog-generator' ); ?>
									</label>
								</th>
								<td>
									<select id="sbg_tone" name="sbg_tone">
										<option value="informational">
											<?php esc_html_e( 'Informational — neutral educational prose', 'smart-blog-generator' ); ?>
										</option>
										<option value="how-to">
											<?php esc_html_e( 'How-To — step-by-step instructions', 'smart-blog-generator' ); ?>
										</option>
										<option value="listicle">
											<?php esc_html_e( 'Listicle — list-based format (e.g. "10 ways…")', 'smart-blog-generator' ); ?>
										</option>
									</select>
								</td>
							</tr>

							<!-- Category -->
							<tr>
								<th scope="row">
									<label for="sbg_category">
										<?php esc_html_e( 'Category', 'smart-blog-generator' ); ?>
									</label>
								</th>
								<td>
									<?php
									// Use WordPress's built-in category dropdown helper.
									// show_option_none lets the user pick "no override"
									// (will fall back to Uncategorized in the post creator).
									wp_dropdown_categories( [
										'name'              => 'sbg_category',
										'id'                => 'sbg_category',
										'show_option_none'  => __( '— Default (Uncategorized) —', 'smart-blog-generator' ),
										'option_none_value' => '0',
										'hide_empty'        => false,
										'orderby'           => 'name',
									] );
									?>
									<p class="description">
										<?php esc_html_e( 'The generated post will be assigned to this category.', 'smart-blog-generator' ); ?>
									</p>
								</td>
							</tr>

						</tbody>
					</table>

					<!-- Submit button + spinner -->
					<p>
						<button
							type="submit"
							id="sbg-submit"
							class="button button-primary button-large"
							<?php disabled( ! $anthropic_configured ); ?>
						>
							<?php esc_html_e( 'Generate Post', 'smart-blog-generator' ); ?>
						</button>
						<!-- WordPress's built-in spinner image, hidden until generation starts -->
						<img
							src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"
							class="sbg-spinner"
							width="20"
							height="20"
							alt="<?php esc_attr_e( 'Loading…', 'smart-blog-generator' ); ?>"
						/>
					</p>

				</form>
			</div><!-- .sbg-card -->

			<!-- ── Result area (revealed by JS on completion) ─────────────── -->
			<div id="sbg-result" role="status" aria-live="polite">
				<!--
					JS will inject one of these inside #sbg-result:

					Success:
					<div class="notice notice-success">
						<p>Draft "<strong>{title}</strong>" created.
						<a href="{edit_url}">Edit Draft Post →</a></p>
					</div>

					Image notice (warning, non-fatal):
					<div class="notice notice-warning">
						<p>{image_notice}</p>
					</div>

					Error:
					<div class="notice notice-error">
						<p>{message}</p>
					</div>
				-->
			</div>

			<!-- ── Quick links ────────────────────────────────────────────── -->
			<p style="margin-top:24px;">
				<a href="<?php echo esc_url( $settings_url ); ?>">
					<?php esc_html_e( '⚙ Plugin Settings', 'smart-blog-generator' ); ?>
				</a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=draft&post_type=post' ) ); ?>">
					<?php esc_html_e( '📋 View All Draft Posts', 'smart-blog-generator' ); ?>
				</a>
			</p>

		</div><!-- .sbg-wrap -->
		<?php
	}
}
