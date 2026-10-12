# EmDash Exporter

    Contributors: ascorbic
    Tags: export, migration, cms, emdash, astro
    Requires at least: 5.6
    Tested up to: 7.1
    Requires PHP: 7.4
    Stable tag: 1.2.0
    License: GPL3
    License URI: https://opensource.org/license/gpl-3.0

Migrate your WordPress site to EmDash CMS with a guided wizard: posts, pages, custom post types, ACF fields, media, menus, translations, and SEO.

## Description

EmDash Exporter adds a guided migration wizard (Tools → EmDash Migration) and REST API endpoints that allow EmDash CMS to import your content directly—no file downloads required.

**Features:**

- **Guided wizard** – Site checks with actionable fixes, one-click migration key, honest overview of what will (and won't) be migrated
- **Migration key** – One copy-paste key contains everything EmDash needs to connect; no manual Application Password setup
- **Full content** – Posts, pages, and custom post types including drafts, with the original permalink for redirect maps
- **Media with metadata** – Images, videos, and files with alt text, captions, and dimensions
- **Custom fields** – Full ACF support plus any custom meta fields
- **SEO data** – Yoast SEO and Rank Math meta automatically included
- **Taxonomies** – Categories, tags, and custom taxonomies with hierarchy
- **Navigation menus** – Menus with hierarchy and theme locations
- **Comments** – Approved and pending comments with authors, dates, and threading
- **Translations** – WPML and Polylang locales and translation groups
- **Authors** – User data for proper attribution
- **No EmDash site yet?** – Deploy a starter site to Cloudflare straight from the wizard

## Authentication

Uses WordPress Application Passwords (built into WordPress 5.6+). The wizard creates one for you and packages it into a single migration key. You can revoke it at any time, and deactivating or deleting the plugin revokes it for every user.

## Installation

1. Download `emdash-exporter.zip` from the [latest release](https://github.com/emdash-cms/wp-emdash/releases) and install it via Plugins → Add New → Upload Plugin (or upload the `plugins/emdash-exporter` folder to `/wp-content/plugins/`)
2. Activate the plugin — you'll land in the migration wizard automatically
3. Follow the three steps: site check, generate your migration key, paste it in EmDash

## API Endpoints

All endpoints are under `/wp-json/emdash/v1/`

### Public (no auth required):

- `GET /probe` – Site info and capabilities
- `GET /header-check` – Reports whether the Authorization header reaches WordPress (used by the wizard)

### Authenticated (requires the `export` capability — Administrators and Editors):

- `GET /analyze` – Full site analysis for import planning
- `GET /content?post_type=post` – Get posts (paginated)
- `GET /media` – Get media items (paginated)
- `GET /media/{id}` – Get a single media item
- `GET /taxonomies` – Get all taxonomies and terms
- `GET /options` – Get site options
- `GET /menus` – Get navigation menus with items and hierarchy
- `GET /comments` – Get comments (paginated, approved and pending)

## Frequently Asked Questions

### Do I need to install anything on my EmDash site?

> No. This plugin runs on your WordPress site and exposes an API that EmDash connects to.

### Is my content secure?

> Yes. All export endpoints (except /probe and /header-check) require authentication. Use Application Passwords and HTTPS.

### What about my media files?

> Media URLs are included in the export. EmDash downloads them directly from your WordPress site during import.

### Does this work with custom post types?

> Yes! All public post types are automatically included, along with their custom fields.

### What about ACF fields?

> Full ACF support. Field groups are analyzed, and field values are exported with proper type information.

## Changelog

### 1.2.0

- New `/comments` endpoint: exports approved and pending comments (authors, dates, threading) for import into EmDash's native comment system
- Site identity in `/options`: custom logo and site icon with resolved URLs, so EmDash can take over title, tagline, logo, and favicon
- Wizard overview now lists comments and site identity as migrated
- `/taxonomies` now includes each taxonomy's singular label and registered post types, so EmDash can auto-create custom post type taxonomies scoped to the right collections

### 1.1.0

- Guided migration wizard under Tools → EmDash Migration with preflight site checks (permalinks, REST loopback, Authorization header, Application Passwords, reachability)
- One-click migration key: creates a revocable Application Password and packages URL + credentials into a single copy-paste string
- Deploy-to-Cloudflare shortcut for users without an EmDash site
- New `/menus` endpoint: navigation menus with hierarchy and theme locations
- Per-post `permalink` for SEO-preserving redirect maps
- WPML/Polylang support: per-post `locale` and `translation_group`, site-level `i18n` info in probe/analyze
- Security: export endpoints now require the `export` capability (previously `edit_posts` was enough), removed server file paths from media responses, hardened unserialization

### 1.0.0

- Initial release
- REST API endpoints for content, media, taxonomies, options
- ACF field support
- Yoast SEO and Rank Math integration
- Application Passwords authentication

## Upgrade Notice

### 1.0.0

> Initial release
