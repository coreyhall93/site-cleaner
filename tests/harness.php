<?php
declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/wp/' );

$GLOBALS['sc_actions']       = array();
$GLOBALS['sc_options']       = array();
$GLOBALS['sc_event_log']     = array();
$GLOBALS['sc_active_plugins'] = array(
	'site-cleaner/site-cleaner.php',
	'worker/init.php',
	'elementor/elementor.php',
	'all-in-one-wp-migration/all-in-one-wp-migration.php',
	'all-in-one-wp-migration-unlimited-extension/all-in-one-wp-migration-unlimited-extension.php',
);
$GLOBALS['sc_network_active_plugins'] = array(
	'network-active/network-active.php',
);
$GLOBALS['sc_plugins']        = array(
	'site-cleaner/site-cleaner.php' => array( 'Name' => 'Site Cleaner' ),
	'worker/init.php' => array( 'Name' => 'ManageWP Worker' ),
	'elementor/elementor.php' => array( 'Name' => 'Elementor' ),
	'all-in-one-wp-migration/all-in-one-wp-migration.php' => array( 'Name' => 'All-in-One WP Migration' ),
	'all-in-one-wp-migration-unlimited-extension/all-in-one-wp-migration-unlimited-extension.php' => array( 'Name' => 'All-in-One WP Migration Unlimited Extension' ),
	'contact-form-7/wp-contact-form-7.php' => array( 'Name' => 'Contact Form 7' ),
	'network-active/network-active.php' => array( 'Name' => 'Network Active Fixture' ),
);

function add_action( $hook, $callback ): void {
	$GLOBALS['sc_actions'][ $hook ] = $callback;
}

