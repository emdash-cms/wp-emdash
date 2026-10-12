=== EmDash Exporter ===
Contributors: emdashcms
Tags: export, migration, cms, emdash, astro
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Migrate your WordPress site to EmDash CMS with a guided wizard: posts, pages, custom post types, ACF fields, media, menus, translations, and SEO.

== Description ==

EmDash Exporter adds a guided migration wizard (Tools → EmDash Migration) and REST API endpoints that let EmDash CMS import your content directly, with no file downloads.

**Features:**

* **Guided wizard:** site checks with actionable fixes, a one-click migration key, and an honest overview of what will (and won't) be migrated
* **Migration key:** one copy-paste key contains everything EmDash needs to connect, with no manual Application Password setup
* **Full content:** posts, pages, and custom post types including drafts, with the original permalink for redirect maps
* **Media with metadata:** images, videos, and files with alt text, captions, and dimensions
* **Custom fields:** full ACF support plus any custom meta fields
* **SEO data:** Yoast SEO and Rank Math meta included automatically
* **Taxonomies:** categories, tags, and custom taxonomies with hierarchy
* **Navigation menus:** menus with hierarchy and theme locations
* **Comments:** approved and pending comments with authors, dates, and threading
* **Translations:** WPML and Polylang locales and translation groups
* **Authors:** user data for proper attribution
* **No EmDash site yet?** Deploy a starter site to Cloudflare straight from the wizard

= Authentication =

The plugin uses WordPress Application Passwords (built into WordPress 5.6+). The wizard creates one for you and packages it into a single migration key. You can revoke it at any time, and deactivating or deleting the plugin revokes it for every user.

= API endpoints =

All endpoints are under `/wp-json/emdash/v1/`.

Public (no authentication):

* `GET /probe`: site info and capabilities
* `GET /header-check`: reports whether the Authorization header reaches WordPress (used by the wizard)

Authenticated (requires the `export` capability, which Administrators and Editors have):

* `GET /analyze`: full site analysis for import planning
* `GET /content?post_type=post`: posts (paginated)
* `GET /media`: media items (paginated)
* `GET /media/{id}`: a single media item
* `GET /taxonomies`: all taxonomies and terms
* `GET /options`: site options
* `GET /menus`: navigation menus with items and hierarchy
* `GET /comments`: comments (paginated, approved and pending)

== Installation ==

1. Install the plugin from Plugins → Add New, or download `emdash-exporter.zip` from the [latest release](https://github.com/emdash-cms/wp-emdash/releases) and upload it via Plugins → Add New → Upload Plugin.
2. Activate the plugin. You'll land in the migration wizard automatically.
3. Follow the three steps: site check, generate your migration key, paste it in EmDash.

== Frequently Asked Questions ==

= Do I need to install anything on my EmDash site? =

No. This plugin runs on your WordPress site and exposes an API that EmDash connects to.

= Does the plugin send my data anywhere? =

No. The plugin makes no outgoing requests apart from checking its own REST API during the site check. Your EmDash site pulls the content using the migration key, and only while the key is valid.

= Is my content secure? =

Yes. All export endpoints except `/probe` and `/header-check` require authentication with the `export` capability. Use HTTPS, keep the migration key private, and revoke it once the migration is done.

= What about my media files? =

Media URLs are included in the export. EmDash downloads the files directly from your WordPress site during import.

= Does this work with custom post types? =

Yes. All public post types are included automatically, along with their custom fields.

= What about ACF fields? =

Field groups are analyzed, and field values are exported with type information. Relationship, post object, user, and taxonomy fields are exported as IDs.

== Screenshots ==

1. The site check confirms that EmDash can connect and explains how to fix anything that would block the migration.
2. One click creates a revocable migration key that contains everything EmDash needs to connect.
3. Connect from an existing EmDash site or deploy a new one, and see what will and won't be migrated.

== Changelog ==

= 1.2.0 =

* New `/comments` endpoint: exports approved and pending comments (authors, dates, threading) for import into EmDash's native comment system
* Site identity in `/options`: custom logo and site icon with resolved URLs, so EmDash can take over title, tagline, logo, and favicon
* Wizard overview now lists comments and site identity as migrated
* `/taxonomies` now includes each taxonomy's singular label and registered post types, so EmDash can auto-create custom post type taxonomies scoped to the right collections
* Security: ACF relationship, post object, user and taxonomy fields are exported as IDs instead of whole posts, users and terms, which could include related posts' `post_password` and users' email addresses and `user_pass` hash; EmDash sites migrated with an earlier version may still hold these values in their imported fields
* Security: deactivating or deleting the plugin revokes the migration keys of all users
* The media endpoints no longer offer the inline base64 option (`include_data`); EmDash downloads media from the file URLs

= 1.1.0 =

* Guided migration wizard under Tools → EmDash Migration with preflight site checks (permalinks, REST loopback, Authorization header, Application Passwords, reachability)
* One-click migration key: creates a revocable Application Password and packages URL + credentials into a single copy-paste string
* Deploy-to-Cloudflare shortcut for users without an EmDash site
* New `/menus` endpoint: navigation menus with hierarchy and theme locations
* Per-post `permalink` for SEO-preserving redirect maps
* WPML/Polylang support: per-post `locale` and `translation_group`, site-level `i18n` info in probe/analyze
* Security: export endpoints now require the `export` capability (previously `edit_posts` was enough), removed server file paths from media responses, hardened unserialization

= 1.0.0 =

* Initial release
* REST API endpoints for content, media, taxonomies, options
* ACF field support
* Yoast SEO and Rank Math integration
* Application Passwords authentication

== Upgrade Notice ==

= 1.2.0 =

Security update: ACF relation fields no longer export password data, and deactivating the plugin revokes all migration keys.
