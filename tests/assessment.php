<?php
/** Run with: php tests/assessment.php */

define( 'ABSPATH', __DIR__ );
function register_activation_hook( $file, $callback ): void {}
function register_deactivation_hook( $file, $callback ): void {}
function add_action( $hook, $callback ): void {}

require dirname( __DIR__ ) . '/wp-health-monitor-connector.php';

$validate = new ReflectionMethod( WPHMC_Connector::class, 'valid_assessment' );
$report = array( 'score' => 60, 'coverage' => 100, 'issues' => array( array( 'id' => 'plugin_updates', 'status' => 'warning' ) ) );
$assessment = array( 'status' => 'needs_attention', 'score' => 60, 'coverage' => 100, 'issues' => $report['issues'] );
if ( ! $validate->invoke( null, $assessment, $report ) ) { throw new RuntimeException( 'Matching validated assessment was rejected.' ); }

$forged = $assessment;
$forged['issues'] = array( array( 'id' => 'https', 'status' => 'fail' ) );
if ( $validate->invoke( null, $forged, $report ) ) { throw new RuntimeException( 'Mismatched issue was accepted.' ); }

$unknown = $assessment;
$unknown['issues'] = array( array( 'id' => 'unknown', 'status' => 'fail' ) );
$unknown_report = $report;
$unknown_report['issues'] = $unknown['issues'];
if ( $validate->invoke( null, $unknown, $unknown_report ) ) { throw new RuntimeException( 'Unknown issue ID was accepted.' ); }

$incomplete_report = array( 'score' => 0, 'coverage' => 0, 'issues' => array() );
$incomplete = array( 'status' => 'incomplete', 'score' => 0, 'coverage' => 0, 'issues' => array() );
if ( ! $validate->invoke( null, $incomplete, $incomplete_report ) ) { throw new RuntimeException( 'Incomplete assessment was rejected.' ); }

echo "Assessment validation passed.\n";
