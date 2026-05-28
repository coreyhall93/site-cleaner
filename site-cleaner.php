<?php
/**
 * Plugin Name: Site Cleaner
 * Description: Selectively clears a WordPress test site while preserving plugins, themes, users, and the ManageWP connection by default.
 * Version: 0.3.0
 * Author: coreyhall93
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: site-cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Site_Cleaner {
	private const VERSION        = '0.3.0';
	private const NONCE_ACTION   = 'site_cleaner_run';
	private const OPTION_DRY_RUN = 'dry_run';
	private const OPTION_CLEAN   = 'clean';
	private const CONFIRM_PHRASE = 'CLEAN TEST SITE';

	private string $plugin_basename;

	public function __construct() {
		$this->plugin_basename = plugin_basename( __FILE__ );

		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_admin_page(): void {
		add_management_page(
			'Site Cleaner',
			'Site Cleaner',
			'manage_options',
			'site-cleaner',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'tools_page_site-cleaner' !== $hook ) {
			return;
		}

		wp_register_style( 'site-cleaner-admin', false, array(), self::VERSION );
		wp_enqueue_style( 'site-cleaner-admin' );

		wp_add_inline_style(
			'site-cleaner-admin',
			'
			.site-cleaner-wrap { max-width: 1120px; }
			.site-cleaner-hero { margin: 18px 0 22px; padding: 22px 24px; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; }
			.site-cleaner-hero p { max-width: 820px; font-size: 15px; }
			.site-cleaner-wizard { display: grid; gap: 18px; }
			.site-cleaner-step { padding: 0; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; overflow: hidden; }
			.site-cleaner-step-header { display: flex; gap: 14px; align-items: flex-start; padding: 18px 20px; border-bottom: 1px solid #f0f0f1; background: #fbfbfc; }
			.site-cleaner-step-number { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 999px; background: #2271b1; color: #fff; font-weight: 700; line-height: 1; flex: 0 0 auto; }
			.site-cleaner-step-header h2 { margin: 0 0 4px; }
			.site-cleaner-step-header p { margin: 0; }
			.site-cleaner-step-body { padding: 18px 20px 20px; }
			.site-cleaner-two-column { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
			.site-cleaner-panel { padding: 16px 18px; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; }
			.site-cleaner-panel h3 { margin-top: 0; }
			.site-cleaner-options { display: grid; gap: 10px; margin-top: 12px; }
			.site-cleaner-option { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid #e6e6e6; border-radius: 6px; background: #fbfbfb; }
			.site-cleaner-option input { margin-top: 2px; }
			.site-cleaner-muted { color: #646970; }
			.site-cleaner-mini { margin: 0; font-size: 13px; color: #646970; }
			.site-cleaner-warning { border-left: 4px solid #d63638; padding: 12px 14px; background: #fcf0f1; }
			.site-cleaner-note { border-left: 4px solid #2271b1; padding: 12px 14px; background: #f0f6fc; }
			.site-cleaner-list { max-height: 330px; overflow: auto; border: 1px solid #dcdcde; border-radius: 6px; }
			.site-cleaner-list label { display: flex; gap: 10px; padding: 9px 10px; border-bottom: 1px solid #f0f0f1; }
			.site-cleaner-list label:last-child { border-bottom: 0; }
			.site-cleaner-list small { display: block; color: #646970; }
			.site-cleaner-results { margin: 22px 0; }
			.site-cleaner-results .notice { margin-left: 0; }
			.site-cleaner-results ul { margin-left: 18px; list-style: disc; }
			.site-cleaner-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 18px; }
			.site-cleaner-step input.regular-text, .site-cleaner-step textarea.large-text { max-width: 100%; box-sizing: border-box; }
			.site-cleaner-confirm { display: block; width: 100%; max-width: 100%; }
			@media (max-width: 960px) { .site-cleaner-two-column { grid-template-columns: 1fr; } }
			'
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use this tool.', 'site-cleaner' ) );
		}

		$this->load_admin_includes();

		$result = null;
		if ( isset( $_POST['site_cleaner_submit'] ) ) {
			check_admin_referer( self::NONCE_ACTION, 'site_cleaner_nonce' );
			$result = $this->handle_request();
		}

		$plugins          = $this->get_plugins_for_form();
		$themes           = $this->get_themes_for_form();
		$site_title       = $this->get_form_text_value( 'site_title', $this->default_site_title() );
		$homepage_title   = $this->get_form_text_value( 'homepage_title', $this->default_homepage_title() );
		$homepage_message = $this->get_form_textarea_value( 'homepage_message', $this->default_homepage_message() );
		?>
		<div class="wrap site-cleaner-wrap">
			<h1><?php echo esc_html__( 'Site Cleaner', 'site-cleaner' ); ?></h1>

			<div class="site-cleaner-hero">
				<h2><?php echo esc_html__( 'Clean a test site while WordPress stays in place.', 'site-cleaner' ); ?></h2>
				<p>
					<?php echo esc_html__( 'Use this after the finished site has already been moved to production. The wizard keeps users, WordPress, Site Cleaner, ManageWP Worker, network-active plugins, and the protected default theme safe while it clears the test domain for the next build.', 'site-cleaner' ); ?>
				</p>
			</div>

			<?php if ( is_array( $result ) ) : ?>
				<?php $this->render_result( $result ); ?>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, 'site_cleaner_nonce' ); ?>

				<div class="site-cleaner-wizard">
					<section class="site-cleaner-step" aria-labelledby="site-cleaner-step-1">
						<div class="site-cleaner-step-header">
							<span class="site-cleaner-step-number">1</span>
							<div>
								<h2 id="site-cleaner-step-1"><?php echo esc_html__( 'Confirm this test site is ready', 'site-cleaner' ); ?></h2>
								<p class="site-cleaner-muted"><?php echo esc_html__( 'These confirmations are required before the final clean can run.', 'site-cleaner' ); ?></p>
							</div>
						</div>
						<div class="site-cleaner-step-body">
							<div class="site-cleaner-options">
								<?php
								$this->render_checkbox( 'confirm_test_domain', __( 'I am logged into the test or staging domain.', 'site-cleaner' ), __( 'Check the browser address bar before continuing. Do not run this on the production site.', 'site-cleaner' ), false );
								$this->render_checkbox( 'confirm_migrated', __( 'The finished site has already been moved to production.', 'site-cleaner' ), __( 'This assumes the All-in-One WP Migration export/import is done and production has been checked.', 'site-cleaner' ), false );
								?>
							</div>
							<div class="site-cleaner-note">
								<strong><?php echo esc_html__( 'WordPress stays installed.', 'site-cleaner' ); ?></strong>
								<p><?php echo esc_html__( 'The cleaner removes old site content and selected extras while keeping the WordPress install and user accounts in place.', 'site-cleaner' ); ?></p>
							</div>
						</div>
					</section>

					<section class="site-cleaner-step" aria-labelledby="site-cleaner-step-2">
						<div class="site-cleaner-step-header">
							<span class="site-cleaner-step-number">2</span>
							<div>
								<h2 id="site-cleaner-step-2"><?php echo esc_html__( 'Choose the content to clear', 'site-cleaner' ); ?></h2>
								<p class="site-cleaner-muted"><?php echo esc_html__( 'The defaults are set for a full test-site cleanup. Users are never deleted.', 'site-cleaner' ); ?></p>
							</div>
						</div>
						<div class="site-cleaner-step-body">
							<div class="site-cleaner-options">
								<?php
								$this->render_checkbox( 'delete_pages', __( 'Pages', 'site-cleaner' ), __( 'Deletes all existing pages.', 'site-cleaner' ), true );
								$this->render_checkbox( 'delete_posts', __( 'Posts', 'site-cleaner' ), __( 'Deletes standard blog posts.', 'site-cleaner' ), true );
								$this->render_checkbox( 'delete_media', __( 'Media library uploads', 'site-cleaner' ), __( 'Permanently deletes attachments from the Media Library.', 'site-cleaner' ), true );
								$this->render_checkbox( 'delete_menus', __( 'Navigation menus', 'site-cleaner' ), __( 'Deletes nav menu items and menu terms.', 'site-cleaner' ), true );
								$this->render_checkbox( 'delete_comments', __( 'Comments', 'site-cleaner' ), __( 'Deletes comments, pingbacks, and trackbacks.', 'site-cleaner' ), true );
								$this->render_checkbox( 'delete_widgets', __( 'Widgets and sidebars', 'site-cleaner' ), __( 'Clears widget placements and most saved widget settings.', 'site-cleaner' ), true );
								$this->render_checkbox( 'delete_custom_post_types', __( 'Custom post types', 'site-cleaner' ), __( 'Deletes content from public custom post types created by plugins or builders.', 'site-cleaner' ), true );
								?>
							</div>
						</div>
					</section>

					<section class="site-cleaner-step" aria-labelledby="site-cleaner-step-3">
						<div class="site-cleaner-step-header">
							<span class="site-cleaner-step-number">3</span>
							<div>
								<h2 id="site-cleaner-step-3"><?php echo esc_html__( 'Reset the site identity and ready screen', 'site-cleaner' ); ?></h2>
								<p class="site-cleaner-muted"><?php echo esc_html__( 'This leaves the test domain with a simple homepage that says it is ready for the next build.', 'site-cleaner' ); ?></p>
							</div>
						</div>
						<div class="site-cleaner-step-body">
							<div class="site-cleaner-options">
								<?php $this->render_checkbox( 'create_homepage', __( 'Create the ready screen on the blog home template', 'site-cleaner' ), __( 'Sets the site to the default latest-posts homepage and replaces the block theme home template with centered ready text only.', 'site-cleaner' ), true ); ?>
								<?php $this->render_checkbox( 'update_site_title', __( 'Update the site title', 'site-cleaner' ), __( 'Updates Settings > General > Site Title after cleanup.', 'site-cleaner' ), true ); ?>
								<?php $this->render_checkbox( 'clear_tagline', __( 'Clear the tagline', 'site-cleaner' ), __( 'Deletes the existing WordPress tagline.', 'site-cleaner' ), true ); ?>
								<?php $this->render_checkbox( 'remove_site_icon', __( 'Remove the site icon', 'site-cleaner' ), __( 'Clears the favicon/site icon setting.', 'site-cleaner' ), true ); ?>
								<?php $this->render_checkbox( 'reset_permalinks', __( 'Reset permalinks', 'site-cleaner' ), __( 'Restores default WordPress permalink settings and flushes rewrite rules.', 'site-cleaner' ), true ); ?>
							</div>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="site_title"><?php echo esc_html__( 'Site title', 'site-cleaner' ); ?></label></th>
									<td><input class="regular-text" type="text" id="site_title" name="site_title" value="<?php echo esc_attr( $site_title ); ?>"></td>
								</tr>
								<tr>
									<th scope="row"><label for="homepage_title"><?php echo esc_html__( 'Ready screen title', 'site-cleaner' ); ?></label></th>
									<td><input class="regular-text" type="text" id="homepage_title" name="homepage_title" value="<?php echo esc_attr( $homepage_title ); ?>"></td>
								</tr>
								<tr>
									<th scope="row"><label for="homepage_message"><?php echo esc_html__( 'Ready screen message', 'site-cleaner' ); ?></label></th>
									<td>
										<textarea class="large-text" rows="3" id="homepage_message" name="homepage_message"><?php echo esc_textarea( $homepage_message ); ?></textarea>
										<p class="description"><?php echo esc_html__( 'This appears centered on the homepage after cleanup.', 'site-cleaner' ); ?></p>
									</td>
								</tr>
							</table>

							<div class="site-cleaner-note">
								<strong><?php echo esc_html__( 'Theme setup', 'site-cleaner' ); ?></strong>
								<p><?php echo esc_html__( 'Site Cleaner can make sure Twenty Twenty-Five is installed and active before it writes the clean home template and removes old themes.', 'site-cleaner' ); ?></p>
							</div>

							<div class="site-cleaner-options">
								<?php
								$this->render_checkbox( 'ensure_2025_theme', __( 'Install Twenty Twenty-Five if missing', 'site-cleaner' ), __( 'Uses the WordPress.org theme API and may require normal filesystem permissions.', 'site-cleaner' ), true );
								$this->render_checkbox( 'activate_2025_theme', __( 'Activate Twenty Twenty-Five', 'site-cleaner' ), __( 'Switches the site to the twentytwentyfive theme before old themes are deleted.', 'site-cleaner' ), true );
								?>
							</div>
						</div>
					</section>

					<section class="site-cleaner-step" aria-labelledby="site-cleaner-step-4">
						<div class="site-cleaner-step-header">
							<span class="site-cleaner-step-number">4</span>
							<div>
								<h2 id="site-cleaner-step-4"><?php echo esc_html__( 'Review plugins and themes to delete', 'site-cleaner' ); ?></h2>
								<p class="site-cleaner-muted"><?php echo esc_html__( 'Most non-protected extras are selected by default. Uncheck anything this test site should keep.', 'site-cleaner' ); ?></p>
							</div>
						</div>
						<div class="site-cleaner-step-body">
							<p class="site-cleaner-warning">
								<?php echo esc_html__( 'Selected active plugins are deactivated before deletion. Site Cleaner, ManageWP Worker, network-active plugins, and Twenty Twenty-Five are protected.', 'site-cleaner' ); ?>
							</p>
							<div class="site-cleaner-two-column">
								<div class="site-cleaner-panel">
									<h3><?php echo esc_html__( 'Delete plugins', 'site-cleaner' ); ?></h3>
									<p class="site-cleaner-mini"><?php echo esc_html__( 'Checked plugins will be removed during the real clean.', 'site-cleaner' ); ?></p>
									<div class="site-cleaner-list">
										<?php foreach ( $plugins as $plugin_file => $plugin ) : ?>
											<label>
												<input type="checkbox" name="plugins_to_delete[]" value="<?php echo esc_attr( $plugin_file ); ?>" <?php checked( $this->should_check_list_item( 'plugins_to_delete', $plugin_file, ! empty( $plugin['default_delete'] ) ) ); ?> <?php disabled( $plugin['locked'] ); ?>>
												<span>
													<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
													<small><?php echo esc_html( $plugin_file ); ?><?php echo '' !== $plugin['status'] ? ' - ' . esc_html( $plugin['status'] ) : ''; ?></small>
												</span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>

								<div class="site-cleaner-panel">
									<h3><?php echo esc_html__( 'Delete themes', 'site-cleaner' ); ?></h3>
									<p class="site-cleaner-mini"><?php echo esc_html__( 'Checked themes will be removed after the protected default theme is active.', 'site-cleaner' ); ?></p>
									<div class="site-cleaner-list">
										<?php foreach ( $themes as $stylesheet => $theme ) : ?>
											<label>
												<input type="checkbox" name="themes_to_delete[]" value="<?php echo esc_attr( $stylesheet ); ?>" <?php checked( $this->should_check_list_item( 'themes_to_delete', $stylesheet, ! empty( $theme['default_delete'] ) ) ); ?> <?php disabled( $theme['locked'] ); ?>>
												<span>
													<strong><?php echo esc_html( $theme['name'] ); ?></strong>
													<small><?php echo esc_html( $stylesheet ); ?><?php echo '' !== $theme['status'] ? ' - ' . esc_html( $theme['status'] ) : ''; ?></small>
												</span>
											</label>
										<?php endforeach; ?>
										<?php if ( empty( $themes ) ) : ?>
											<p><?php echo esc_html__( 'No themes found.', 'site-cleaner' ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					</section>

					<section class="site-cleaner-step" aria-labelledby="site-cleaner-step-5">
						<div class="site-cleaner-step-header">
							<span class="site-cleaner-step-number">5</span>
							<div>
								<h2 id="site-cleaner-step-5"><?php echo esc_html__( 'Run the dry run', 'site-cleaner' ); ?></h2>
								<p class="site-cleaner-muted"><?php echo esc_html__( 'The dry run lists what will happen without changing the site.', 'site-cleaner' ); ?></p>
							</div>
						</div>
						<div class="site-cleaner-step-body">
							<div class="site-cleaner-actions">
								<button class="button button-secondary" type="submit" name="site_cleaner_submit" value="<?php echo esc_attr( self::OPTION_DRY_RUN ); ?>">
									<?php echo esc_html__( 'Run dry run', 'site-cleaner' ); ?>
								</button>
							</div>
						</div>
					</section>

					<section class="site-cleaner-step" aria-labelledby="site-cleaner-step-6">
						<div class="site-cleaner-step-header">
							<span class="site-cleaner-step-number">6</span>
							<div>
								<h2 id="site-cleaner-step-6"><?php echo esc_html__( 'Run the final clean', 'site-cleaner' ); ?></h2>
								<p class="site-cleaner-muted"><?php echo esc_html__( 'Only run this after the dry run looks right.', 'site-cleaner' ); ?></p>
							</div>
						</div>
						<div class="site-cleaner-step-body">
							<p class="site-cleaner-warning">
								<?php echo esc_html__( 'A real clean permanently deletes selected content, plugins, and themes. WordPress itself and users stay in place.', 'site-cleaner' ); ?>
							</p>
							<p>
								<label for="confirm_phrase"><strong><?php echo esc_html__( 'Required for real clean', 'site-cleaner' ); ?></strong></label>
								<input class="regular-text site-cleaner-confirm" type="text" id="confirm_phrase" name="confirm_phrase" placeholder="<?php echo esc_attr( self::CONFIRM_PHRASE ); ?>">
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: confirmation phrase */
									esc_html__( 'To run the real cleaner, type %s exactly.', 'site-cleaner' ),
									'<code>' . esc_html( self::CONFIRM_PHRASE ) . '</code>'
								);
								?>
							</p>

							<div class="site-cleaner-actions">
								<button class="button button-primary" type="submit" name="site_cleaner_submit" value="<?php echo esc_attr( self::OPTION_CLEAN ); ?>">
									<?php echo esc_html__( 'Clean test site', 'site-cleaner' ); ?>
								</button>
							</div>
						</div>
					</section>
				</div>
			</form>
		</div>
		<?php
	}

	private function handle_request(): array {
		$mode = isset( $_POST['site_cleaner_submit'] ) ? sanitize_key( wp_unslash( $_POST['site_cleaner_submit'] ) ) : self::OPTION_DRY_RUN;

		if ( ! in_array( $mode, array( self::OPTION_DRY_RUN, self::OPTION_CLEAN ), true ) ) {
			$mode = self::OPTION_DRY_RUN;
		}

		$is_dry_run = self::OPTION_DRY_RUN === $mode;

		if ( ! $is_dry_run ) {
			if ( ! $this->is_checked( 'confirm_test_domain' ) || ! $this->is_checked( 'confirm_migrated' ) ) {
				return array(
					'status'  => 'error',
					'title'   => __( 'Required confirmations are missing.', 'site-cleaner' ),
					'message' => __( 'No changes were made. Confirm you are on the test domain and the finished site has already been moved to production before running the final clean.', 'site-cleaner' ),
					'items'   => array(),
				);
			}

			$confirm_phrase = isset( $_POST['confirm_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_phrase'] ) ) : '';
			if ( self::CONFIRM_PHRASE !== $confirm_phrase ) {
				return array(
					'status'  => 'error',
					'title'   => __( 'Confirmation phrase did not match.', 'site-cleaner' ),
					'message' => __( 'No changes were made. Type the required phrase exactly before running a real clean.', 'site-cleaner' ),
					'items'   => array(),
				);
			}
		}

		$options = $this->get_submitted_options();

		$runner = new Site_Cleaner_Runner(
			$options,
			$is_dry_run,
			$this->plugin_basename
		);

		return $runner->run();
	}

	private function get_submitted_options(): array {
		return array(
			'delete_pages'             => $this->is_checked( 'delete_pages' ),
			'delete_posts'             => $this->is_checked( 'delete_posts' ),
			'delete_media'             => $this->is_checked( 'delete_media' ),
			'delete_menus'             => $this->is_checked( 'delete_menus' ),
			'delete_comments'          => $this->is_checked( 'delete_comments' ),
			'delete_widgets'           => $this->is_checked( 'delete_widgets' ),
			'delete_custom_post_types' => $this->is_checked( 'delete_custom_post_types' ),
			'create_homepage'          => $this->is_checked( 'create_homepage' ),
			'update_site_title'        => $this->is_checked( 'update_site_title' ),
			'clear_tagline'            => $this->is_checked( 'clear_tagline' ),
			'remove_site_icon'         => $this->is_checked( 'remove_site_icon' ),
			'reset_permalinks'         => $this->is_checked( 'reset_permalinks' ),
			'ensure_2025_theme'        => $this->is_checked( 'ensure_2025_theme' ),
			'activate_2025_theme'      => $this->is_checked( 'activate_2025_theme' ),
			'site_title'               => $this->get_posted_text( 'site_title', $this->default_site_title() ),
			'homepage_title'           => $this->get_posted_text( 'homepage_title', $this->default_homepage_title() ),
			'homepage_message'         => $this->get_posted_textarea( 'homepage_message', $this->default_homepage_message() ),
			'plugins_to_delete'        => array_values( array_unique( $this->get_posted_string_array( 'plugins_to_delete' ) ) ),
			'themes_to_delete'         => array_values( array_unique( $this->get_posted_string_array( 'themes_to_delete' ) ) ),
		);
	}

	private function get_plugins_for_form(): array {
		$plugins = get_plugins();
		$items   = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$name              = $plugin_data['Name'] ?? $plugin_file;
			$is_self           = $plugin_file === $this->plugin_basename;
			$is_worker         = $this->looks_like_managewp_worker( $plugin_file, $name );
			$is_active         = is_plugin_active( $plugin_file );
			$is_network_active = is_plugin_active_for_network( $plugin_file );
			$is_locked         = $is_self || $is_worker || $is_network_active;

			$items[ $plugin_file ] = array(
				'name'           => $name,
				'active'         => $is_active,
				'locked'         => $is_locked,
				'default_delete' => ! $is_locked,
				'status'         => $this->plugin_status_label( $is_self, $is_worker, $is_active, $is_network_active ),
			);
		}

		uasort(
			$items,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $items;
	}

	private function get_themes_for_form(): array {
		$themes      = wp_get_themes();
		$active      = get_stylesheet();
		$theme_items = array();

		foreach ( $themes as $stylesheet => $theme ) {
			$is_active = $stylesheet === $active;
			$is_2025   = 'twentytwentyfive' === $stylesheet;

			$theme_items[ $stylesheet ] = array(
				'name'           => $theme->get( 'Name' ),
				'active'         => $is_active,
				'locked'         => $is_2025,
				'default_delete' => ! $is_2025,
				'status'         => $this->theme_status_label( $is_active, $is_2025 ),
			);
		}

		uasort(
			$theme_items,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $theme_items;
	}

	private function plugin_status_label( bool $is_self, bool $is_worker, bool $is_active, bool $is_network_active ): string {
		if ( $is_self ) {
			return __( 'protected: Site Cleaner', 'site-cleaner' );
		}

		if ( $is_worker ) {
			return __( 'protected: ManageWP Worker', 'site-cleaner' );
		}

		if ( $is_network_active ) {
			return __( 'protected: network active', 'site-cleaner' );
		}

		if ( $is_active ) {
			return __( 'active: will be deactivated first if selected', 'site-cleaner' );
		}

		return __( 'inactive', 'site-cleaner' );
	}

	private function theme_status_label( bool $is_active, bool $is_2025 ): string {
		if ( $is_2025 ) {
			return __( 'protected: Twenty Twenty-Five', 'site-cleaner' );
		}

		if ( $is_active ) {
			return __( 'active: deleted only after another theme is activated', 'site-cleaner' );
		}

		return __( 'inactive', 'site-cleaner' );
	}

	private function render_checkbox( string $name, string $label, string $description, bool $checked ): void {
		?>
		<label class="site-cleaner-option">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $this->should_check_field( $name, $checked ) ); ?>>
			<span>
				<strong><?php echo esc_html( $label ); ?></strong>
				<small><?php echo esc_html( $description ); ?></small>
			</span>
		</label>
		<?php
	}

	private function render_result( array $result ): void {
		$status = $result['status'] ?? 'info';
		$class  = 'notice-info';

		if ( 'success' === $status ) {
			$class = 'notice-success';
		} elseif ( 'warning' === $status ) {
			$class = 'notice-warning';
		} elseif ( 'error' === $status ) {
			$class = 'notice-error';
		}
		?>
		<div class="site-cleaner-results">
			<div class="notice <?php echo esc_attr( $class ); ?>">
				<p><strong><?php echo esc_html( $result['title'] ?? __( 'Site Cleaner result', 'site-cleaner' ) ); ?></strong></p>
				<?php if ( ! empty( $result['message'] ) ) : ?>
					<p><?php echo esc_html( $result['message'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $result['items'] ) && is_array( $result['items'] ) ) : ?>
					<ul>
						<?php foreach ( $result['items'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function is_checked( string $name ): bool {
		return isset( $_POST[ $name ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
	}

	private function is_form_submitted(): bool {
		return isset( $_POST['site_cleaner_submit'] );
	}

	private function should_check_field( string $name, bool $default ): bool {
		if ( ! $this->is_form_submitted() ) {
			return $default;
		}

		return $this->is_checked( $name );
	}

	private function should_check_list_item( string $name, string $value, bool $default ): bool {
		if ( ! $this->is_form_submitted() ) {
			return $default;
		}

		return in_array( $value, $this->get_posted_string_array( $name ), true );
	}

	private function get_form_text_value( string $name, string $fallback ): string {
		if ( ! $this->is_form_submitted() ) {
			return $fallback;
		}

		return $this->get_posted_text( $name, $fallback );
	}

	private function get_form_textarea_value( string $name, string $fallback ): string {
		if ( ! $this->is_form_submitted() ) {
			return $fallback;
		}

		return $this->get_posted_textarea( $name, $fallback );
	}

	private function get_posted_string_array( string $name ): array {
		if ( empty( $_POST[ $name ] ) || ! is_array( $_POST[ $name ] ) ) {
			return array();
		}

		$values = wp_unslash( $_POST[ $name ] );
		$values = array_map( 'sanitize_text_field', $values );

		return array_values( array_filter( $values ) );
	}

	private function get_posted_text( string $name, string $fallback ): string {
		if ( ! isset( $_POST[ $name ] ) ) {
			return $fallback;
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );

		return '' !== $value ? $value : $fallback;
	}

	private function get_posted_textarea( string $name, string $fallback ): string {
		if ( ! isset( $_POST[ $name ] ) ) {
			return $fallback;
		}

		$value = sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) );

		return '' !== $value ? $value : $fallback;
	}

	private function looks_like_managewp_worker( string $plugin_file, string $plugin_name ): bool {
		$haystack = strtolower( $plugin_file . ' ' . $plugin_name );

		return false !== strpos( $haystack, 'managewp' ) || 0 === strpos( strtolower( $plugin_file ), 'worker/' );
	}

	private function load_admin_includes(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( file_exists( ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		}
	}

	private function default_site_title(): string {
		return __( 'Clean Test Site', 'site-cleaner' );
	}

	private function default_homepage_title(): string {
		return __( 'Clean Test Site', 'site-cleaner' );
	}

	private function default_homepage_message(): string {
		return __( 'This site is clean and ready for the next build.', 'site-cleaner' );
	}
}

final class Site_Cleaner_Runner {
	private array $options;
	private bool $dry_run;
	private string $plugin_basename;
	private array $items = array();

	public function __construct( array $options, bool $dry_run, string $plugin_basename ) {
		$this->options         = $options;
		$this->dry_run         = $dry_run;
		$this->plugin_basename = $plugin_basename;
	}

	public function run(): array {
		$this->items = array();

		if ( $this->dry_run ) {
			$this->run_plan();

			return array(
				'status'  => 'info',
				'title'   => __( 'Dry run complete. No changes were made.', 'site-cleaner' ),
				'message' => __( 'Review the planned changes below. If everything looks right, type the confirmation phrase and run the real cleaner.', 'site-cleaner' ),
				'items'   => $this->items,
			);
		}

		$this->run_clean();

		return array(
			'status'  => 'success',
			'title'   => __( 'Cleanup complete.', 'site-cleaner' ),
			'message' => __( 'The selected cleanup actions finished. Review the test site and confirm ManageWP still opens the correct WordPress admin.', 'site-cleaner' ),
			'items'   => $this->items,
		);
	}

	private function run_plan(): void {
		if ( ! empty( $this->options['ensure_2025_theme'] ) ) {
			$this->items[] = $this->theme_exists( 'twentytwentyfive' )
				? __( 'Twenty Twenty-Five is already installed.', 'site-cleaner' )
				: __( 'Would install Twenty Twenty-Five.', 'site-cleaner' );
		}

		if ( ! empty( $this->options['activate_2025_theme'] ) ) {
			$this->items[] = __( 'Would activate Twenty Twenty-Five.', 'site-cleaner' );
		}

			if ( ! empty( $this->options['update_site_title'] ) ) {
				$this->items[] = sprintf(
					/* translators: %s: site title */
					__( 'Would update the site title to "%s".', 'site-cleaner' ),
					$this->options['site_title']
				);
			}

			if ( ! empty( $this->options['clear_tagline'] ) ) {
				$this->items[] = __( 'Would clear the site tagline.', 'site-cleaner' );
			}

			if ( ! empty( $this->options['remove_site_icon'] ) ) {
				$this->items[] = __( 'Would remove the site icon.', 'site-cleaner' );
			}

			if ( ! empty( $this->options['reset_permalinks'] ) ) {
				$this->items[] = __( 'Would reset permalinks to the WordPress default and flush rewrite rules.', 'site-cleaner' );
			}

		$this->plan_post_type_deletion( 'page', __( 'pages', 'site-cleaner' ), 'delete_pages' );
		$this->plan_post_type_deletion( 'post', __( 'posts', 'site-cleaner' ), 'delete_posts' );
		$this->plan_post_type_deletion( 'attachment', __( 'media attachments', 'site-cleaner' ), 'delete_media' );
		$this->plan_post_type_deletion( 'nav_menu_item', __( 'navigation menu items', 'site-cleaner' ), 'delete_menus' );
		$this->plan_post_type_deletion( 'wp_navigation', __( 'block theme navigation menus', 'site-cleaner' ), 'delete_menus' );

		if ( ! empty( $this->options['delete_custom_post_types'] ) ) {
			foreach ( $this->get_custom_post_types() as $post_type ) {
				$this->items[] = sprintf(
					/* translators: 1: count, 2: post type */
					__( 'Would delete %1$d items from custom post type: %2$s.', 'site-cleaner' ),
					$this->count_posts_for_type( $post_type ),
					$post_type
				);
			}
		}

		if ( ! empty( $this->options['delete_comments'] ) ) {
			$this->items[] = sprintf(
				/* translators: %d: comment count */
				__( 'Would delete %d comments, pingbacks, and trackbacks.', 'site-cleaner' ),
				$this->count_comments()
			);
		}

		if ( ! empty( $this->options['delete_menus'] ) ) {
			$this->items[] = sprintf(
				/* translators: %d: menu count */
				__( 'Would delete %d navigation menus.', 'site-cleaner' ),
				count( wp_get_nav_menus() )
			);
		}

		if ( ! empty( $this->options['delete_widgets'] ) ) {
			$this->items[] = __( 'Would clear widget placements and saved widget settings.', 'site-cleaner' );
		}

			$plugin_deletions = $this->get_plugins_to_delete();
			if ( ! empty( $plugin_deletions ) ) {
				$this->items[] = sprintf(
					/* translators: %d: plugin count */
					__( 'Would deactivate if needed, then delete %d selected plugins.', 'site-cleaner' ),
					count( $plugin_deletions )
				);
				$this->append_named_items( $plugin_deletions, __( 'Plugin', 'site-cleaner' ) );
			} else {
				$this->items[] = __( 'No plugins selected for deletion.', 'site-cleaner' );
			}

			$theme_deletions = $this->get_themes_to_delete();
			if ( ! empty( $theme_deletions ) ) {
				$this->items[] = sprintf(
					/* translators: %d: theme count */
					__( 'Would delete %d selected themes.', 'site-cleaner' ),
					count( $theme_deletions )
				);
				$this->append_named_items( $theme_deletions, __( 'Theme', 'site-cleaner' ) );
			} else {
				$this->items[] = __( 'No themes selected for deletion.', 'site-cleaner' );
			}

			if ( ! empty( $this->options['create_homepage'] ) ) {
				$this->items[] = __( 'Would set the homepage display to the default blog home and create a clean home template with no header or footer template parts.', 'site-cleaner' );
			}

			$this->items[] = __( 'Users would be preserved.', 'site-cleaner' );
			$this->items[] = __( 'Site Cleaner, ManageWP Worker, network-active plugins, and Twenty Twenty-Five are protected.', 'site-cleaner' );
		}

	private function run_clean(): void {
		if ( ! empty( $this->options['ensure_2025_theme'] ) ) {
			$this->ensure_twenty_twenty_five();
		}

		if ( ! empty( $this->options['activate_2025_theme'] ) ) {
			$this->activate_twenty_twenty_five();
		}

			if ( ! empty( $this->options['update_site_title'] ) ) {
				$this->update_site_title();
			}

			if ( ! empty( $this->options['clear_tagline'] ) ) {
				$this->clear_tagline();
			}

			if ( ! empty( $this->options['remove_site_icon'] ) ) {
				$this->remove_site_icon();
			}

			if ( ! empty( $this->options['reset_permalinks'] ) ) {
				$this->reset_permalinks();
			}

		$this->maybe_delete_post_type( 'page', __( 'pages', 'site-cleaner' ), 'delete_pages' );
		$this->maybe_delete_post_type( 'post', __( 'posts', 'site-cleaner' ), 'delete_posts' );
		$this->maybe_delete_post_type( 'attachment', __( 'media attachments', 'site-cleaner' ), 'delete_media' );
		$this->maybe_delete_post_type( 'nav_menu_item', __( 'navigation menu items', 'site-cleaner' ), 'delete_menus' );
		$this->maybe_delete_post_type( 'wp_navigation', __( 'block theme navigation menus', 'site-cleaner' ), 'delete_menus' );

		if ( ! empty( $this->options['delete_custom_post_types'] ) ) {
			foreach ( $this->get_custom_post_types() as $post_type ) {
				$deleted       = $this->delete_posts_for_type( $post_type );
				$this->items[] = sprintf(
					/* translators: 1: count, 2: post type */
					__( 'Deleted %1$d items from custom post type: %2$s.', 'site-cleaner' ),
					$deleted,
					$post_type
				);
			}
		}

		if ( ! empty( $this->options['delete_comments'] ) ) {
			$this->items[] = sprintf(
				/* translators: %d: comment count */
				__( 'Deleted %d comments, pingbacks, and trackbacks.', 'site-cleaner' ),
				$this->delete_comments()
			);
		}

		if ( ! empty( $this->options['delete_menus'] ) ) {
			$this->items[] = sprintf(
				/* translators: %d: menu count */
				__( 'Deleted %d navigation menus.', 'site-cleaner' ),
				$this->delete_nav_menus()
			);
		}

			if ( ! empty( $this->options['delete_widgets'] ) ) {
				$this->clear_widgets();
				$this->items[] = __( 'Cleared widget placements and saved widget settings.', 'site-cleaner' );
			}

			if ( ! empty( $this->options['create_homepage'] ) ) {
				$template_id = $this->create_ready_home_template();
				if ( $template_id > 0 ) {
					$this->items[] = sprintf(
						/* translators: %d: template ID */
						__( 'Created or updated the clean blog home template. Template ID: %d.', 'site-cleaner' ),
						$template_id
					);
				}
			}

			$this->delete_selected_plugins();
			$this->delete_selected_themes();

		wp_cache_flush();
		$this->items[] = __( 'Flushed the WordPress object cache.', 'site-cleaner' );
		$this->items[] = __( 'Users were preserved.', 'site-cleaner' );
	}

	private function plan_post_type_deletion( string $post_type, string $label, string $option_key ): void {
		if ( empty( $this->options[ $option_key ] ) ) {
			return;
		}

		$this->items[] = sprintf(
			/* translators: 1: count, 2: content label */
			__( 'Would delete %1$d %2$s.', 'site-cleaner' ),
			$this->count_posts_for_type( $post_type ),
			$label
		);
	}

	private function maybe_delete_post_type( string $post_type, string $label, string $option_key ): void {
		if ( empty( $this->options[ $option_key ] ) ) {
			return;
		}

		$deleted       = $this->delete_posts_for_type( $post_type );
		$this->items[] = sprintf(
			/* translators: 1: count, 2: content label */
			__( 'Deleted %1$d %2$s.', 'site-cleaner' ),
			$deleted,
			$label
		);
	}

	private function count_posts_for_type( string $post_type ): int {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	private function delete_posts_for_type( string $post_type ): int {
		$deleted       = 0;
		$attempted_ids = array();

		do {
			$query = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'posts_per_page'         => 100,
					'fields'                 => 'ids',
					'post__not_in'           => $attempted_ids,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$ids = array_map( 'intval', $query->posts );

			foreach ( $ids as $post_id ) {
				$attempted_ids[] = $post_id;

				if ( 'attachment' === $post_type ) {
					$result = wp_delete_attachment( $post_id, true );
				} else {
					$result = wp_delete_post( $post_id, true );
				}

				if ( false !== $result && null !== $result ) {
					++$deleted;
				}
			}
		} while ( ! empty( $ids ) );

		return $deleted;
	}

	private function count_comments(): int {
		$count = get_comments(
			array(
				'count'  => true,
				'status' => 'all',
			)
		);

		return (int) $count;
	}

	private function delete_comments(): int {
		$deleted       = 0;
		$attempted_ids = array();

		do {
			$comments = get_comments(
				array(
					'fields'          => 'ids',
					'number'          => 100,
					'status'          => 'all',
					'comment__not_in' => $attempted_ids,
				)
			);

			$comment_ids = array_map( 'intval', $comments );

			foreach ( $comment_ids as $comment_id ) {
				$attempted_ids[] = $comment_id;

				if ( wp_delete_comment( $comment_id, true ) ) {
					++$deleted;
				}
			}
		} while ( ! empty( $comment_ids ) );

		return $deleted;
	}

	private function delete_nav_menus(): int {
		$deleted = 0;
		$menus   = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			if ( wp_delete_nav_menu( $menu->term_id ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	private function clear_widgets(): void {
		update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array() ) );

		global $wpdb;

		$widget_options = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%'"
		);

		foreach ( $widget_options as $option_name ) {
			delete_option( $option_name );
		}
	}

	private function get_custom_post_types(): array {
		$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face' );
		$types      = get_post_types( array( 'public' => true ), 'names' );

		return array_values( array_diff( $types, $core_types ) );
	}

	private function get_plugins_to_delete(): array {
		$plugins  = get_plugins();
		$selected = $this->options['plugins_to_delete'] ?? array();
		$delete   = array();

		foreach ( $selected as $plugin_file ) {
			if ( empty( $plugins[ $plugin_file ] ) ) {
				continue;
			}

			$plugin_data = $plugins[ $plugin_file ];
			$plugin_name = $plugin_data['Name'] ?? $plugin_file;

			if (
				$plugin_file === $this->plugin_basename
				|| is_plugin_active_for_network( $plugin_file )
				|| $this->looks_like_managewp_worker( $plugin_file, $plugin_name )
			) {
				continue;
			}

			$delete[ $plugin_file ] = is_plugin_active( $plugin_file )
				? sprintf(
					/* translators: %s: plugin name */
					__( '%s - active, will be deactivated first', 'site-cleaner' ),
					$plugin_name
				)
				: $plugin_name;
		}

		return $delete;
	}

	private function delete_selected_plugins(): void {
		$plugins_to_delete = $this->get_plugins_to_delete();

		if ( empty( $plugins_to_delete ) ) {
			$this->items[] = __( 'No plugins selected for deletion.', 'site-cleaner' );
			return;
		}

		$plugin_files = array_keys( $plugins_to_delete );
		$active_files = array_values( array_filter( $plugin_files, 'is_plugin_active' ) );

		if ( ! empty( $active_files ) ) {
			deactivate_plugins( $active_files, true, false );
			$this->items[] = sprintf(
				/* translators: %d: plugin count */
				__( 'Deactivated %d selected plugins before deletion.', 'site-cleaner' ),
				count( $active_files )
			);
		}

		$plugin_files = array_values(
			array_filter(
				$plugin_files,
				static function ( string $plugin_file ): bool {
					return ! is_plugin_active( $plugin_file ) && ! is_plugin_active_for_network( $plugin_file );
				}
			)
		);

		if ( empty( $plugin_files ) ) {
			$this->items[] = __( 'No selected plugins could be deleted after protection checks.', 'site-cleaner' );
			return;
		}

		$result       = delete_plugins( $plugin_files );

		if ( is_wp_error( $result ) ) {
			$this->items[] = sprintf(
				/* translators: %s: error message */
				__( 'Plugin deletion error: %s', 'site-cleaner' ),
				$result->get_error_message()
			);
			return;
		}

		$this->items[] = sprintf(
			/* translators: %d: plugin count */
			__( 'Deleted %d selected plugins.', 'site-cleaner' ),
			count( $plugin_files )
		);
		$this->append_named_items( $plugins_to_delete, __( 'Deleted plugin', 'site-cleaner' ) );
	}

	private function get_themes_to_delete(): array {
		$themes        = wp_get_themes();
		$selected      = $this->options['themes_to_delete'] ?? array();
		$active_styles = $this->active_theme_styles_to_protect();
		$delete        = array();

		foreach ( $selected as $stylesheet ) {
			if ( empty( $themes[ $stylesheet ] ) ) {
				continue;
			}

			if ( 'twentytwentyfive' === $stylesheet || in_array( $stylesheet, $active_styles, true ) ) {
				continue;
			}

			$theme                = $themes[ $stylesheet ];
			$delete[ $stylesheet ] = $theme->get( 'Name' );
		}

		return $delete;
	}

	private function delete_selected_themes(): void {
		$themes_to_delete = $this->get_themes_to_delete();

		if ( empty( $themes_to_delete ) ) {
			$this->items[] = __( 'No themes selected for deletion.', 'site-cleaner' );
			return;
		}

		$deleted = 0;
		foreach ( $themes_to_delete as $stylesheet => $theme_name ) {
			$result = delete_theme( $stylesheet );
			if ( true === $result ) {
				++$deleted;
			} elseif ( is_wp_error( $result ) ) {
				$this->items[] = sprintf(
					/* translators: 1: theme name, 2: error message */
					__( 'Theme deletion error for %1$s: %2$s', 'site-cleaner' ),
					$theme_name,
					$result->get_error_message()
				);
			}
		}

		$this->items[] = sprintf(
			/* translators: %d: theme count */
			__( 'Deleted %d selected themes.', 'site-cleaner' ),
			$deleted
		);
	}

	private function active_theme_styles_to_protect(): array {
		if (
			! empty( $this->options['activate_2025_theme'] )
			&& ( $this->theme_exists( 'twentytwentyfive' ) || ! empty( $this->options['ensure_2025_theme'] ) )
		) {
			return array( 'twentytwentyfive' );
		}

		return array( get_stylesheet(), get_template() );
	}

	private function ensure_twenty_twenty_five(): void {
		if ( $this->theme_exists( 'twentytwentyfive' ) ) {
			$this->items[] = __( 'Twenty Twenty-Five is already installed.', 'site-cleaner' );
			return;
		}

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => 'twentytwentyfive',
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			$this->items[] = sprintf(
				/* translators: %s: error message */
				__( 'Could not fetch Twenty Twenty-Five theme information: %s', 'site-cleaner' ),
				$api->get_error_message()
			);
			return;
		}

		if ( empty( $api->download_link ) ) {
			$this->items[] = __( 'Could not install Twenty Twenty-Five because WordPress did not return a theme download link.', 'site-cleaner' );
			return;
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) || ! class_exists( 'Theme_Upgrader' ) ) {
			$this->items[] = __( 'Could not install Twenty Twenty-Five because the WordPress upgrader classes are unavailable.', 'site-cleaner' );
			return;
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			$this->items[] = sprintf(
				/* translators: %s: error message */
				__( 'Could not install Twenty Twenty-Five: %s', 'site-cleaner' ),
				$result->get_error_message()
			);
			return;
		}

		if ( true === $result ) {
			wp_clean_themes_cache();
			$this->items[] = __( 'Installed Twenty Twenty-Five.', 'site-cleaner' );
		} else {
			$this->items[] = __( 'Twenty Twenty-Five installation did not complete. Check filesystem permissions.', 'site-cleaner' );
		}
	}

	private function activate_twenty_twenty_five(): void {
		if ( ! $this->theme_exists( 'twentytwentyfive' ) ) {
			$this->items[] = __( 'Could not activate Twenty Twenty-Five because it is not installed.', 'site-cleaner' );
			return;
		}

		switch_theme( 'twentytwentyfive' );
		$this->items[] = __( 'Activated Twenty Twenty-Five.', 'site-cleaner' );
	}

	private function theme_exists( string $stylesheet ): bool {
		$theme = wp_get_theme( $stylesheet );

		return $theme->exists();
	}

	private function update_site_title(): void {
		$title = ! empty( $this->options['site_title'] ) ? $this->options['site_title'] : __( 'Clean Test Site', 'site-cleaner' );

		update_option( 'blogname', $title );
		$this->items[] = sprintf(
			/* translators: %s: site title */
			__( 'Updated the site title to "%s".', 'site-cleaner' ),
			$title
		);
	}

	private function clear_tagline(): void {
		update_option( 'blogdescription', '' );
		$this->items[] = __( 'Cleared the site tagline.', 'site-cleaner' );
	}

	private function remove_site_icon(): void {
		delete_option( 'site_icon' );
		$this->items[] = __( 'Removed the site icon setting.', 'site-cleaner' );
	}

	private function reset_permalinks(): void {
		update_option( 'permalink_structure', '' );
		update_option( 'category_base', '' );
		update_option( 'tag_base', '' );
		flush_rewrite_rules();

		$this->items[] = __( 'Reset permalinks to the WordPress default and flushed rewrite rules.', 'site-cleaner' );
	}

	private function create_ready_home_template(): int {
		$title   = ! empty( $this->options['homepage_title'] ) ? $this->options['homepage_title'] : __( 'Clean Test Site', 'site-cleaner' );
		$message = ! empty( $this->options['homepage_message'] ) ? $this->options['homepage_message'] : __( 'This site is clean and ready for the next build.', 'site-cleaner' );

		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', 0 );
		$this->items[] = __( 'Set the homepage display to the default blog home.', 'site-cleaner' );

		$this->delete_generated_front_page_template();

		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			$this->items[] = __( 'Skipped the clean home template because the active theme is not a block theme.', 'site-cleaner' );
			return 0;
		}

		$template_id = $this->find_theme_template_id( 'home', get_stylesheet() );
		$template    = array(
			'post_type'    => 'wp_template',
			'post_status'  => 'publish',
			'post_title'   => __( 'Blog Home', 'site-cleaner' ),
			'post_name'    => 'home',
			'post_excerpt' => __( 'Clean ready-to-build blog home template generated by Site Cleaner.', 'site-cleaner' ),
			'post_content' => $this->home_template_content( $title, $message ),
		);

		if ( $template_id > 0 ) {
			$template['ID'] = $template_id;
			$result         = wp_update_post( $template, true );
		} else {
			$result = wp_insert_post( $template, true );
		}

		if ( is_wp_error( $result ) ) {
			$this->items[] = sprintf(
				/* translators: %s: error message */
				__( 'Could not create the clean home template: %s', 'site-cleaner' ),
				$result->get_error_message()
			);
			return 0;
		}

		$terms_result = wp_set_post_terms( (int) $result, array( get_stylesheet() ), 'wp_theme', false );
		if ( is_wp_error( $terms_result ) ) {
			$this->items[] = sprintf(
				/* translators: %s: error message */
				__( 'Could not assign the clean home template to the active theme: %s', 'site-cleaner' ),
				$terms_result->get_error_message()
			);
			return 0;
		}

		clean_post_cache( (int) $result );

		return (int) $result;
	}

	private function delete_generated_front_page_template(): void {
		$template_id = $this->find_theme_template_id( 'front-page', get_stylesheet() );
		if ( $template_id <= 0 ) {
			return;
		}

		$template = get_post( $template_id );
		$excerpt  = $template ? (string) $template->post_excerpt : '';
		if ( ! $template || ( false === strpos( $excerpt, 'Site Cleaner' ) && false === strpos( $excerpt, 'Build' . 'Ready Cleaner' ) ) ) {
			return;
		}

		wp_delete_post( $template_id, true );
		$this->items[] = __( 'Removed the older Site Cleaner front-page template override.', 'site-cleaner' );
	}

	private function find_theme_template_id( string $slug, string $stylesheet ): int {
		$templates = get_posts(
			array(
				'post_type'      => 'wp_template',
				'post_status'    => 'any',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $stylesheet,
					),
				),
			)
		);

		if ( empty( $templates ) ) {
			return 0;
		}

		return (int) $templates[0];
	}

	private function home_template_content( string $title, string $message ): string {
		$title   = esc_html( $title );
		$message = esc_html( $message );

		return <<<HTML
<!-- wp:group {"tagName":"main","align":"full","style":{"dimensions":{"minHeight":"100vh"},"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center","verticalAlignment":"center"}} -->
<main class="wp-block-group alignfull" style="min-height:100vh;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px">
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">{$title}</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">{$message}</p>
<!-- /wp:paragraph -->

</main>
<!-- /wp:group -->
HTML;
	}

	private function looks_like_managewp_worker( string $plugin_file, string $plugin_name ): bool {
		$haystack = strtolower( $plugin_file . ' ' . $plugin_name );

		return false !== strpos( $haystack, 'managewp' ) || 0 === strpos( strtolower( $plugin_file ), 'worker/' );
	}

	private function append_named_items( array $items, string $prefix ): void {
		$shown = 0;
		foreach ( $items as $key => $name ) {
			if ( $shown >= 12 ) {
				$remaining     = count( $items ) - $shown;
				$this->items[] = sprintf(
					/* translators: %d: remaining item count */
					__( '...and %d more.', 'site-cleaner' ),
					$remaining
				);
				return;
			}

			$this->items[] = sprintf( '%s: %s (%s)', $prefix, $name, $key );
			++$shown;
		}
	}
}

new Site_Cleaner();
