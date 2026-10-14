<?php
/**
 * Plugin Name: Health Monitor Connector
 * Description: Sends a small signed health summary to a paired WP Health Monitor Manager.
 * Version: 0.4.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Md. Rakib Hossen
 * License: GPL-2.0-or-later
 * Text Domain: wp-health-monitor-connector
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WPHMC_Connector {
	private const OPTION = 'wphmc_connection';
	private const LAST = 'wphmc_last_run';
	private const CRON = 'wphmc_daily_report';
	private const ASSESSMENT = 'wphmc_assessment';
	private const NOTIFY = 'wphmc_notify';
	private const LAST_NOTIFIED = 'wphmc_last_notified';
	private const GUIDES = array(
		'https' => array( 'HTTPS configuration', 'Check the site certificate and WordPress URL settings.' ),
		'core_updates' => array( 'WordPress core updates', 'Back up the site, then review Dashboard > Updates.' ),
		'plugin_updates' => array( 'Plugin updates', 'Back up the site, then review Plugins > Installed Plugins.' ),
		'theme_updates' => array( 'Theme updates', 'Back up the site, then review Dashboard > Updates.' ),
		'debug_display' => array( 'Public error display', 'Disable public PHP error display on production sites.' ),
		'php_version' => array( 'PHP version', 'Ask your host about a supported PHP version after checking compatibility.' ),
	);

	public static function boot(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_wphmc_action', array( __CLASS__, 'action' ) );
		add_action( self::CRON, array( __CLASS__, 'send' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON ); }
	}

	public static function activate(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON ); }
	}

	public static function deactivate(): void { wp_clear_scheduled_hook( self::CRON ); }

	private static function key(): string { return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true ); }

	private static function encrypt( string $plain ): string|false {
		if ( ! function_exists( 'openssl_encrypt' ) ) { return false; }
		$iv = random_bytes( 12 ); $tag = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $cipher ? false : base64_encode( $iv . $tag . $cipher );
	}

	private static function decrypt( string $value ): string|false {
		$raw = base64_decode( $value, true );
		if ( ! function_exists( 'openssl_decrypt' ) || false === $raw || strlen( $raw ) < 28 ) { return false; }
		return openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
	}

	public static function menu(): void {
		add_options_page( 'Health Connector', 'Health Connector', 'manage_options', 'wphmc-connector', array( __CLASS__, 'page' ) );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
		$connection = get_option( self::OPTION, array() );
		$last = get_option( self::LAST, array() );
		$assessment = get_option( self::ASSESSMENT, array() );
		$notify = get_option( self::NOTIFY, array() );
		$notice = sanitize_key( $_GET['notice'] ?? '' );
		$messages = array( 'paired' => 'Connected to the Manager.', 'pair_failed' => 'Pairing failed. Check the HTTPS Manager URL and unused code.', 'sent' => 'Signed report sent. See the Agency assessment below.', 'send_failed' => 'Report or assessment failed. Check the connection and Manager status.', 'disconnected' => 'Local credentials removed. Revoke the site in Manager too.', 'settings_saved' => 'Client notifications saved.' );
		$notice_type = in_array( $notice, array( 'pair_failed', 'send_failed' ), true ) ? 'error' : 'success';
		?>
		<div class="wrap" style="width:calc(100% - 40px);max-width:800px;margin:24px auto 40px"><h1>Health Monitor Connector</h1><p>Send read-only health summaries to your agency Manager. No passwords, post content or customer records are sent.</p>
		<?php if ( isset( $messages[ $notice ] ) ) : ?>
			<style>.wphmc-toast{position:fixed;z-index:100000;top:48px;inset-inline-end:24px;box-sizing:border-box;width:min(360px,calc(100vw - 32px));padding:13px 16px;border:1px solid #b7dfc8;border-inline-start:4px solid #16824b;border-radius:10px;background:#f1fbf5;box-shadow:0 14px 36px rgba(23,35,60,.16);color:#16492d;font-weight:600;line-height:1.4;transition:opacity .2s ease,transform .2s ease}.wphmc-toast--error{border-color:#f0c2bd;border-inline-start-color:#c94337;background:#fff5f3;color:#8d2b24}.wphmc-toast--leaving{opacity:0;transform:translateY(-8px);pointer-events:none}@media(max-width:600px){.wphmc-toast{top:58px;inset-inline-end:16px}}</style>
			<div class="wphmc-toast wphmc-toast--<?php echo esc_attr( $notice_type ); ?>" id="wphmc-toast" role="<?php echo 'error' === $notice_type ? 'alert' : 'status'; ?>"><?php echo esc_html( $messages[ $notice ] ); ?></div>
			<script>(()=>{const show=()=>{const toast=document.getElementById('wphmc-toast');const url=new URL(location.href);url.searchParams.delete('notice');history.replaceState(history.state,'',url.pathname+url.search+url.hash);setTimeout(()=>{toast.classList.add('wphmc-toast--leaving');setTimeout(()=>toast.remove(),200)},1500)};if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',show,{once:true})}else{show()}})();</script>
		<?php endif; ?>
		<?php if ( ! empty( $connection['site_id'] ) ) : ?>
			<div class="card" style="max-width:none;padding:20px"><h2>Connected</h2><p><strong>Manager:</strong> <?php echo esc_html( $connection['manager_url'] ); ?><br><strong>Site ID:</strong> <?php echo (int) $connection['site_id']; ?><br><strong>Last send (UTC):</strong> <?php echo esc_html( $last['time'] ?? 'Not yet' ); ?><br><strong>Result:</strong> <?php echo esc_html( $last['result'] ?? 'Pending' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><input type="hidden" name="action" value="wphmc_action"><input type="hidden" name="operation" value="send"><?php wp_nonce_field( 'wphmc_send' ); ?><button type="submit" class="button button-primary">Send report now</button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><input type="hidden" name="action" value="wphmc_action"><input type="hidden" name="operation" value="disconnect"><?php wp_nonce_field( 'wphmc_disconnect' ); ?><button type="submit" class="button">Disconnect locally</button></form></div>
			<div class="card" style="max-width:none;padding:20px"><h2>Agency assessment</h2>
			<?php if ( is_array( $assessment ) && ! empty( $assessment['received_at'] ) && isset( $assessment['status'], $assessment['score'], $assessment['coverage'], $assessment['issues'] ) && is_array( $assessment['issues'] ) ) : ?>
				<p><strong>Status:</strong> <?php echo esc_html( ucwords( str_replace( '_', ' ', $assessment['status'] ) ) ); ?> · <strong>Score:</strong> <?php echo (int) $assessment['score']; ?>/100 · <strong>Coverage:</strong> <?php echo (int) $assessment['coverage']; ?>% · <strong>Received (UTC):</strong> <?php echo esc_html( $assessment['received_at'] ); ?></p>
				<?php if ( 'incomplete' === $assessment['status'] ) : ?><p>Less than half of the checks could run. Review the client site's update data and PHP checks.</p><?php endif; ?>
				<?php if ( ! empty( $assessment['issues'] ) ) : ?><ul><?php foreach ( $assessment['issues'] as $issue ) : if ( ! isset( self::GUIDES[ $issue['id'] ?? '' ], $issue['status'] ) ) { continue; } $guide = self::GUIDES[ $issue['id'] ]; ?><li><strong><?php echo esc_html( $guide[0] ); ?> (<?php echo esc_html( $issue['status'] ); ?>):</strong> <?php echo esc_html( $guide[1] ); ?></li><?php endforeach; ?></ul><?php else : ?><p>No open issues were reported by the completed checks.</p><?php endif; ?>
			<?php else : ?><p>No assessment received yet. Send a report, or update the Agency Manager if delivery already succeeded.</p><?php endif; ?>
			<p class="description">The Agency Manager validates the signed report and returns these results. It does not independently scan this site's internal settings.</p></div>
			<form class="card" style="max-width:none;padding:20px" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><h2>Client notifications</h2><input type="hidden" name="action" value="wphmc_action"><input type="hidden" name="operation" value="notify"><?php wp_nonce_field( 'wphmc_notify' ); ?><p><label><input type="checkbox" name="email_enabled" value="1" <?php checked( ! empty( $notify['enabled'] ) ); ?>> Email new or changed issues and recovery</label></p><p><label for="wphmc-email">Recipient</label><br><input id="wphmc-email" type="email" class="regular-text" name="email" value="<?php echo esc_attr( $notify['email'] ?? get_option( 'admin_email' ) ); ?>"></p><button type="submit" class="button">Save notifications</button><p class="description">Only changes to issue status trigger email. Delivery depends on this site's mail setup.</p></form>
		<?php else : ?>
			<form class="card" style="max-width:none;padding:20px" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><h2>Pair with an agency Manager</h2><p>Enable Agency Manager on a public HTTPS WordPress site, generate a code there, and enter it here within 10 minutes.</p><input type="hidden" name="action" value="wphmc_action"><input type="hidden" name="operation" value="pair"><?php wp_nonce_field( 'wphmc_pair' ); ?><p><label for="wphmc-manager">Manager site URL</label><br><input id="wphmc-manager" type="url" class="regular-text" name="manager_url" placeholder="https://agency.example.com" required></p><p><label for="wphmc-code">Pairing code</label><br><input id="wphmc-code" type="text" class="regular-text" name="code" minlength="32" maxlength="32" autocomplete="off" required></p><button type="submit" class="button button-primary">Connect</button><?php if ( ! empty( $last['result'] ) ) : ?><p>Last attempt: <?php echo esc_html( $last['result'] ); ?></p><?php endif; ?><p class="description">If pairing fails, check that both sites use public HTTPS, the code is unused, and the Manager REST API is reachable.</p></form>
		<?php endif; ?></div>
		<?php
	}

	public static function action(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
		$operation = sanitize_key( $_POST['operation'] ?? '' );
		check_admin_referer( 'wphmc_' . $operation );
		$notice = 'pair_failed';
		if ( 'pair' === $operation ) {
			$result = self::pair( wp_unslash( $_POST['manager_url'] ?? '' ), wp_unslash( $_POST['code'] ?? '' ) );
			$notice = is_wp_error( $result ) ? 'pair_failed' : 'paired';
			update_option( self::LAST, array( 'time' => current_time( 'mysql', true ), 'result' => is_wp_error( $result ) ? $result->get_error_code() : 'Paired' ), false );
		}
		elseif ( 'send' === $operation ) { $notice = is_wp_error( self::send() ) ? 'send_failed' : 'sent'; }
		elseif ( 'notify' === $operation ) {
			$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			if ( ! empty( $_POST['email_enabled'] ) && ! is_email( $email ) ) { wp_die( 'Enter a valid notification email.', '', array( 'response' => 400 ) ); }
			update_option( self::NOTIFY, array( 'enabled' => ! empty( $_POST['email_enabled'] ), 'email' => is_email( $email ) ? $email : '' ), false );
			$notice = 'settings_saved';
		}
		elseif ( 'disconnect' === $operation ) { delete_option( self::OPTION ); delete_option( self::ASSESSMENT ); delete_option( self::LAST_NOTIFIED ); $notice = 'disconnected'; }
		else { wp_die( 'Invalid action', '', array( 'response' => 400 ) ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'wphmc-connector', 'notice' => $notice ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function pair( string $manager_url, string $code ): true|\WP_Error {
		$base = untrailingslashit( esc_url_raw( trim( $manager_url ) ) );
		if ( 'https' !== wp_parse_url( $base, PHP_URL_SCHEME ) || ! wp_http_validate_url( $base ) || ! preg_match( '/^[a-f0-9]{32}$/i', $code ) || 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			return new \WP_Error( 'wphmc_invalid_input', 'Public HTTPS URLs and a valid code are required.' );
		}
		$body = wp_json_encode( array( 'code' => strtolower( $code ), 'name' => get_bloginfo( 'name' ), 'site_url' => home_url() ) );
		$urls = array( $base . '/wp-json/wphm/v1/pairing/complete', add_query_arg( 'rest_route', '/wphm/v1/pairing/complete', $base . '/' ) );
		$last_status = 0;
		foreach ( $urls as $url ) {
			$response = wp_safe_remote_post( $url, array( 'timeout' => 10, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => $body, 'limit_response_size' => 8192 ) );
			if ( is_wp_error( $response ) ) { continue; }
			$last_status = wp_remote_retrieve_response_code( $response );
			if ( 404 === wp_remote_retrieve_response_code( $response ) ) { continue; }
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) { break; }
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['site_id'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $data['secret'] ?? '' ) ) { break; }
			$report_url = esc_url_raw( (string) ( $data['report_url'] ?? '' ) );
			if ( 'https' !== wp_parse_url( $report_url, PHP_URL_SCHEME ) || wp_parse_url( $report_url, PHP_URL_HOST ) !== wp_parse_url( $base, PHP_URL_HOST ) || ! wp_http_validate_url( $report_url ) ) { break; }
			$encrypted = self::encrypt( $data['secret'] );
			if ( false === $encrypted ) { break; }
			update_option( self::OPTION, array( 'manager_url' => $base, 'report_url' => $report_url, 'site_id' => (int) $data['site_id'], 'secret' => $encrypted ), false );
			delete_option( self::ASSESSMENT );
			delete_option( self::LAST_NOTIFIED );
			return true;
		}
		return new \WP_Error( $last_status ? 'wphmc_pair_http_' . $last_status : 'wphmc_pair_unreachable', 'The Manager did not complete pairing.' );
	}

	public static function send(): true|\WP_Error {
		$connection = get_option( self::OPTION, array() );
		if ( empty( $connection['site_id'] ) || empty( $connection['report_url'] ) ) { return new \WP_Error( 'wphmc_not_connected', 'Not connected.' ); }
		$secret = self::decrypt( $connection['secret'] ?? '' );
		if ( false === $secret ) { return new \WP_Error( 'wphmc_secret_unavailable', 'Credential cannot be decrypted.' ); }
		$report = self::collect();
		$body = wp_json_encode( $report );
		$timestamp = (string) time();
		$request_id = wp_generate_uuid4();
		$path = '/wphm/v1/sites/' . (int) $connection['site_id'] . '/reports';
		$signature = hash_hmac( 'sha256', "POST\n{$path}\n{$timestamp}\n{$request_id}\n{$body}", $secret );
		$response = wp_safe_remote_post( $connection['report_url'], array( 'timeout' => 10, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/json', 'X-WPHM-Timestamp' => $timestamp, 'X-WPHM-Request-ID' => $request_id, 'X-WPHM-Signature' => $signature ), 'body' => $body, 'limit_response_size' => 8192 ) );
		$success = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
		if ( ! $success ) {
			update_option( self::LAST, array( 'time' => current_time( 'mysql', true ), 'result' => is_wp_error( $response ) ? $response->get_error_code() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) ), false );
			return new \WP_Error( 'wphmc_send_failed', 'Report delivery failed.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $data['new_secret'] ) && is_string( $data['new_secret'] ) && preg_match( '/^[a-f0-9]{64}$/', $data['new_secret'] ) ) {
			$encrypted = self::encrypt( $data['new_secret'] );
			if ( false !== $encrypted ) { $connection['secret'] = $encrypted; update_option( self::OPTION, $connection, false ); }
		}
		if ( is_array( $data ) && ! array_key_exists( 'assessment', $data ) && ! empty( $data['received'] ) ) {
			delete_option( self::ASSESSMENT );
			update_option( self::LAST, array( 'time' => current_time( 'mysql', true ), 'result' => 'Delivered; Manager assessment unavailable' ), false );
			return true;
		}
		$assessment = is_array( $data ) ? ( $data['assessment'] ?? null ) : null;
		if ( true !== ( $data['received'] ?? null ) || ! self::valid_assessment( $assessment, $report ) ) {
			update_option( self::LAST, array( 'time' => current_time( 'mysql', true ), 'result' => 'Invalid Agency assessment' ), false );
			return new \WP_Error( 'wphmc_bad_assessment', 'The Manager accepted the report but returned an invalid assessment.' );
		}
		$previous = get_option( self::ASSESSMENT, array() );
		$assessment['received_at'] = current_time( 'mysql', true );
		update_option( self::ASSESSMENT, $assessment, false );
		update_option( self::LAST, array( 'time' => $assessment['received_at'], 'result' => 'Delivered and assessed' ), false );
		self::notify_if_changed( $assessment, is_array( $previous ) ? $previous : array() );
		return true;
	}

	private static function valid_assessment( mixed $assessment, array $report ): bool {
		if ( ! is_array( $assessment ) || ! isset( $assessment['status'], $assessment['score'], $assessment['coverage'], $assessment['issues'] ) || ! is_string( $assessment['status'] ) || ! in_array( $assessment['status'], array( 'healthy', 'needs_attention', 'incomplete' ), true ) || ! is_int( $assessment['score'] ) || ! is_int( $assessment['coverage'] ) || ! is_array( $assessment['issues'] ) ) { return false; }
		$expected = $report['coverage'] < 50 ? 'incomplete' : ( $report['issues'] ? 'needs_attention' : 'healthy' );
		if ( $assessment['status'] !== $expected || $assessment['score'] !== $report['score'] || $assessment['coverage'] !== $report['coverage'] || $assessment['issues'] !== $report['issues'] ) { return false; }
		foreach ( $assessment['issues'] as $issue ) {
			if ( ! is_array( $issue ) || ! isset( $issue['id'], $issue['status'] ) || ! isset( self::GUIDES[ $issue['id'] ] ) || ! in_array( $issue['status'], array( 'warning', 'fail' ), true ) ) { return false; }
		}
		return true;
	}

	private static function notify_if_changed( array $assessment, array $previous ): void {
		$notify = get_option( self::NOTIFY, array() );
		if ( ! is_array( $notify ) ) { return; }
		$email = sanitize_email( $notify['email'] ?? '' );
		if ( empty( $notify['enabled'] ) || ! is_email( $email ) ) { return; }
		$current = self::issue_fingerprint( $assessment['issues'] );
		$old = self::issue_fingerprint( is_array( $previous['issues'] ?? null ) ? $previous['issues'] : array() );
		$last_notified = get_option( self::LAST_NOTIFIED, '' );
		if ( ( ! $assessment['issues'] && ! ( $previous['issues'] ?? array() ) ) || ( $current === $old && $current === $last_notified ) ) { return; }
		$lines = array( 'Agency health assessment for ' . get_bloginfo( 'name' ), 'Score: ' . $assessment['score'] . '/100; coverage: ' . $assessment['coverage'] . '%', '' );
		if ( $assessment['issues'] ) {
			foreach ( $assessment['issues'] as $issue ) {
				$guide = self::GUIDES[ $issue['id'] ];
				$lines[] = '- ' . $guide[0] . ' (' . $issue['status'] . '): ' . $guide[1];
			}
		} else {
			$lines[] = 'Previously reported issues have cleared.';
		}
		$lines[] = '';
		$lines[] = admin_url( 'options-general.php?page=wphmc-connector' );
		if ( wp_mail( $email, '[Health Connector] ' . ( $assessment['issues'] ? 'Issues need attention' : 'Issues cleared' ), implode( "\n", $lines ) ) ) {
			update_option( self::LAST_NOTIFIED, $current, false );
		}
	}

	private static function issue_fingerprint( array $issues ): string {
		$states = array_map( static fn( $issue ) => $issue['id'] . ':' . $issue['status'], $issues );
		sort( $states );
		return hash( 'sha256', implode( '|', $states ) );
	}

	/** Collect only bounded status IDs. No content, usernames, passwords or paths. */
	private static function collect(): array {
		$states = array();
		$states['https'] = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) && 'https' === wp_parse_url( site_url(), PHP_URL_SCHEME ) ? 'pass' : 'fail';
		foreach ( array( 'core' => 'core_updates', 'plugins' => 'plugin_updates', 'themes' => 'theme_updates' ) as $kind => $id ) {
			$transient = get_site_transient( 'update_' . $kind );
			if ( ! is_object( $transient ) || ! isset( $transient->last_checked ) || time() - (int) $transient->last_checked > 7 * DAY_IN_SECONDS ) { $states[ $id ] = 'skipped'; continue; }
			if ( 'core' === $kind ) {
				$count = 0;
				foreach ( (array) ( $transient->updates ?? array() ) as $update ) { if ( in_array( $update->response ?? '', array( 'upgrade', 'autoupdate' ), true ) ) { ++$count; } }
			} else { $count = count( (array) ( $transient->response ?? array() ) ); }
			$states[ $id ] = $count ? 'warning' : 'pass';
		}
		$debug_exposed = ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) ) || filter_var( ini_get( 'display_errors' ), FILTER_VALIDATE_BOOLEAN );
		$states['debug_display'] = $debug_exposed && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ? 'skipped' : ( $debug_exposed ? 'warning' : 'pass' );
		$php = wp_check_php_version();
		$states['php_version'] = ! is_array( $php ) || ! isset( $php['recommended_version'], $php['is_secure'], $php['is_acceptable'] ) ? 'skipped' : ( ! $php['is_secure'] ? 'fail' : ( ! $php['is_acceptable'] || version_compare( PHP_VERSION, $php['recommended_version'], '<' ) ? 'warning' : 'pass' ) );
		$weights = array( 'https' => 3, 'core_updates' => 3, 'plugin_updates' => 2, 'theme_updates' => 1, 'debug_display' => 2, 'php_version' => 2 );
		$total = array_sum( $weights ); $run = 0; $earned = 0; $issues = array();
		foreach ( $states as $id => $status ) {
			if ( 'skipped' === $status ) { continue; }
			$run += $weights[ $id ];
			$earned += $weights[ $id ] * ( 'pass' === $status ? 100 : ( 'warning' === $status ? 50 : 0 ) );
			if ( 'pass' !== $status ) { $issues[] = array( 'id' => $id, 'status' => $status ); }
		}
		return array( 'score' => $run ? (int) round( $earned / $run ) : 0, 'coverage' => (int) round( 100 * $run / $total ), 'issues' => $issues, 'plugin_version' => '0.4.0' );
	}
}

register_activation_hook( __FILE__, array( WPHMC_Connector::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( WPHMC_Connector::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( WPHMC_Connector::class, 'boot' ) );
