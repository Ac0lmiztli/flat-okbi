=== Flat Okbi ===
Contributors: Okbi
Tags: real estate, property, apartments, shortcode, custom post types, mortgage, flat, realty
Requires at least: 5.2
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 2.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: okbi-apartments
Domain Path: /languages

A powerful plugin to manage a catalog of apartments and residential complexes with an interactive viewer.

== Description ==

"Flat Okbi" is a comprehensive solution for real estate developers and agencies to easily manage their property catalog directly within WordPress.

**Key features include:**

* **Custom Post Types:** Separate, organized sections for Residential Complexes, Sections, Apartments, Commercial properties, Parking spaces, and Storerooms.
* **Interactive Viewer:** A frontend, full-screen catalog with advanced filters, allowing clients to easily search for properties by rooms, area, floor, and more.
* **Visual Grid Editor:** An intuitive drag-and-drop interface for arranging properties on a grid for each section, just as they are located in the building.
* **Floor Plan Editor:** Draw interactive polygons over floor plan images to create clickable areas for each apartment or office.
* **CSV Import/Export:** Quickly populate your database with hundreds of properties using a simple CSV file. Export existing properties and leads.
* **Lead Management:** A built-in system to collect and manage leads from booking forms, with email and Telegram notifications.
* **Price Management:** A dedicated page to bulk-edit prices for properties across different residential complexes.
* **Flexible and Customizable:** Change the accent color and logo to match your brand identity.

This plugin requires the free [MetaBox](https://wordpress.org/plugins/meta-box/) plugin to function correctly.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/flat-okbi` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  **Important:** Install and activate the required free plugin [MetaBox](https://wordpress.org/plugins/meta-box/).
4.  Navigate to **Flat Okbi -> Settings** in your WordPress admin menu to configure the plugin (add your logo, set up notifications, etc.).
5.  To display the catalog, place the shortcode `[okbi_viewer]` on any page.
6.  Create a button or a link on that same page and add the following attributes to it: `class="fok-open-viewer" data-rc-id="X"`, where `X` is the ID of the residential complex you want to display. You can find the ID in the URL when you edit a residential complex.

== Frequently Asked Questions ==

= Is the MetaBox plugin required? =

Yes. "Flat Okbi" uses the MetaBox plugin to create and manage all the custom fields for properties, sections, and complexes. You need to install and activate the free version from the WordPress repository.

= How do I find the ID for a residential complex? =

Go to **Flat Okbi -> Residential Complexes** in your WordPress admin. Click "Edit" on the complex you need. In your browser's address bar, you will see a URL like `.../post.php?post=123&action=edit`. The number after `post=` is the ID. In this example, the ID is `123`.

= Can I customize the look of the property viewer? =

Yes, you can change the main accent color and upload your own logo from the **Flat Okbi -> Settings** page. For more advanced CSS customizations, you can use your theme's custom CSS feature or an external stylesheet.

== Screenshots ==

1.  The interactive frontend property viewer with filters.
2.  The admin price management table.
3.  The visual drag-and-drop grid editor.
4.  The floor plan editor with polygon drawing.
5.  The CSV import interface.
6.  The main plugin settings page.

== Changelog ==

= 2.2.5 =
* Initial stable release.
* Added full localization support.
* Performance optimizations for AJAX requests and admin pages.
* Finalized all core features.