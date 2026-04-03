# Home Inference

Home Inference is a WordPress AI provider plugin plus a small local proxy for running inference against models on your own machine.

It is built for WordPress 7.0+ and the new Connectors primitives. The WordPress plugin runs on the remote site. The local proxy runs on a machine you control, talks to an OpenAI-compatible local backend such as Ollama or LM Studio, and exposes that backend to WordPress through an authenticated Tailscale Funnel.

## What It Does

- Registers a `home-inference` AI provider with the WordPress AI Client.
- Adds a Settings screen for entering the proxy URL, API key, and selected model.
- Loads model choices from the real proxied `/v1/models` endpoint.
- Restricts the provider to the selected model so WordPress auto-discovery consistently uses it.
- Ships a local `node` proxy that can:
  - detect local backends,
  - persist configuration in `local/.env`,
  - expose the proxy through Tailscale Funnel,
  - protect requests with a bearer token.

## Repository Layout

- [`plugin.php`](plugin.php): WordPress plugin bootstrap, settings UI, connector registration.
- [`src/`](src): provider, model, and metadata directory classes.
- [`local/server.mjs`](local/server.mjs): local proxy and Tailscale Funnel entrypoint.

## Requirements

- WordPress `7.0+`
- PHP `7.4+`
- Node.js `18+` with native `fetch`
- One local OpenAI-compatible backend, currently tested against:
  - Ollama
  - LM Studio
- Tailscale, if you want public exposure through Funnel

## WordPress Plugin Setup

1. Copy this plugin into `wp-content/plugins/wp-home-inference`.
2. Activate the plugin in WordPress.
3. Open `Settings > Home Inference`.
4. Enter:
   - the proxy endpoint URL,
   - the shared API key,
   - the model to expose to WordPress.

The Connectors screen links back to this setup page.

## Local Proxy Setup

From [`local/`](local):

```bash
node server.mjs init
```

That guided setup will:

- detect or prompt for the backend URL,
- generate or accept an API key,
- ask whether to enable Tailscale Funnel,
- ask which public Funnel port to use, defaulting to `8443`,
- save everything into `local/.env`.

After that, normal startup is non-interactive:

```bash
node server.mjs
```

To reconfigure later:

```bash
node server.mjs init
```

Useful overrides:

```bash
node server.mjs --port 13531
node server.mjs --funnel-port 10000
node server.mjs --backend http://localhost:11434
node server.mjs --api-key your-secret
node server.mjs --no-tunnel
```

## Ports

- Local proxy port defaults to `13531`.
- Tailscale Funnel public port defaults to `8443`.
- Allowed Funnel public ports are `443`, `8443`, and `10000`.

Important: the public Funnel URL usually does not use the same port as the local proxy. Tailscale listens on the selected public port and forwards traffic to the local proxy port.

## Local Smoke Test

Once the proxy is running:

```bash
curl -H "Authorization: Bearer <api-key>" http://127.0.0.1:13531/v1/models
```

## Development

Install project tooling:

```bash
composer install
npm ci
```

Run the local checks:

```bash
composer lint
composer test
npm run lint
npm test
```

Build a release ZIP locally:

```bash
npm run build
```

That creates:

```text
dist/wp-home-inference.zip
```

## GitHub Automation

This repository ships with GitHub Actions workflows for:

- CI on pushes and pull requests
  - PHP lint + PHPUnit
  - Node syntax check + Node tests
- Release packaging on tags matching `v*`
  - runs the full test suite,
  - builds `dist/wp-home-inference.zip`,
  - uploads the ZIP to the GitHub release.

## Current Status

This project is early, but the core loop is in place:

- provider registration works,
- settings save with nonce/capability protection via the WordPress Settings API,
- API key handling preserves secrets as opaque values,
- model choices come from the live proxy,
- local proxy configuration persists in `local/.env`.

## License

GPL-2.0-or-later
