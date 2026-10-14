=== Health Monitor Connector ===
Contributors: rlorakib
Tags: site health, monitoring, agency
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect a client WordPress site to a selected WP Health Monitor Agency Manager with small, signed health reports.

== Description ==

Health Monitor Connector runs on a client site. An administrator pairs it with an Agency Manager on another WordPress site using a one-time code. The Connector then sends a concise health summary each day through WP-Cron, or immediately when an administrator selects Send report now. The main WP Health Monitor plugin is needed on the agency site, but not on the client site.

= Client features =

* Check HTTPS, available core/plugin/theme updates, public error display and PHP version support.
* Send a score, check coverage and approved issue IDs/statuses in a signed HTTPS report.
* Show the Manager's validated assessment and relevant next steps in Settings > Health Connector.
* Optionally email a chosen client recipient when reported issues change or clear.
* Disconnect locally when a connection is no longer needed.

The Manager validates the report and returns the accepted score and issue statuses. It does not independently scan the client's internal configuration. Internal agency notes are not sent back to the client.

== Installation ==

1. Install and activate Health Monitor Connector on a public HTTPS client WordPress site.
2. Ask the Agency Manager administrator for a fresh pairing code. It expires after 10 minutes and can be used once.
3. Open Settings > Health Connector on the client, enter the Manager's public HTTPS URL and the code, and select Connect.
4. Select Send report now to confirm delivery and view the assessment. Daily reports depend on WP-Cron or a server cron.
5. If desired, enable client email notifications and choose a recipient on the same screen.

Pairing needs reachable public HTTPS sites, the Manager's REST API and OpenSSL. Email delivery depends on the client site's mail configuration.

== Privacy and data ==

Nothing is sent to an Agency Manager until an administrator pairs the Connector with a Manager URL they choose. During pairing, the Connector sends the one-time code, client site name and client site URL. Reports then send only the score, coverage, approved check IDs and statuses, and Connector version. They do not send passwords, post content, user lists or customer records. The recipient Manager stores the client site details and report history according to its own retention settings.

The per-site reporting secret is stored encrypted in the client site's WordPress database and is used to sign reports. The Connector stores the latest assessment and delivery status locally. Client issue/recovery email is off by default; when enabled, it uses the client site's WordPress mail setup to send the selected recipient the score, coverage, issue guidance and a link to the local Connector screen.

Local Disconnect removes the client's stored connection credentials, but does not erase records already held by the Manager. The Agency Manager administrator should also revoke the site there to stop future reports. Deleting the Connector through WordPress removes its local options and scheduled task.

== Frequently Asked Questions ==

= Does the client site need the main plugin or Pro? =

No. The Connector runs separately on the client. The selected agency site must run WP Health Monitor with Agency Manager enabled.

= Does the agency manually approve every report? =

No. The Manager checks the signed report and returns its accepted assessment automatically. Agency administrators can review reports and keep internal notes on their own site.

= What if delivery fails? =

Check HTTPS, the Manager URL, REST API access, WP-Cron and whether the agency revoked or rotated the connection. The Connector screen shows the last delivery result.

== Changelog ==

= 0.4.0 =
* Show validated Agency assessments and offer opt-in client issue/recovery email notifications.

= 0.3.0 =
* Initial separate Connector with encrypted credentials and signed reports.
