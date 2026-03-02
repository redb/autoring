# Morgao AutoRing

Minimal WordPress plugin to connect a family of sites through a shared footer signature and a clean public directory.

## Positioning

- simple, durable, low-friction webring for independent sites
- footer-ready snippet for WordPress themes and page builders
- admin-managed ring sites
- GitHub Release updates
- cached availability status on the directory page

## What it does

- generates a minimal signature with `Previous`, `Index`, `Next`, and `Random`
- manages sites directly from WordPress admin
- supports a master site plus client sites that join the same ring
- renders a public directory page with green/red live status dots
- supports an optional `Give` CTA on the directory page
- falls back safely if remote data or external checks fail

## Structure

- `morgao-webring-signature.php`: plugin bootstrap
- `config/default-sites.php`: local seed/fallback sites
- `config/defaults.php`: config defaults
- `includes/`: validation, registry, updater, rendering, admin
- `assets/`: frontend and admin assets
- `.github/workflows/release-plugin.yml`: GitHub Release ZIP packaging

## Install

1. Upload the folder `morgao-webring-signature` to `wp-content/plugins/`
2. Activate `Morgao AutoRing`
3. Open `Settings > Morgao AutoRing`
4. On first launch, choose:
   - `Make This Site The Master`
   - or `Connect To An Existing Master`
5. If this site is the master, manage the shared ring sites here
6. If this site is a client, enter the master site URL and let the plugin register automatically
7. Copy the generated snippet into your theme, footer builder, or custom footer credits area

Divi note:

- works in `Divi > Theme Options > Edit Footer Credits`
- both the copied HTML snippet and `[morgao_webring_signature]` are supported

## GitHub Install And Updates

Recommended flow:

1. publish this plugin in a GitHub repository
2. create a GitHub Release
3. attach the generated asset `morgao-webring-signature.zip`
4. install that ZIP in WordPress the first time
5. enable `GitHub updates` in `Settings > Morgao AutoRing`
6. keep or set the repository to `redb/autoring`

On new installations, GitHub updates are enabled by default.

Automatic GitHub updates require:

- a published GitHub Release
- a ZIP asset named `morgao-webring-signature.zip`
- the plugin folder slug to stay `morgao-webring-signature`
- a release tag/version greater than the installed version

If the live registry is temporarily broken, the plugin falls back to a safe admin mode so the settings page stays accessible.
If runtime rendering also fails, the signature and directory switch to degraded output instead of crashing the site.

For private repositories, define `MWS_GITHUB_TOKEN` in `wp-config.php`.

## Master And Client Mode

The plugin is designed to connect multiple WordPress sites into one shared ring.

- the first site can act as the master by default
- the master exposes the shared site registry and accepts registrations
- other sites install the plugin and choose `Connect To An Existing Master`
- client sites read the master registry with cache and keep a local fallback

Master site shares:

- its base URL
- optionally a shared secret for registration protection

Client site needs:

- the master site base URL
- the shared secret only if the master requires one

Shared branding from the master site can also propagate to subscribed clients, including:

- the signature label shown in the shared footer signature
- the accent color
- the optional `Give` button

## Monetization

The public directory can display a `Give` button.

Configure it in the plugin admin:

- enable `Give button`
- set a valid URL
- customize the label if needed

The plugin list in WordPress also exposes:

- `Settings`
- `Open Admin`
- `Give` when enabled

## Availability Status

The public directory shows:

- green dot: site responded
- red dot: site did not respond

Checks are designed to stay lightweight:

- short timeout
- 2 attempts maximum
- cached per site
- manual refresh from admin
- periodic refresh with WP-Cron

## Credits

Morgao AutoRing is inspired by and adapted from the original `webring` project by Devine Lu Linvega.

- original project: `XXIIVV/webring`
- original license: MIT
- this plugin adds a WordPress-specific admin, updater flow, caching, status checks, and monetization support

See [NOTICE.md](NOTICE.md) and [LICENSE](LICENSE).

## Release

See [RELEASING.md](RELEASING.md) for the first public release checklist.
