<?php
/** Run with wp eval-file wp-content/plugins/wp-health-monitor-connector/tests/notification.php --skip-plugins --skip-themes. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
require dirname( __DIR__ ) . '/wp-health-monitor-connector.php';

$names = array( 'wphmc_notify', 'wphmc_last_notified', 'wphmc_last_feedback_notified' );
$before = array();
foreach ( $names as $name ) { $before[ $name ] = get_option( $name, null ); }
$mail = array();
$intercept = static function ( $pre, $args ) use ( &$mail ) { $mail[] = $args; return true; };
add_filter( 'pre_wp_mail', $intercept, 10, 2 );
$notify = new ReflectionMethod( WPHMC_Connector::class, 'notify_if_changed' );
$notify_feedback = new ReflectionMethod( WPHMC_Connector::class, 'notify_feedback' );
$issue = array( 'status' => 'needs_attention', 'score' => 60, 'coverage' => 100, 'issues' => array( array( 'id' => 'plugin_updates', 'status' => 'warning' ) ) );
$healthy = array( 'status' => 'healthy', 'score' => 100, 'coverage' => 100, 'issues' => array() );

try {
	update_option( 'wphmc_notify', array( 'enabled' => true, 'email' => 'client@example.com' ), false );
	delete_option( 'wphmc_last_notified' );
	$notify->invoke( null, $issue, array() );
	if ( 1 !== count( $mail ) || false === strpos( $mail[0]['message'], 'Plugin updates' ) ) { throw new RuntimeException( 'New issue email was not composed.' ); }
	$notify->invoke( null, $issue, $issue );
	if ( 1 !== count( $mail ) ) { throw new RuntimeException( 'Unchanged issue generated duplicate email.' ); }
	$notify->invoke( null, $healthy, $issue );
	if ( 2 !== count( $mail ) || false === strpos( $mail[1]['message'], 'Previously reported issues have cleared.' ) ) { throw new RuntimeException( 'Recovery email was not composed.' ); }
	$notify->invoke( null, $healthy, $healthy );
	if ( 2 !== count( $mail ) ) { throw new RuntimeException( 'Unchanged healthy status generated duplicate email.' ); }
	update_option( 'wphmc_notify', array( 'enabled' => false, 'email' => 'client@example.com' ), false );
	$notify->invoke( null, $issue, $healthy );
	if ( 2 !== count( $mail ) ) { throw new RuntimeException( 'Disabled notifications generated email.' ); }
	update_option( 'wphmc_notify', array( 'enabled' => true, 'feedback_enabled' => false, 'email' => 'client@example.com' ), false );
	delete_option( 'wphmc_last_feedback_notified' );
	$feedback = array( 'id' => 17, 'message' => 'Please check your plugins.', 'report_at' => '2026-10-15 11:00:00', 'created_at' => '2026-10-15 12:00:00' );
	$notify_feedback->invoke( null, $feedback );
	if ( 2 !== count( $mail ) ) { throw new RuntimeException( 'Issue email setting also enabled feedback email.' ); }
	update_option( 'wphmc_notify', array( 'enabled' => true, 'feedback_enabled' => true, 'email' => 'client@example.com' ), false );
	$notify_feedback->invoke( null, $feedback );
	if ( 3 !== count( $mail ) || false === strpos( $mail[2]['message'], $feedback['message'] ) ) { throw new RuntimeException( 'Agency feedback email was not composed.' ); }
	$notify_feedback->invoke( null, $feedback );
	if ( 3 !== count( $mail ) ) { throw new RuntimeException( 'Agency feedback generated duplicate email.' ); }
	WP_CLI::success( 'Issue, deduplication, recovery and opt-out notifications passed.' );
} finally {
	remove_filter( 'pre_wp_mail', $intercept, 10 );
	foreach ( $before as $name => $value ) {
		if ( null === $value ) { delete_option( $name ); }
		else { update_option( $name, $value, false ); }
	}
}
