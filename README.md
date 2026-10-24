# Health Monitor Connector

Install this folder as a separate WordPress plugin on a client site. It does not require the main plugin on the client.

Download the installable `wp-health-monitor-connector.zip` from the [latest GitHub release](https://github.com/rlorakib/wp-health-monitor-connector/releases/latest), then upload it through **Plugins → Add New → Upload Plugin**. The agency Manager administrator supplies the one-time pairing code separately. A GitHub source archive is not the release ZIP.

1. Activate **Health Monitor Connector**.
2. Ask the agency Manager administrator for a fresh pairing code.
3. Open **Settings → Health Connector**, enter the Manager's public HTTPS site URL and the 32-character code, then connect.
4. Select **Send report now** to verify delivery and view the Agency assessment. Daily reports use WordPress cron.

The Connector displays the Manager's validated score, coverage, approved issue statuses and local fix guides in **Settings → Health Connector**. Enable client email notifications there to receive a message when reported issues change or clear. This is off by default and uses the client site's mail setup. The Manager validates the signed report; it does not independently inspect the client's internal configuration.

If the agency uses Pro, its administrator can write feedback after reviewing a client report. The Connector receives the latest message with its next successful report response and displays it on the same settings page. The client can separately opt in to feedback emails for the configured recipient. The message is delivered on the next report, not immediately when the agency saves it.

The Connector sends only a score, coverage, approved issue IDs/statuses and its version. Each request is signed with a per-site secret. The secret is stored encrypted using the client's WordPress salts. Use the Manager's **Revoke** action to disconnect immediately; local disconnect only deletes this site's copy of the credential.

If pairing fails, check HTTPS on both sites, expiry and prior use of the code, REST API access and firewall rules. If reports fail, check the displayed HTTP status and whether the Manager revoked or rotated the connection.

## Development and releases

This repository is the source of the client Connector. Run `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-release.ps1` to create `dist/wp-health-monitor-connector.zip`. The archive contains one `wp-health-monitor-connector/` folder and excludes tests and development files. Tagging a commit with `v` followed by the plugin version builds the same ZIP and attaches it to a GitHub release.

Run `php tests/assessment.php` and, from a WordPress root, `wp eval-file wp-content/plugins/wp-health-monitor-connector/tests/send-feedback.php --skip-plugins --skip-themes` and `wp eval-file wp-content/plugins/wp-health-monitor-connector/tests/notification.php --skip-plugins --skip-themes` before release. The WordPress tests intercept HTTP and email; they do not verify live delivery.
