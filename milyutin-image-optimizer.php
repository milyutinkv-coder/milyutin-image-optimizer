<?php
/**
 * Plugin Name: Milyutin Image Optimizer
 * Plugin URI:  https://milyutin-codes.ru
 * Description: Конвертация JPG/PNG в WebP и AVIF для ускорения сайта. Массовая и автоматическая конвертация, отдача через <picture>.
 * Version:     1.0.0
 * Author:      Константин Милютин
 * Author URI:  https://milyutin-codes.ru
 * License:     GPL-2.0-or-later
 * Text Domain: milyutin-image-optimizer
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Прямой доступ запрещён.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MIO_VERSION',    '1.0.0' );
define( 'MIO_PLUGIN_FILE', __FILE__ );
define( 'MIO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MIO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once MIO_PLUGIN_DIR . 'includes/class-mio-logger.php';
require_once MIO_PLUGIN_DIR . 'includes/class-mio-converter.php';
require_once MIO_PLUGIN_DIR . 'includes/class-mio-settings.php';
require_once MIO_PLUGIN_DIR . 'includes/class-mio-bulk.php';
require_once MIO_PLUGIN_DIR . 'includes/class-mio-frontend.php';

/**
 * Главный класс-бутстрап плагина.
 */
final class MIO_Plugin {

    private static ?MIO_Plugin $instance = null;

    private MIO_Logger   $logger;
    private MIO_Converter $converter;
    private MIO_Settings $settings;
    private MIO_Bulk     $bulk;
    private MIO_Frontend $frontend;

    public static function instance(): MIO_Plugin {
        return self::$instance ??= new self();
    }

    private function __construct() {
        $this->logger    = new MIO_Logger();
        $this->converter = new MIO_Converter();
        $this->settings  = new MIO_Settings();
        $this->bulk      = new MIO_Bulk( $this->converter, $this->logger );
        $this->frontend  = new MIO_Frontend();

        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init(): void {
        // Админка
        if ( is_admin() ) {
            $this->settings->init();
            $this->bulk->init();
        }

        // Фронтенд
        $this->frontend->init();

        // Хук автоконвертации после загрузки
        add_action( 'wp_generate_attachment_metadata', [ $this, 'on_upload' ], 20, 2 );
    }

    /**
     * Автоматическая конвертация при загрузке нового изображения.
     */
    public function on_upload( array $metadata, int $attachment_id ): array {
        $settings = self::get_settings();

        if ( empty( $settings['auto_convert'] ) || empty( $settings['formats'] ) ) {
            return $metadata;
        }

        $this->converter->convert_attachment(
            $attachment_id,
            $settings['formats'],
            [
                'webp' => (int) $settings['quality_webp'],
                'avif' => (int) $settings['quality_avif'],
            ]
        );

        return $metadata;
    }

    /* --------- Геттеры --------- */

    public static function get_settings(): array {
        $defaults = MIO_Settings::defaults();
        $saved    = get_option( MIO_Settings::OPTION, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return wp_parse_args( $saved, $defaults );
    }

    public static function logger(): MIO_Logger       { return self::instance()->logger; }
    public static function converter(): MIO_Converter { return self::instance()->converter; }
    public static function bulk(): MIO_Bulk           { return self::instance()->bulk; }
    public static function settings(): MIO_Settings   { return self::instance()->settings; }
}

MIO_Plugin::instance();