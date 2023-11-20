<?php
/**
 * Plugin Name: Site Health Tool Manager
 * Plugin URI:  https://github.com/earnjam/site-health-tool-manager
 * Description: Control which tests appear in the the Site Health Tool
 * Version:     1.3
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
	$scr = get_current_screen();
	if ( property_exists($scr, 'base') !== true || $scr->base !== 'settings_page_shtm-settings' ) {
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
 * Filters the list of PHP extension modules
 *
 * @param array $modules The array of PHP extension modules
 * @return array The filtered list of modules.
 */
function shtm_filter_modules( $modules ) {
	$hidden = get_option( 'shtm_hidden_modules' );

	$hidden_modules = explode( ',', $hidden );

	foreach ( $hidden_modules as $module ) {
		if ( isset( $modules[$module] ) ) unset( $modules[$module] );
	}

	return $modules;
}
add_filter( 'site_status_test_php_modules', 'shtm_filter_modules', 10000 );

/**
 * Disable the dashboard widget
 */
function shtm_filter_dashboard_widget() {
	if ( ! get_option( 'shtm_widget_enabled', 1 ) ) {
		global $wp_meta_boxes;
		unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_site_health'] );
	}
}
add_action( 'wp_dashboard_setup', 'shtm_filter_dashboard_widget' );


/**
 * Output for the Site Health Tool Settings page
 */
function shtm_settings_page() { ?>

	<div class="wrap">
		<h1><?php _e( 'Site Health Tool Settings', 'site-health-tool-manager' ); ?></h1>

	<?php
	// Verify user has proper capability to view this page
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Sorry, you are not allowed to manage Site Health tests for this site.', 'site-health-tool-manager' ) );
	}

	include_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	$tests    = WP_Site_Health::get_tests();
	$disabled = get_option( 'shtm_hidden_tests', array() );
	$widget   = get_option( 'shtm_widget_enabled', 1 );
	$modules  = get_option( 'shtm_hidden_modules', '' );
	$enabled  = array();

	// If tests have been submitted, process the form data
	if ( isset( $_POST['submit'] ) ) {

		// Verify form nonce before saving
		if ( isset( $_POST['shtm-disable-tests-nonce'] ) && wp_verify_nonce( $_POST['shtm-disable-tests-nonce'], 'shtm-disable-tests' ) ) {

			// Validate that submitted tests are actually registered
			$test_names = array_merge( $tests['direct'], $tests['async'] );
			if ( isset( $_POST['checked'] ) ) {
				foreach ( $_POST['checked'] as $name ) {
					if ( isset( $test_names[ $name ] ) ) {
						$enabled[] = $name;
					}
				}
			}

			// Only save the list of which tests were not checked.
			// This ensures that any tests that are added after this setting is
			// saved will still get run.
			$new_disabled = array_keys( array_diff_key( $test_names, array_flip( $enabled ) ) );
			update_option( 'shtm_hidden_tests', $new_disabled, false );
			$disabled = $new_disabled;

			$widget = ( isset( $_POST['widget'] ) ) ? 1 : 0;
			update_option( 'shtm_widget_enabled', $widget, false );

			// Save option for excluding PHP extension modules
			$modules = ( isset( $_POST['modules'] ) ) ? $_POST['modules'] : '';
			
			// Keep in mind checking for NUL when dealing with user input
			// see https://st-g.de/2011/04/doing-filename-checks-securely-in-PHP
			$modules = str_replace(chr(0), '', $modules);

			$modules = sanitize_text_field($modules);			
			update_option( 'shtm_hidden_modules', $modules, false );

			$classes = 'notice notice-success is-dismissible';
			$message = __( 'Settings saved.', 'site-health-tool-manager' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $classes ), esc_html( $message ) );

		} else {
			// Invalid or missing nonce
			$classes = 'notice notice-error is-dismissible';
			$message = __( 'Unable to submit this form, please try again. Your changes have not been saved.', 'site-health-tool-manager' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $classes ), esc_html( $message ) );

		}
	}
	?>
	<form method="POST" action="">
		<h2><?php _e( 'Tests Enabled', 'site-health-tool-manager' ); ?></h2>
		<p><?php _e( 'Certain tests may not be relevant to your environment. Uncheck a test to remove it from the Site Health Status screen.', 'site-health-tool-manager' ); ?></p>
			<?php wp_nonce_field( 'shtm-disable-tests', 'shtm-disable-tests-nonce' ); ?>
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
					echo 'name="checked[]" id="' . $test . '" value="' . $test . '" />';
					
					// Fallback for tests that don't set label and/or use class-based checks
					if ( isset( $details['label'] ) ) {
						$label = $details['label'];
					} else if ( is_array( $details ) ) { 
						$label = __( 'Class', 'site-health-tool-manager' ) . ' ' . get_class( $details['test'][0] );					
					} else {
						$label = __( 'Function', 'site-health-tool-manager' ) . ' ' . $details['test'];
					}
					echo '<label for="' . $test . '">' . $label . '</label>';

					// Option for excluding PHP extension modules
					if ( $test == "php_extensions" ) {
						echo '<br/><label for="modules">' . __( 'Exclude PHP Extensions', 'site-health-tool-manager' ) . '</label>&nbsp;&nbsp;';
						echo '<input type="text" name="modules" size="50" value="' . esc_attr($modules) . '" />';
					}
					
					echo '</li>';					
				}
			}
			?>
			</ul>
		<h2><?php _e( 'Other Settings', 'site-health-tool-manager' ); ?></h2>
		<ul>
			<li>
				<input type="checkbox" name="widget" id="widget-setting"
					<?php
					if ( $widget ) {
						echo 'checked="checked"';
					}
					?>
				/>
				<label for="widget-setting"><?php _e( 'Dashboard Widget Enabled', 'site-health-tool-manager' ); ?></label>
			</li>
		</ul>
		<p>
			<input class="button button-primary" type="submit" value="<?php _e( 'Save Settings', 'site-health-tool-manager' ); ?>" name="submit" />
		</p>
	</form>
</div>
	<?php
}
