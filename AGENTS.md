# AGENTS.md

Instructions for AI coding assistants working on this repo.

## What this is
`swiss-cookie-banner/` is a WordPress plugin (PHP 7.4+, vanilla JS/CSS, no dependencies). See `README.md` for behaviour.

- `swiss-cookie-banner.php`: settings page, enqueue, server-side rewrite of Google tag loaders (`scb_block_tags`), shortcode.
- `assets/banner.js`: banner and settings dialog, consent cookie, re-activation of blocked scripts, Google Consent Mode update.
- `assets/banner.css`: styles.

## Rules (do not break)
- **No "Reject all" on the first layer (the bar).** Rejecting happens via Settings. This is intentional for Swiss law. Do not add one or change it silently.
- Optional categories are **off by default**; nothing analytics-related runs before consent.
- No dependencies, no build tooling, no jQuery, no external requests, no tracking of our own.
- Keep it small. Prefer deleting code to adding options.
- Visitor-facing texts are German; the admin UI is English.
- Escape output and sanitize input (WordPress standards). Admin settings need `manage_options`.
- Comments only where a reader cannot see the reason (rationale, magic numbers). Do not restate the code.

## Every change
1. Bump the version in **both** places in `swiss-cookie-banner.php` (plugin header `Version:` and `SCB_VERSION`). It is the cache-buster for the JS/CSS.
2. Syntax check: `php -l swiss-cookie-banner/swiss-cookie-banner.php` and `node --check swiss-cookie-banner/assets/banner.js` (if available).
3. Build with `./build.sh` (Linux/macOS) or `build.bat` (Windows). Never zip with PowerShell `Compress-Archive` (breaks paths on Linux).
4. Test on a test site first, following the README "Test" section. Purge page caches and the CDN after installing.
