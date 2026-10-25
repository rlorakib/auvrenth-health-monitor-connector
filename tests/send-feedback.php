<?php
/** Run with wp eval-file wp-content/plugins/auvrenth-health-monitor-connector/tests/send-feedback.php --skip-plugins --skip-themes. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
require dirname( __DIR__ ) . '/auvrenth-health-monitor-connector.php';

$names = array( 'wphmc_connection', 'wphmc_assessment', 'wphmc_agency_feedback', 'wphmc_last_run', 'wphmc_notify', 'wphmc_last_notified', 'wphmc_last_feedback_notified' );
$before = array();
foreach ( $names as $name ) { $before[ $name ] = get_option( $name, null ); }
$encrypt = new ReflectionMethod( WPHMC_Connector::class, 'encrypt' );
$secret = str_repeat( 'a', 64 );
$ciphertext = $encrypt->invoke( null, $secret );
if ( ! is_string( $ciphertext ) ) { WP_CLI::error( 'OpenSSL is required for the Connector test.' ); }
$tamper = false;
$bad_feedback = false;
$requests = 0;
$intercept = static function ( $pre, $args, $url ) use ( &$tamper, &$bad_feedback, &$requests, $secret ) {
	if ( 'https://example.com/wp-json/wphm/v1/sites/77/reports' !== $url ) {
		return new WP_Error( 'wphmc_test_blocked', 'External requests are disabled by this test.' );
	}
	++$requests;
	$body = $args['body'];
	$headers = $args['headers'];
	$expected = hash_hmac( 'sha256', "POST\n/wphm/v1/sites/77/reports\n{$headers['X-WPHM-Timestamp']}\n{$headers['X-WPHM-Request-ID']}\n{$body}", $secret );
	if ( ! hash_equals( $expected, $headers['X-WPHM-Signature'] ) ) {
		return new WP_Error( 'wphmc_test_signature', 'Connector signature did not match.' );
	}
	$report = json_decode( $body, true );
	$assessment = array(
		'status' => $report['coverage'] < 50 ? 'incomplete' : ( $report['issues'] ? 'needs_attention' : 'healthy' ),
		'score' => $tamper ? ( 100 === $report['score'] ? 99 : $report['score'] + 1 ) : $report['score'],
		'coverage' => $report['coverage'],
		'issues' => $report['issues'],
	);
	$feedback = array( 'id' => 17, 'message' => 'Please review the available plugin updates.', 'report_at' => '2026-10-15 11:00:00', 'created_at' => '2026-10-15 12:00:00' );
	if ( $bad_feedback ) { $feedback['message'] = str_repeat( 'x', 4001 ); }
	return array( 'headers' => array(), 'body' => wp_json_encode( array( 'received' => true, 'assessment' => $assessment, 'feedback' => $feedback ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};

try {
	update_option( 'wphmc_connection', array( 'site_id' => 77, 'report_url' => 'https://example.com/wp-json/wphm/v1/sites/77/reports', 'manager_url' => 'https://example.com', 'secret' => $ciphertext ), false );
	update_option( 'wphmc_notify', array( 'enabled' => false, 'email' => '' ), false );
	add_filter( 'pre_http_request', $intercept, 10, 3 );
	$sent = WPHMC_Connector::send();
	$stored = get_option( 'wphmc_assessment', array() );
	$stored_feedback = get_option( 'wphmc_agency_feedback', array() );
	if ( is_wp_error( $sent ) || 1 !== $requests || empty( $stored['received_at'] ) || ! in_array( $stored['status'], array( 'healthy', 'needs_attention', 'incomplete' ), true ) || 17 !== ( $stored_feedback['id'] ?? null ) ) {
		throw new RuntimeException( 'Signed report feedback was not saved.' );
	}
	$bad_feedback = true;
	$rejected_feedback = WPHMC_Connector::send();
	if ( ! is_wp_error( $rejected_feedback ) || 'wphmc_bad_feedback' !== $rejected_feedback->get_error_code() || get_option( 'wphmc_agency_feedback' ) !== $stored_feedback ) {
		throw new RuntimeException( 'Invalid agency feedback was accepted.' );
	}
	$bad_feedback = false;
	$tamper = true;
	$rejected = WPHMC_Connector::send();
	if ( ! is_wp_error( $rejected ) || 'wphmc_bad_assessment' !== $rejected->get_error_code() || get_option( 'wphmc_assessment' ) !== $stored ) {
		throw new RuntimeException( 'Mismatched Manager assessment was accepted or overwrote the good one.' );
	}
	WP_CLI::success( 'Signed report assessment and agency feedback are stored; invalid responses are rejected.' );
} finally {
	remove_filter( 'pre_http_request', $intercept, 10 );
	foreach ( $before as $name => $value ) {
		if ( null === $value ) { delete_option( $name ); }
		else { update_option( $name, $value, false ); }
	}
}
