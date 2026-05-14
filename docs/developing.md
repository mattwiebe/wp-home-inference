# Developing

## Development

Install project tooling:

```bash
composer install
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
```

Build a release ZIP locally:

```bash
bash scripts/build-plugin-zip.sh
```

That creates:

```text
dist/mwlai-connector-plugin.zip
```

## GitHub Automation

This repository ships with GitHub Actions workflows for:

- CI on pushes and pull requests:
  - PHP lint
  - real WordPress `WP_UnitTestCase` tests
- Release packaging on tags matching `v*`:
  - runs PHP verification,
  - builds the WordPress plugin ZIP,
  - deploys the tag to WordPress.org SVN with `10up/action-wordpress-plugin-deploy`,
  - uploads the locally built ZIP and the WordPress.org-generated ZIP to the GitHub release.

WordPress.org deploys use `.distignore` to exclude development-only files from SVN `trunk`. Plugin-directory assets live in `.wordpress-org`; the deploy action copies that directory to the top-level SVN `assets` directory, so those files are intentionally excluded from the installable plugin package.

To cut a release:

1. Update the plugin header version and `Stable tag` in `readme.txt`.
2. Create and push a semver tag like `v0.2.0`.
3. GitHub Actions will build `dist/mwlai-connector-plugin.zip`.
4. The workflow will deploy the tag to WordPress.org using the `SVN_USERNAME` and `SVN_PASSWORD` repository secrets.
5. The workflow will attach ZIP assets to the GitHub release automatically.

For Packagist, connect the GitHub repository in Packagist so updates are detected through the Packagist GitHub integration. Reference: [Packagist update hooks](https://packagist.org/about#how-to-update-packages).

## Composer / Packagist

This repository is ready to be consumed as a Composer package from Packagist or directly from GitHub.

The package name is:

```text
mattwiebe/ai-connector-for-local-ai
```

It uses Composer package type `wordpress-plugin`, so Composer-based WordPress projects can install it into `wp-content/plugins` when the root project uses `composer/installers` with the usual installer paths.

Example root project setup:

```json
{
  "require": {
    "composer/installers": "^2.3",
    "mattwiebe/ai-connector-for-local-ai": "^0.3"
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
      "url": "https://github.com/mattwiebe/ai-connector-for-local-ai"
    }
  ]
}
```

## WP Packages Compatibility

[WP Packages](https://wp-packages.org/docs) mirrors active plugins and themes from the WordPress.org directory under names like `wp-plugin/plugin-name`. Because MW Local AI Connector is hosted on GitHub and is not currently in the WordPress.org plugin directory, it will not appear there yet.

The repo is still compatible with the same Composer install flow described in the WP Packages docs:

- it uses Composer package type `wordpress-plugin`
- it sets a stable installer name of `mwlai-connector`
- it works with the same root-level `composer/installers` configuration used for WP Packages packages

So the practical path today is `mattwiebe/ai-connector-for-local-ai` via Packagist or a VCS repository. If the plugin is later published to WordPress.org, then a WP Packages entry would become possible under a `wp-plugin/...` package name.

## Recommended Proxy Helper

The local proxy now lives in the separate SLOProxy repository:

```text
https://github.com/mattwiebe/sloproxy
```

The npm package name is:

```text
sloproxy
```

Preferred usage:

```bash
npm install -g sloproxy
sloproxy init
sloproxy up
```

The CLI also exposes macOS service management:

```bash
sloproxy install
sloproxy start
sloproxy stop
sloproxy status
sloproxy uninstall
```

It also works without installation:

```bash
npx sloproxy up
npx sloproxy init
```

SLOProxy stores persistent config in:

```text
~/.config/sloproxy/.env
```

The local proxy can front multiple localhost OpenAI-compatible providers at once:

```bash
sloproxy up --provider ollama:11434 --provider lmstudio:1234 --tunnel local
```

Provider model IDs are exposed with the provider slug as a prefix, such as `ollama/llama3.2`. The proxy strips the prefix before forwarding requests to the matching local port. Public exposure is optional; use `--tunnel local`, `--tunnel tailscale`, or `--tunnel cloudflare`.

## Current Status

This project is early, but the core plugin loop is in place:

- provider registration works,
- settings save with nonce/capability protection via the WordPress Settings API,
- API key handling preserves secrets as opaque values,
- model choices come from the live proxy and include provider-prefixed IDs.

## License

GPL-2.0-or-later
