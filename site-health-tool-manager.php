<?php
/**
 * Plugin Name: Site Health Tool Manager
 * Plugin URI:  https://github.com/earnjam/site-health-tool-manager
 * Description: Control which tests appear in the the Site Health Tool
 * Version:     1.0
 * Author:      William Earnhardt
 * Author URI:  https://wearnhardt.com
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: site-health-tool-manager
 */

/**
 * Register the Site Health Tool settings page
 */
function shtm_add_settings_page() {
	add_submenu_page(
		'options-general.php',
		__( 'Site Health Tool Settings', 'site-health-tool-manager' ),
		__( 'Site Health', 'site-health-tool-manager' ),
		'manage_options',
		'shtm-settings',
		'shtm_settings_page'
	);
}
add_action( 'admin_menu', 'shtm_add_settings_page', 10 );

/**
 * Filters the list of registered Site Health Tool tests
 *
 * @param array $tests The array of registered Site Health tests
 * @return array The filtered list of tests.
 */
function shtm_filter_tests( $tests ) {
	// Don't filter on the plugin settings page
	if ( get_current_screen()->base !== 'settings_page_shtm-settings' ) {
		$hidden_tests = (array) maybe_unserialize( get_option( 'shtm_hidden_tests' ) );
		foreach ( $hidden_tests as $test ) {
			unset( $tests['direct'][ $test ] );
			unset( $tests['async'][ $test ] );
		}
	}

	return $tests;
}
add_filter( 'site_status_tests', 'shtm_filter_tests', 10000 );

/**
 * Output for the Site Health Tool Settings page
 */
function shtm_settings_page() {
	include ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	$tests    = WP_Site_Health::get_tests();
	$disabled = get_option( 'shtm_hidden_tests', array() );
	$enabled  = array();
	if ( isset( $_POST['checked'] ) ) {
		//Validate that submitted tests are actually registered
		$test_names = array_merge( $tests['direct'], $tests['async'] );
		foreach ( $_POST['checked'] as $name ) {
			if ( isset( $test_names[ $name ] ) ) {
				$enabled[] = $name;
			}
		}
		//Only save the list of which tests were not checked.
		//This ensures that any tests that are added after this setting is
		//saved will still get run.
		$new_disabled = array_keys( array_diff_key( $test_names, array_flip( $enabled ) ) );
		update_option( 'shtm_hidden_tests', $new_disabled );
		$disabled = $new_disabled;
	}
	?>
<div class="wrap">
	<h1><?php _e( 'Site Health Tool Settings', 'site-health-tool-manager' ); ?></h1>
	<h2><?php _e( 'Tests Enabled', 'site-health-tool-manager' ); ?></h2>
	<p><?php _e( 'Certain tests may not be relevant to your environment. Uncheck a test to remove it from the Site Health Status screen.', 'site-health-tool-manager' ); ?></p>
	<form method="POST" action="">
		<ul>
		<?php
		foreach ( $tests as $type ) {
			$checked = false;
			foreach ( $type as $test => $details ) {
				$checked = ( ! in_array( $test, $disabled ) );
				echo '<li><input type="checkbox" ';
				if ( $checked ) {
					echo 'checked="checked" ';
				}
				echo 'name="checked[]" id="' . $details['test'] . '" value="' . $test . '" />';
				echo '<label for="' . $details['test'] . '">' . $details['label'] . '</label></li>';
			}
		}
		?>
		</ul>
		<input class="button button-primary" type="submit" value="<?php _e( 'Save Tests', 'site-health-tool-manager' ); ?>" />
	</form>
</div>
	<?php
}
