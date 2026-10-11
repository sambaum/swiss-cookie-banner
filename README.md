# Swiss Cookie Banner

A small WordPress/WooCommerce cookie banner for shops that serve **Switzerland only**. No dependencies, no external requests, no tracking of its own.

## Behaviour

- Bottom bar (non-blocking) with **Accept** and **Settings**. The page stays usable.
- Settings dialog: *Necessary* (always on) and *Analytics* (off by default), with **Reject all / Accept all / Save**.
- Google tag loaders (`gtag/js`, `gtm.js`, `analytics.js`) are rewritten server-side to `type="text/plain"` so the browser never fetches them before consent, then activated in the browser after the visitor accepts. This works regardless of which plugin places the tag (Site Kit, WooCommerce Google Analytics Integration, a theme, ...).
- Google Consent Mode: sends `gtag('consent','update',...)` and, if the *WP Consent API* plugin is installed, reports through that too.
- Consent is stored in the first-party cookie `scb_consent` for 12 months. No server-side consent log.
- Banner and dialog texts are German; the admin UI is English.

## Legal note (read this)

There is deliberately **no "Reject all" button on the banner itself**. Rejecting needs one extra click (Settings). That is intended for Swiss law (Art. 45c FMG / nDSG: inform and allow refusal, no prior opt-in required) and is **not compliant with the GDPR/ePrivacy rules** for visitors in the EU/EEA. Do not use it for shops that target EU customers. This is not legal advice.

You still need a privacy policy that names Google Analytics, its purpose and the US transfer.

## Install

1. Run `build.sh` (Linux/macOS) or `build.bat` (Windows). This creates `swiss-cookie-banner.zip`.
2. WordPress admin: Plugins -> Add New -> Upload Plugin.
3. Settings -> Cookie Banner: banner text, privacy policy URL (optional).
4. Give visitors a way to reopen the settings: shortcode `[cookie_einstellungen]` or any link to `#cookie-einstellungen` (e.g. in the footer menu).

## After every update

The tag rewriting is part of the page HTML, so **purge page caches** (caching plugin and CDN) after installing or updating the plugin.

## Test

Open the site in a normal browser window (private windows and tracker blockers hide problems) with DevTools -> Network:

1. No choice made: no requests to `googletagmanager.com` / `google-analytics.com`, no `_ga` cookies.
2. After Accept: requests and `_ga` cookies appear.
3. Reject via Settings: `_ga` cookies are gone after the page reloads.

Use a URL with a query string (`/?x=1`) to bypass page caches while testing. Turn off "I don't care about cookies"-style extensions.

## Limits

- Scripts injected later by JavaScript (e.g. extra tags loaded by a GTM container) are not blocked.
- Only Google Analytics/Tag Manager is handled. There is no Marketing category.

## License

GPL-2.0-or-later.
