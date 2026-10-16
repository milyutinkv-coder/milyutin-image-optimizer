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

### From GitHub

1. Download the latest release ZIP.
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**.
4. Activate the plugin.
5. Go to **Milyutin Image Opt.** in the admin menu to configure.

### Manual

1. Clone the repository:
   ```bash
   git clone https://github.com/milyutinkv-coder/milyutin-image-optimizer.git
