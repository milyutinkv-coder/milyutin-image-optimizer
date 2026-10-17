# Milyutin Image Optimizer

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

> WordPress plugin that converts JPG/PNG images to **WebP** and **AVIF**, delivers them via `<picture>` tag, and includes bulk conversion for the entire media library.

## ✨ Features

- 🔄 **Automatic conversion** on image upload (original + all intermediate sizes)
- 🖼️ **Bulk conversion** of the entire media library via AJAX batches (with progress bar)
- 📦 **WebP and AVIF** support (Imagick first, GD as fallback)
- 🎨 **Adjustable quality** for each format (sliders in admin)
- 🏷️ **`<picture>` tag rendering** with AVIF → WebP → original fallback
- 📊 **Log and statistics** — see how much space you saved
- 🔒 **Secure AJAX** (nonce + capability checks)
- 🌐 **No external services** — everything runs on your server

## 📋 Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- **Imagick** PHP extension (recommended) or **GD** with WebP/AVIF support
- For AVIF: PHP 8.1+ with GD compiled with AVIF support, or Imagick with AVIF

## 🚀 Installation

1. Upload the `milyutin-image-optimizer` folder to `wp-content/plugins/` (or upload a ZIP file via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin.
3. Go to **Milyutin Image Opt.** in the admin menu to configure formats, quality, and run bulk conversion.

## 📂 File Structure

```text
milyutin-image-optimizer/
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── includes/
│   ├── class-mio-bulk.php
│   ├── class-mio-converter.php
│   ├── class-mio-frontend.php
│   ├── class-mio-logger.php
│   └── class-mio-settings.php
├── milyutin-image-optimizer.php
└── uninstall.php
```
❓ FAQ
Question	Answer
Does it modify my original images?	No. The plugin only creates new .webp / .avif files next to the originals. Originals stay untouched.
What if my server doesn't support WebP or AVIF?	The plugin detects server support and shows a warning in the admin panel. Unsupported formats are simply skipped.
Can I use it with a CDN?	Yes. Since files are created on disk next to the originals, any CDN will pick them up automatically.
How do I revert changes?	Deactivate the plugin. Your original JPG/PNG files remain intact.
