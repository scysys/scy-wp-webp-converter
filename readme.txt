=== Scy: WebP Converter ===
Contributors: scysys  
Donate link: https://buy.stripe.com/4gwcOm4l86dyeuk8ww  
Tags: webp, image optimization, performance, media, images  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.0.0  
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WebP Converter automatically converts uploaded images to WebP format and serves them dynamically.

== Description ==

WebP Converter automatically converts uploaded images to WebP format and serves them dynamically, ensuring better website performance and faster page load times.

**Key Features:**

* Automatically converts uploaded `.jpg`, `.jpeg`, and `.png` images to WebP.
* Displays WebP conversion status in the media library.
* Scheduled conversion via WordPress cron job (default: hourly).
* Manual regeneration of all images via the admin interface.
* Customizable settings for conversion method, quality, and cron frequency.
* Supports `GD`, `Imagick`, and `Gmagick` for conversion.

== Installation ==

1. Download the plugin and upload it to your WordPress installation via the **Plugins** page.
2. Activate the plugin through the **Plugins** page in WordPress.
3. Configure the plugin settings via **Settings > WebP Converter**.

== Frequently Asked Questions ==

= What image formats are supported? =

The plugin supports `.jpg`, `.jpeg`, and `.png` formats for conversion to WebP.

= How often does the plugin check for new images? =

By default, the plugin uses a WordPress cron job to check for new images hourly. You can change the frequency in the plugin settings.

= Can I regenerate all images manually? =

Yes, you can regenerate all images via the **Regenerate All Images** button on the plugin’s settings page.

== Screenshots ==

1. **Settings page** – Configure conversion method, quality, and cron frequency.
2. **Media library column** – Displays the WebP conversion status of each image.
3. **Regeneration tool** – Manually regenerate all images.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
