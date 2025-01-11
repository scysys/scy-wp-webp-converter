# WP WebP Converter

A WordPress plugin that automatically converts images to WebP format and serves them dynamically, ensuring better performance and faster page load times.

## Features

- Automatically converts uploaded images (`.jpg`, `.jpeg`, `.png`) to WebP format.
- Dynamically serves WebP images on the website.
- Displays the WebP conversion status in the media library:
  - **Exists** – WebP version already generated.
  - **Missing (Generate now)** – WebP version missing, with an option to generate it manually.
  - **Already WebP** – If the uploaded image is already in WebP format.
  - **Not supported** – For unsupported file types (e.g., `.svg`, `.mp4`).
- Scheduled conversion via WordPress cron job (default: hourly).
- Manual regeneration of all images via the admin interface.
- Settings page for configuring conversion method, quality, and cron frequency.

## Installation

1. Download the plugin and upload it to your WordPress installation via the **Plugins** page.
2. Activate the plugin through the **Plugins** page in WordPress.
3. Configure the plugin settings via the **Settings > WebP Converter** page.

## Usage

- Once the plugin is activated, it will automatically convert newly uploaded images to WebP format.
- The plugin will check for new images and convert them on an hourly basis using a WordPress cron job.
- You can manually regenerate all existing images via the **Regenerate All Images** button on the plugin’s settings page.

## Settings

The plugin provides the following settings:

- **Conversion Method** – Choose between `GD`, `Imagick`, or `Gmagick` for image conversion.
- **WebP Quality** – Set the quality level for the generated WebP images (default: 82).
- **Files Per Cron Run** – Limit the number of files processed per cron run.
- **Cron Frequency** – Set the frequency of the cron job (options: every 5 minutes, every 15 minutes, hourly, daily, etc.).

## Contribution

Feel free to open issues or submit pull requests for improvements and bug fixes.

## License

This plugin is licensed under the GPLv3 or later license.
