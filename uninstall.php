<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'shtm_hidden_tests' );
delete_option( 'shtm_widget_enabled' );
delete_option( 'shtm_hidden_modules' );
