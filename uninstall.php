<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
wp_clear_scheduled_hook( 'wphmc_daily_report' );
delete_option( 'wphmc_connection' );
delete_option( 'wphmc_last_run' );
delete_option( 'wphmc_assessment' );
delete_option( 'wphmc_agency_feedback' );
delete_option( 'wphmc_last_feedback_notified' );
delete_option( 'wphmc_notify' );
delete_option( 'wphmc_last_notified' );
