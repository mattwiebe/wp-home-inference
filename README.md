# MW Local AI Connector

MW Local AI Connector is a WordPress AI provider plugin plus a small local proxy for running inference against models on your own machine.

It is built for WordPress 7.0+ and the new Connectors primitives. The WordPress plugin runs on the remote site. The local proxy runs on a machine you control, talks to an OpenAI-compatible local backend such as Ollama or LM Studio, and exposes that backend to WordPress through an authenticated Tailscale Funnel.

This is an independent project by Matt Wiebe. It is not affiliated with, endorsed by, or sponsored by Actual Computer, Tailscale, Ollama, LM Studio, or any other third-party service it can be configured to talk to.

## What It Does

- Registers a `mw-local-ai` AI provider with the WordPress AI Client.
- Adds a Settings screen for entering the proxy URL, API key, and selected model.
- Loads model choices from the real proxied `/v1/models` endpoint.
- Restricts the provider to the selected model so WordPress auto-discovery consistently uses it.
- Ships a local `node` proxy that can:
  - detect local backends,
  - persist configuration in `local/.env`,
  - expose the proxy through Tailscale Funnel,
  - protect requests with a bearer token.

## Repository Layout

- [`mw-local-ai-connector.php`](mw-local-ai-connector.php): WordPress plugin bootstrap, settings UI, connector registration.
- [`src/`](src): provider, model, and metadata directory classes.
- [`local/server.mjs`](local/server.mjs): local proxy and Tailscale Funnel entrypoint.

## Requirements

- WordPress `7.0+`
- PHP `7.4+`
- Node.js `18+` with native `fetch`
- One local OpenAI-compatible backend, such as:
  - Ollama
  - LM Studio
  - another localhost backend by port number
- Tailscale, if you want public exposure through Funnel

## WordPress Plugin Setup

1. Copy this plugin into `wp-content/plugins/mw-local-ai-connector`.
2. Activate the plugin in WordPress.
3. Open `Settings > Connectors` or the dedicated Local AI settings page.
4. Enter:
   - the proxy endpoint URL,
   - the shared API key,
   - the model to expose to WordPress.

The Connectors screen links back to this setup page.

## Local Proxy Setup

Preferred install path:

```bash
npm install -g @mattwiebe/mw-local-ai-connector
```

Then initialize and run the proxy with:

```bash
laiproxy init
laiproxy up
```

macOS background service management:

```bash
laiproxy install
laiproxy start
laiproxy stop
laiproxy status
laiproxy rotate-key
laiproxy uninstall
```

You can also run it without a global install:

```bash
npx @mattwiebe/mw-local-ai-connector init
npx @mattwiebe/mw-local-ai-connector up
```

For local development from this repo, you can still use:

```bash
npm run init
npm run up
npm run service:install
npm run start
npm run stop
npm run status
npm run rotate-key
npm run service:uninstall
```

That guided setup will:

- detect known backends or ask for the localhost backend port,
- generate or accept an API key,
- ask whether to enable Tailscale Funnel,
- ask which public Funnel port to use, defaulting to `8443`,
- save everything into `local/.env`.

When Funnel starts, the proxy also checks public DNS for the generated `ts.net` hostname. If public DNS has not propagated yet, WordPress may temporarily report `Could not resolve host`; Tailscale says Funnel DNS propagation can take up to 10 minutes.

After that, normal startup is non-interactive:

```bash
laiproxy up
```

To reconfigure later:

```bash
laiproxy init
```

On macOS, `laiproxy install` or `npm run service:install` writes a LaunchAgent at `~/Library/LaunchAgents/com.mattwiebe.mw-local-ai-connector.plist` so the proxy can keep running in the background across logins.

To rotate the shared API key in the persisted `.env` file and print the new key:

```bash
laiproxy rotate-key
```

This also works as:

```bash
laiproxy rotate-key
npm run rotate-key
```

On macOS, if the LaunchAgent is currently running, `rotate-key` will restart it automatically so the background process picks up the new key immediately.

Useful overrides:

```bash
laiproxy up --port 13531
laiproxy up --funnel-port 10000
laiproxy up --backend http://localhost:11434
laiproxy up --api-key your-secret
laiproxy up --no-tunnel
```

## Development

Developer docs, release notes, packaging details, and reference material now live in [docs/developing.md](docs/developing.md).
