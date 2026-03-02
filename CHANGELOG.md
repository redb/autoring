# Changelog

## 0.1.4

- fix backward compatibility with older cached hub payloads after the shared-branding format change
- prevent invalid remote payloads from crashing shortcode rendering

## 0.1.3

- fix Divi footer credits so `[morgao_webring_signature]` is rendered instead of shown as plain text

## 0.1.2

- add master-managed shared branding propagated to subscribed client sites
- allow the master site to control the shared signature label
- propagate master accent color and Give CTA settings to connected clients
- keep `Morgao AutoRing` branding on the index page only, not in footer signatures
- add explicit Divi footer credits shortcode compatibility

## 0.1.1

- rename repository target to `redb/autoring`
- add master/client onboarding and automatic client registration
- fix the `Index` page fatal error caused by inline CSS formatting
- prepare GitHub-based updates for already installed `0.1.0` sites

## 0.1.0

- initial public release of Morgao AutoRing
- minimal footer signature for Divi and shortcode support
- admin-managed site registry
- public directory with cached status dots
- optional Give CTA
- upgrade-safe plugin settings
- GitHub Release update support
