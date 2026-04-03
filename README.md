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

Preferred install path:

```bash
npm install -g @mattwiebe/wp-home-inference
```

Then initialize and run the proxy with:

```bash
wphi init
wphi up
```

macOS background service management:

```bash
wphi install
wphi start
wphi stop
wphi status
wphi uninstall
```

You can also run it without a global install:

```bash
npx @mattwiebe/wp-home-inference init
npx @mattwiebe/wp-home-inference up
```

For local development from this repo, you can still use:

```bash
npm run init
npm run up
npm run service:install
npm run start
npm run stop
npm run status
npm run service:uninstall
```

That guided setup will:

- detect or prompt for the backend URL,
- generate or accept an API key,
- ask whether to enable Tailscale Funnel,
- ask which public Funnel port to use, defaulting to `8443`,
- save everything into `local/.env`.

After that, normal startup is non-interactive:

```bash
wphi up
```

To reconfigure later:

```bash
wphi init
```

On macOS, `wphi install` or `npm run service:install` writes a LaunchAgent at `~/Library/LaunchAgents/com.mattwiebe.wp-home-inference.plist` so the proxy can keep running in the background across logins.

Useful overrides:

```bash
wphi up --port 13531
wphi up --funnel-port 10000
wphi up --backend http://localhost:11434
wphi up --api-key your-secret
wphi up --no-tunnel
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

Install the WordPress PHPUnit test suite:

```bash
composer test:install-wp
```

The PHP tests use the real WordPress test framework and `WP_UnitTestCase`, so they need a local MySQL database plus the WordPress test library installed by that script.

Run the local checks:

```bash
composer lint
composer test
npm run lint
npm test
```

Build a release ZIP locally:

```bash
npm run build:plugin
```

That creates:

```text
dist/wp-home-inference-plugin.zip
```

Build the npm package tarball locally:

```bash
npm run build:npm
```

That creates:

```text
dist/mattwiebe-wp-home-inference-<version>.tgz
```

## GitHub Automation

This repository ships with GitHub Actions workflows for:

- CI on pushes and pull requests
  - PHP lint + real WordPress `WP_UnitTestCase` tests
  - Node syntax check + Node tests
- Release packaging on tags matching `v*`
  - runs the full test suite,
  - builds the WordPress plugin ZIP and npm tarball,
- uploads both artifacts to the GitHub release.

## Composer / Packagist

This repository is ready to be consumed as a Composer package from Packagist or directly from GitHub.

The package name is:

```text
mattwiebe/wp-home-inference
```

It uses Composer package type `wordpress-plugin`, so Composer-based WordPress projects can install it into `wp-content/plugins` when the root project uses `composer/installers` with the usual installer paths.

Example root project setup:

```json
{
  "require": {
    "composer/installers": "^2.3",
    "mattwiebe/wp-home-inference": "^0.1"
  },
  "extra": {
    "installer-paths": {
      "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

If you want to consume it directly from GitHub before Packagist metadata refreshes or before tagging, add a VCS repository in the root project:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/mattwiebe/wp-home-inference"
    }
  ]
}
```

## WP Packages Compatibility

[WP Packages](https://wp-packages.org/docs) mirrors active plugins and themes from the WordPress.org directory under names like `wp-plugin/plugin-name`. Because Home Inference is hosted on GitHub and is not currently in the WordPress.org plugin directory, it will not appear there yet.

The repo is still compatible with the same Composer install flow described in the WP Packages docs:

- it uses Composer package type `wordpress-plugin`
- it sets a stable installer name of `wp-home-inference`
- it works with the same root-level `composer/installers` configuration used for WP Packages packages

So the practical path today is `mattwiebe/wp-home-inference` via Packagist or a VCS repository. If the plugin is later published to WordPress.org, then a WP Packages entry would become possible under a `wp-plugin/...` package name.

## npm Package

The npm package name is:

```text
@mattwiebe/wp-home-inference
```

Preferred usage:

```bash
npm install -g @mattwiebe/wp-home-inference
wphi init
wphi up
```

The CLI also exposes macOS service management:

```bash
wphi install
wphi start
wphi stop
wphi status
wphi uninstall
```

It also works without installation:

```bash
npx @mattwiebe/wp-home-inference up
npx @mattwiebe/wp-home-inference init
```

The package also exposes `wp-home-inference` as a longer alias, but `wphi` is the intended global command.

The npm CLI stores its persistent config in:

```text
~/.config/wp-home-inference/.env
```

That keeps `npx` usage stateful across runs instead of writing config into a temporary install directory.

## Publishing To npm

Based on npm’s current docs for scoped public packages, the publish flow is:

1. Create or sign in to your npm account with `npm login`.
2. Make sure the package name is available:
   `npm view @mattwiebe/wp-home-inference`
3. Inspect exactly what would be published:
   `npm pack --dry-run`
4. Publish the next version:
   `npm publish`

This repo already sets `publishConfig.access` to `public`, which is the required setting for a public scoped package according to npm’s docs.

## Current Status

This project is early, but the core loop is in place:

- provider registration works,
- settings save with nonce/capability protection via the WordPress Settings API,
- API key handling preserves secrets as opaque values,
- model choices come from the live proxy,
- local proxy configuration persists in `local/.env`.

## License

GPL-2.0-or-later
