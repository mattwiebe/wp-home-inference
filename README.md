# MW Local AI Connector

MW Local AI Connector is a WordPress AI provider plugin for connecting WordPress to local, OpenAI-compatible inference providers through a proxy you control.

It is built for WordPress 7.0+ and the new Connectors primitives. The WordPress plugin runs on the site. The recommended local proxy helper is [SLOProxy](https://github.com/mattwiebe/sloproxy), a separate npm package.

This is an independent project by Matt Wiebe. It is not affiliated with, endorsed by, or sponsored by Actual Computer, Tailscale, Ollama, LM Studio, or any other third-party service it can be configured to talk to.

## What It Does

- Registers a `mwlai` AI provider with the WordPress AI Client.
- Adds a Settings screen for entering the proxy URL, optional API key, and selected model.
- Loads model choices from the configured `/v1/models` endpoint.
- Restricts the provider to the selected model so WordPress auto-discovery consistently uses it.

## Repository Layout

- [`mw-local-ai-connector.php`](mw-local-ai-connector.php): WordPress plugin bootstrap, settings UI, connector registration.
- [`src/`](src): provider, model, and metadata directory classes.
- [`assets/`](assets): admin UI assets.
- [`tests/phpunit/`](tests/phpunit): WordPress PHPUnit coverage.

## Requirements

- WordPress `7.0+`
- PHP `7.4+`
- A user-controlled OpenAI-compatible proxy endpoint. SLOProxy is recommended for local providers.

## WordPress Plugin Setup

1. Copy this plugin into `wp-content/plugins/mwlai-connector`.
2. Activate the plugin in WordPress.
3. Open `Settings > Connectors` or the dedicated Local AI settings page.
4. Enter:
   - the proxy endpoint URL,
   - the shared API key, when using a tunneled proxy,
   - the model to expose to WordPress.

## Recommended Proxy Helper

Install SLOProxy on the machine that can reach your local model providers:

```bash
npm install -g sloproxy
sloproxy init
sloproxy up
```

For macOS background service management:

```bash
sloproxy install
sloproxy start
sloproxy stop
sloproxy status
sloproxy rotate-key
sloproxy uninstall
```

SLOProxy stores its persistent config in `~/.config/sloproxy/.env` and can expose local providers locally only, through Tailscale Funnel, or through Cloudflare Tunnel.

## Development

Developer docs, release notes, packaging details, and reference material live in [docs/developing.md](docs/developing.md).
