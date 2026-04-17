=== AI Connector for Local AI ===
Contributors: mattwiebe
Tags: ai, connectors, inference, openai, models, mw-local-ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route WordPress AI requests either to models running on your own machine or to an Actual Computer endpoint.

== Description ==

AI Connector for Local AI provides WordPress AI connectors for:

* Local AI: connect WordPress to a self-hosted OpenAI-compatible inference proxy on your own machine.
* Actual Computer: connect WordPress to the fixed Actual Computer API endpoint with your API key.

The plugin integrates with the WordPress Connectors screen and provides a dedicated settings experience for each connector.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it through Composer.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open `Settings > Connectors` or the dedicated settings pages under `Settings`.

== Frequently Asked Questions ==

= Does this plugin include the local proxy? =

The WordPress plugin ships the connector and settings UI. The local proxy and CLI used for Local AI are maintained in this repository for development and release purposes, but are not included in the production plugin zip.

= Which endpoint does the Actual Computer connector use? =

It uses `https://api.actual.inc/v1`.

== Changelog ==

= 0.2.1 =

* Move development and release documentation into `docs/developing.md`.
* Refresh plugin metadata and WordPress.org tags.

= 0.2.0 =

* Rename the plugin and repository to AI Connector for Local AI.
* Fully migrate Local AI internals from the old Home Inference identifiers with no compatibility layer.
* Keep Actual Computer under the same plugin with updated packaging and release metadata.

= 0.1.6 =

* Add an Actual Computer connector with a simplified API key flow.
* Improve packaged plugin assets and release contents for plugin-directory checks.
