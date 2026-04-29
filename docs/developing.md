# Developing

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

Cut a release from the repo:

```bash
npm run release
```

This command:

- verifies version alignment between `package.json` and `mw-local-ai-connector.php`
- requires a clean `main` branch worktree
- if the target tag already exists, offers to bump to the next patch semver and continue
- runs PHP lint plus Node verification
- builds the plugin ZIP and npm tarball
- creates and pushes the git tag
- waits for the GitHub Release workflow to publish release assets
- reports whether Packagist notification is configured in GitHub secrets

Because npm publish is manual in this repo due OTP requirements, the default release command stops short of publishing to npm and prints the exact next command.

If you want the command to publish to npm too, opt in explicitly:

```bash
npm run release -- --publish-npm --otp <code>
```

Build a release ZIP locally:

```bash
npm run build:plugin
```

That creates:

```text
dist/mw-local-ai-connector-plugin.zip
```

Build the npm package tarball locally:

```bash
npm run build:npm
```

That creates:

```text
dist/mattwiebe-mw-local-ai-connector-<version>.tgz
```

## GitHub Automation

This repository ships with GitHub Actions workflows for:

- CI on pushes and pull requests
  - PHP lint + real WordPress `WP_UnitTestCase` tests
  - Node syntax check + Node tests
- Release packaging on tags matching `v*`
  - runs the full test suite,
  - builds the WordPress plugin ZIP and npm tarball,
  - publishes the npm package with trusted publishing,
  - uploads both artifacts to the GitHub release.

To cut a release:

1. Make sure `package.json` and the plugin header version are aligned.
2. Create and push a semver tag like `v0.2.0`.
3. GitHub Actions will build:
   - `dist/mw-local-ai-connector-plugin.zip`
   - `dist/mattwiebe-mw-local-ai-connector-<version>.tgz`
4. The workflow will attach both files to the GitHub release automatically.

For Packagist, connect the GitHub repository in Packagist so updates are detected through the Packagist GitHub integration. Reference: [Packagist update hooks](https://packagist.org/about#how-to-update-packages).

For npm publishing, configure npm trusted publishing for `@mattwiebe/mw-local-ai-connector` on npmjs.com and authorize the GitHub Actions workflow file `release.yml`. The workflow requests `id-token: write` and runs `npm publish` on tag builds, so no long-lived npm token is needed once trusted publishing is set up.

## Composer / Packagist

This repository is ready to be consumed as a Composer package from Packagist or directly from GitHub.

The package name is:

```text
mattwiebe/mw-local-ai-connector
```

It uses Composer package type `wordpress-plugin`, so Composer-based WordPress projects can install it into `wp-content/plugins` when the root project uses `composer/installers` with the usual installer paths.

Example root project setup:

```json
{
  "require": {
    "composer/installers": "^2.3",
    "mattwiebe/mw-local-ai-connector": "^0.2"
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
      "url": "https://github.com/mattwiebe/mw-local-ai-connector"
    }
  ]
}
```

## WP Packages Compatibility

[WP Packages](https://wp-packages.org/docs) mirrors active plugins and themes from the WordPress.org directory under names like `wp-plugin/plugin-name`. Because MW Local AI Connector is hosted on GitHub and is not currently in the WordPress.org plugin directory, it will not appear there yet.

The repo is still compatible with the same Composer install flow described in the WP Packages docs:

- it uses Composer package type `wordpress-plugin`
- it sets a stable installer name of `mw-local-ai-connector`
- it works with the same root-level `composer/installers` configuration used for WP Packages packages

So the practical path today is `mattwiebe/mw-local-ai-connector` via Packagist or a VCS repository. If the plugin is later published to WordPress.org, then a WP Packages entry would become possible under a `wp-plugin/...` package name.

## npm Package

The npm package name is:

```text
@mattwiebe/mw-local-ai-connector
```

Preferred usage:

```bash
npm install -g @mattwiebe/mw-local-ai-connector
laiproxy init
laiproxy up
```

The CLI also exposes macOS service management:

```bash
laiproxy install
laiproxy start
laiproxy stop
laiproxy status
laiproxy uninstall
```

It also works without installation:

```bash
npx @mattwiebe/mw-local-ai-connector up
npx @mattwiebe/mw-local-ai-connector init
```

The package also exposes `mw-local-ai-connector` as a longer alias, but `laiproxy` is the intended global command.

The npm CLI stores its persistent config in:

```text
~/.config/mw-local-ai-connector/.env
```

That keeps `npx` usage stateful across runs instead of writing config into a temporary install directory.

## Publishing To npm

Based on npm’s current docs for scoped public packages, the publish flow is:

1. Create or sign in to your npm account with `npm login`.
2. Make sure the package name is available:
   `npm view @mattwiebe/mw-local-ai-connector`
3. Inspect exactly what would be published:
   `npm pack --dry-run`
4. Configure npm trusted publishing for the package on npmjs.com using this repository and the `release.yml` workflow.
5. Push a release tag so GitHub Actions runs `npm publish`.

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
