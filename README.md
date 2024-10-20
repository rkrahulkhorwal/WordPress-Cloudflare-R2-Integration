# WordPress Cloudflare R2 Integration

This WordPress plugin integrates Cloudflare R2 storage with WordPress, allowing you to upload and serve media files directly from Cloudflare R2.

## Features

- Automatically upload WordPress media library files to Cloudflare R2
- Direct file uploads to R2 from the WordPress admin area
- Rewrite attachment URLs to serve files from R2
- Modern, user-friendly interface with real-time upload progress
- Secure authentication using SigV4 for R2 compatibility

## Installation

1. Download the plugin files and upload them to your `/wp-content/plugins/wordpress-cloudflare-r2-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the 'Cloudflare R2' menu item in your admin area to configure the plugin settings.

## Configuration

1. Go to the 'Cloudflare R2' settings page in your WordPress admin area.
2. Enter your Cloudflare R2 credentials:
   - Account ID
   - Access Key ID
   - Secret Access Key
   - Bucket Name
3. Save your settings.

## Usage

Once configured, the plugin will automatically handle uploads to Cloudflare R2. You can also use the direct upload form on the settings page to test uploads to R2.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- cURL PHP extension

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0 License - see the [LICENSE](LICENSE) file for details.
