=== MW Local AI Connector ===
Contributors: mattwiebe
Tags: ai, connectors, inference, openai, models
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route WordPress AI requests either to models running on your own machine or to an Actual Computer endpoint.

== Description ==

MW Local AI Connector provides WordPress AI connectors for:

* Local AI: connect WordPress to a self-hosted OpenAI-compatible inference proxy on your own machine.
* Actual Computer: connect WordPress to the fixed Actual Computer API endpoint with your API key.

The plugin integrates with the WordPress Connectors screen and provides a dedicated settings experience for each connector.

This plugin is an independent project by Matt Wiebe and is not affiliated with, endorsed by, or sponsored by Actual Computer, Tailscale, Ollama, LM Studio, or any other third-party service it can be configured to talk to.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it through Composer.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open `Settings > Connectors` or the dedicated settings pages under `Settings`.

== Frequently Asked Questions ==

= Does this plugin include the local proxy? =

The WordPress plugin ships the connector and settings UI. The local proxy and CLI used for Local AI are maintained in the project repository for development and release purposes, but are not included in the production plugin zip.

= Which endpoint does the Actual Computer connector use? =

It uses `https://api.actual.inc/v1`.

== External services ==

This plugin can connect to two third-party services. Both connections only happen after you explicitly configure the matching connector in the WordPress admin and either save it or trigger an AI request through the WordPress AI Client.

= Local AI proxy (user-controlled) =

The Local AI connector sends requests to the OpenAI-compatible HTTPS endpoint URL that you enter on its settings page. This endpoint is intended to point at a proxy you run on your own machine (for example, in front of Ollama, llama.cpp, LM Studio, or vLLM).

* What it sends: the prompts, messages, model selection, and any other AI parameters supplied by callers of the WordPress AI Client, plus a `Bearer` API key so the proxy can authenticate the request. It also calls `/v1/models` on this endpoint to populate the model selector in settings.
* When it sends: when an admin saves connection details (model list refresh) and whenever WordPress generates a response through this connector.
* Where it sends: the endpoint URL you configure on the Local AI settings screen. By default no endpoint is configured and no requests are made.
* Because the endpoint is one you supply, this plugin cannot link a generic terms of service or privacy policy on your behalf — the applicable terms are those of whatever software (and, if you use Tailscale Funnel, whatever network operator) is running at that URL. Tailscale's terms and privacy policy are available at [https://tailscale.com/terms](https://tailscale.com/terms) and [https://tailscale.com/privacy-policy](https://tailscale.com/privacy-policy) if you choose to use Tailscale Funnel to expose the proxy.

= Actual Computer API =

The Actual Computer connector sends requests to the fixed base URL `https://api.actual.inc/v1`, operated by Actual Computer.

* What it sends: the prompts, messages, model selection, and any other AI parameters supplied by callers of the WordPress AI Client, plus the bearer API key you enter on the settings page. It also calls `/v1/models` to populate the model selector.
* When it sends: when an admin saves connection details (model list refresh) and whenever WordPress generates a response through this connector.
* Where it sends: `https://api.actual.inc/v1`.
* Service provider: Actual Computer. Terms of service: [https://actual.inc/terms](https://actual.inc/terms). Privacy policy: [https://actual.inc/privacy](https://actual.inc/privacy).

If you do not configure either connector, this plugin makes no outbound network requests.

== Changelog ==

= 0.2.5 =

* Rename the plugin to "MW Local AI Connector" with the slug `mw-local-ai-connector` and the text domain `mw-local-ai-connector`.
* Move the main plugin file to `mw-local-ai-connector.php` to match the plugin slug.
* Move the inline admin settings CSS and JavaScript into separate enqueued assets.
* Document the third-party services this plugin can connect to.

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
