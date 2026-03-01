# Releasing Morgao AutoRing

## First Release Checklist

1. Confirm the GitHub repository is `https://github.com/redb/Webring` in `morgao-webring-signature.php`.
2. Confirm the plugin version in `morgao-webring-signature.php`.
3. Update [CHANGELOG.md](CHANGELOG.md).
4. Verify the default site seed in `config/default-sites.php`.
5. Create a Git tag matching the plugin version, for example `v0.1.0`.
6. Publish a GitHub Release from that tag.
7. Let the GitHub Action attach `morgao-webring-signature.zip` to the release.
8. Install that ZIP in WordPress or update from the previous release.

## Notes

- Keep the folder slug `morgao-webring-signature`.
- Do not rename the main plugin file.
- For automatic GitHub updates, the release asset name must stay `morgao-webring-signature.zip`.
- If the repository is private, set `MWS_GITHUB_TOKEN` in `wp-config.php`.