function add_management_page() {}
function wp_register_style() {}
function wp_enqueue_style() {}
function wp_add_inline_style() {}
function wp_die( $message ) { throw new RuntimeException( (string) $message ); }
function current_user_can() { return true; }
function check_admin_referer() { return true; }
function wp_nonce_field() {}
function checked( $checked ) { echo $checked ? 'checked="checked"' : ''; }
function disabled( $disabled ) { echo $disabled ? 'disabled="disabled"' : ''; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_textarea( $value ) { return htmlspecialchars( (string) $value, ENT_NOQUOTES ); }
function esc_html__( $value ) { return $value; }
function __( $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( wp_strip_all_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( wp_strip_all_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function plugin_basename() { return 'site-cleaner/site-cleaner.php'; }
function get_plugins() { return $GLOBALS['sc_plugins']; }
function is_plugin_active( $plugin ) { return in_array( $plugin, $GLOBALS['sc_active_plugins'], true ); }
function is_plugin_active_for_network( $plugin ) { return in_array( $plugin, $GLOBALS['sc_network_active_plugins'], true ); }
function wp_get_themes() {
	return array(
		'twentytwentyfive' => new class {
			public function get( $field ) { return 'Name' === $field ? 'Twenty Twenty-Five' : ''; }
			public function exists() { return true; }
		},
		'old-client-theme' => new class {
			public function get( $field ) { return 'Name' === $field ? 'Old Client Theme' : ''; }
			public function exists() { return true; }
		},
	);
}
function get_stylesheet() { return 'twentytwentyfive'; }
function get_template() { return 'twentytwentyfive'; }
function wp_is_block_theme() { return true; }
function get_post_types() { return array( 'post', 'page', 'attachment' ); }
function get_posts( $args = array() ) { return array(); }
function get_post( $post = null ) { return null; }
function wp_get_nav_menus() { return array(); }
function get_comments() { return 0; }
function update_option( $key, $value ) {
	$GLOBALS['sc_options'][ $key ] = $value;
	$GLOBALS['sc_event_log'][]     = 'update_option:' . $key;
	return true;
}
function delete_option( $key ) {
	$GLOBALS['sc_event_log'][] = 'delete_option:' . $key;
	unset( $GLOBALS['sc_options'][ $key ] );
	return true;
}
function switch_theme( $theme ) {
	$GLOBALS['sc_event_log'][] = 'switch_theme:' . $theme;
}
function deactivate_plugins( $plugins, $silent = false, $network_wide = null ) {
	$GLOBALS['sc_event_log'][]     = 'deactivate_plugins:' . implode( ',', (array) $plugins );
	$GLOBALS['sc_active_plugins'] = array_values( array_diff( $GLOBALS['sc_active_plugins'], (array) $plugins ) );
}
function delete_plugins( $plugins ) {
	$GLOBALS['sc_event_log'][] = 'delete_plugins:' . implode( ',', $plugins );
	return true;
}
function delete_theme( $theme ) {
	$GLOBALS['sc_event_log'][] = 'delete_theme:' . $theme;
	return true;
}
function wp_insert_post( $post, $wp_error = false ) {
	$GLOBALS['sc_event_log'][] = 'wp_insert_post:' . $post['post_title'];
	return 123;
}
function wp_update_post( $post, $wp_error = false ) {
	$GLOBALS['sc_event_log'][] = 'wp_update_post:' . $post['post_title'];
	return $post['ID'] ?? 123;
}
function wp_delete_post( $post_id, $force_delete = false ) {
	$GLOBALS['sc_event_log'][] = 'wp_delete_post:' . $post_id;
	return true;
}
function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
	$GLOBALS['sc_event_log'][] = 'wp_set_post_terms:' . $taxonomy . ':' . implode( ',', $terms );
	return array( 1 );
}
function clean_post_cache( $post_id ) {
	$GLOBALS['sc_event_log'][] = 'clean_post_cache:' . $post_id;
}
function sanitize_title( $title ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $title ) ) ); }
function flush_rewrite_rules() { $GLOBALS['sc_event_log'][] = 'flush_rewrite_rules'; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_cache_flush() { $GLOBALS['sc_event_log'][] = 'wp_cache_flush'; }

class WP_Error {
	public function get_error_message() { return 'stub error'; }
}

require __DIR__ . '/../site-cleaner.php';

function sc_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$plugin = new Site_Cleaner();

$plugins_method = new ReflectionMethod( Site_Cleaner::class, 'get_plugins_for_form' );
if ( PHP_VERSION_ID < 80100 ) {
	$plugins_method->setAccessible( true );
}
$plugins = $plugins_method->invoke( $plugin );

sc_assert( true === $plugins['worker/init.php']['locked'], 'ManageWP Worker should be locked.' );
sc_assert( false === $plugins['elementor/elementor.php']['locked'], 'Active Elementor should be selectable for deletion.' );
sc_assert( false === $plugins['all-in-one-wp-migration/all-in-one-wp-migration.php']['locked'], 'Active All-in-One WP Migration should be selectable for deletion.' );
sc_assert( true === $plugins['network-active/network-active.php']['locked'], 'Network-active plugins should be locked.' );
sc_assert( false === $plugins['site-cleaner/site-cleaner.php']['default_delete'], 'Site Cleaner should not be selected for deletion by default.' );
sc_assert( false === $plugins['worker/init.php']['default_delete'], 'ManageWP Worker should not be selected for deletion by default.' );
sc_assert( true === $plugins['elementor/elementor.php']['default_delete'], 'Active non-protected plugins should be selected for deletion by default.' );
sc_assert( true === $plugins['all-in-one-wp-migration/all-in-one-wp-migration.php']['default_delete'], 'All-in-One WP Migration should be selected for deletion by default.' );

$themes_method = new ReflectionMethod( Site_Cleaner::class, 'get_themes_for_form' );
if ( PHP_VERSION_ID < 80100 ) {
	$themes_method->setAccessible( true );
}
$themes = $themes_method->invoke( $plugin );

sc_assert( false === $themes['twentytwentyfive']['default_delete'], 'Twenty Twenty-Five should not be selected for deletion by default.' );
sc_assert( true === $themes['old-client-theme']['default_delete'], 'Old client themes should be selected for deletion by default.' );

$runner_options = array(
	'delete_pages'             => false,
	'delete_posts'             => false,
	'delete_media'             => false,
	'delete_menus'             => false,
	'delete_comments'          => false,
	'delete_widgets'           => false,
	'delete_custom_post_types' => false,
	'create_homepage'          => true,
	'update_site_title'        => true,
	'clear_tagline'            => true,
	'remove_site_icon'         => true,
	'reset_permalinks'         => true,
	'ensure_2025_theme'        => false,
	'activate_2025_theme'      => false,
	'site_title'               => 'Clean Test Site',
	'homepage_title'           => 'Clean Test Site',
	'homepage_message'         => 'Ready to build.',
	'plugins_to_delete'        => array( 'contact-form-7/wp-contact-form-7.php', 'elementor/elementor.php', 'worker/init.php', 'network-active/network-active.php' ),
	'themes_to_delete'         => array(),
);

$runner = new Site_Cleaner_Runner( $runner_options, true, 'site-cleaner/site-cleaner.php' );
$dry    = $runner->run();
sc_assert( 'info' === $dry['status'], 'Dry run should return info status.' );
sc_assert( in_array( 'Plugin: Contact Form 7 (contact-form-7/wp-contact-form-7.php)', $dry['items'], true ), 'Selected inactive plugin should be listed for deletion.' );
sc_assert( in_array( 'Plugin: Elementor - active, will be deactivated first (elementor/elementor.php)', $dry['items'], true ), 'Selected active plugin should be listed for deactivation and deletion.' );
sc_assert( ! in_array( 'Plugin: ManageWP Worker (worker/init.php)', $dry['items'], true ), 'ManageWP Worker should not be listed for deletion.' );

$runner = new Site_Cleaner_Runner( array_merge( $runner_options, array( 'plugins_to_delete' => array() ) ), true, 'site-cleaner/site-cleaner.php' );
$dry    = $runner->run();
sc_assert( in_array( 'No plugins selected for deletion.', $dry['items'], true ), 'Dry run should report no selected plugins.' );

$runner = new Site_Cleaner_Runner( array_merge( $runner_options, array( 'plugins_to_delete' => array() ) ), false, 'site-cleaner/site-cleaner.php' );
$clean  = $runner->run();
sc_assert( 'success' === $clean['status'], 'Clean run should return success status.' );
sc_assert( 'Clean Test Site' === $GLOBALS['sc_options']['blogname'], 'Site title should be updated.' );
sc_assert( '' === $GLOBALS['sc_options']['blogdescription'], 'Tagline should be cleared.' );
sc_assert( 'posts' === $GLOBALS['sc_options']['show_on_front'], 'Homepage display should be reset to the default blog home.' );
sc_assert( 0 === $GLOBALS['sc_options']['page_on_front'], 'Static front page should be cleared.' );
sc_assert(
	in_array( 'wp_insert_post:Blog Home', $GLOBALS['sc_event_log'], true ),
	'Clean run should create the no-header/footer blog home template.'
);
sc_assert(
	in_array( 'wp_set_post_terms:wp_theme:twentytwentyfive', $GLOBALS['sc_event_log'], true ),
	'Blog home template should be assigned to the active theme.'
);
sc_assert(
	in_array( 'flush_rewrite_rules', $GLOBALS['sc_event_log'], true ),
	'Permalink reset should flush rewrite rules.'
);

$GLOBALS['sc_event_log'] = array();
$runner                  = new Site_Cleaner_Runner(
	array_merge(
		$runner_options,
		array(
			'themes_to_delete' => array( 'old-client-theme', 'twentytwentyfive' ),
			'create_homepage'  => false,
		)
	),
	false,
	'site-cleaner/site-cleaner.php'
);
$runner->run();
sc_assert(
	in_array( 'deactivate_plugins:elementor/elementor.php', $GLOBALS['sc_event_log'], true ),
	'Selected active plugin should be deactivated before deletion.'
);
sc_assert(
	in_array( 'delete_plugins:contact-form-7/wp-contact-form-7.php,elementor/elementor.php', $GLOBALS['sc_event_log'], true ),
	'Selected plugins should be deleted after active plugins are deactivated.'
);
sc_assert(
	! in_array( 'delete_plugins:worker/init.php', $GLOBALS['sc_event_log'], true ),
	'Plugin deletion should not delete ManageWP Worker.'
);
sc_assert(
	in_array( 'delete_theme:old-client-theme', $GLOBALS['sc_event_log'], true ),
	'Selected theme deletion should delete selected old theme.'
);

echo "Site Cleaner harness checks passed.\n";
