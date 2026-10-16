<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Страница настроек в админке.
 */
class MIO_Settings {

    public const OPTION    = 'mio_settings';
    public const PAGE_SLUG = 'mio-settings';

    public function init(): void {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public static function defaults(): array {
        return [
            'auto_convert'  => 1,
            'formats'       => [ 'webp', 'avif' ],
            'quality_webp'  => 82,
            'quality_avif'  => 50,
        ];
    }

    public function add_menu(): void {
        add_menu_page(
            'Milyutin Image Optimizer',
            'Milyutin Image Opt.',
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ],
            'dashicons-format-image',
            81
        );
    }

    public function register_settings(): void {
        register_setting( 'mio_settings_group', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
            'default'           => self::defaults(),
        ] );
    }

    public function sanitize( $input ): array {
        $input = is_array( $input ) ? $input : [];
        $out   = self::defaults();

        $out['auto_convert'] = ! empty( $input['auto_convert'] ) ? 1 : 0;

        $fmt_in = isset( $input['formats'] ) && is_array( $input['formats'] ) ? $input['formats'] : [];
        $fmt_in = array_map( 'sanitize_text_field', $fmt_in );
        $out['formats'] = array_values( array_intersect( [ 'webp', 'avif' ], $fmt_in ) );

        $out['quality_webp'] = min( 100, max( 1, (int) ( $input['quality_webp'] ?? 82 ) ) );
        $out['quality_avif'] = min( 100, max( 1, (int) ( $input['quality_avif'] ?? 50 ) ) );

        return $out;
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'toplevel_page_' . self::PAGE_SLUG ) {
            return;
        }

        wp_enqueue_style(
            'mio-admin',
            MIO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MIO_VERSION
        );

        wp_enqueue_script(
            'mio-admin',
            MIO_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            MIO_VERSION,
            true
        );

        wp_localize_script( 'mio-admin', 'mioData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mio_ajax' ),
            'i18n'    => [
                'starting'  => 'Инициализация...',
                'done'      => 'Готово!',
                'netError'  => 'Ошибка сети',
            ],
        ] );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = MIO_Plugin::get_settings();
        $stats    = MIO_Plugin::logger()->get_stats();
        $log      = MIO_Plugin::logger()->get_log();
        $support  = MIO_Plugin::converter()->get_server_support();

        $formats = (array) $settings['formats'];
        ?>
        <div class="wrap mio-wrap">
            <h1>Milyutin Image Optimizer</h1>

            <?php if ( ! $support['webp'] && ! $support['avif'] ) : ?>
                <div class="notice notice-error">
                    <p><strong>Внимание:</strong> сервер не поддерживает ни WebP, ни AVIF. Обратитесь к хостингу.</p>
                </div>
            <?php else : ?>
                <?php if ( in_array( 'webp', $formats, true ) && ! $support['webp'] ) : ?>
                    <div class="notice notice-warning">
                        <p>WebP выбран, но сервер его не поддерживает. Формат будет пропущен.</p>
                    </div>
                <?php endif; ?>
                <?php if ( in_array( 'avif', $formats, true ) && ! $support['avif'] ) : ?>
                    <div class="notice notice-warning">
                        <p>AVIF выбран, но сервер его не поддерживает. Формат будет пропущен.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="mio-grid">

                <div class="mio-card">
                    <h2>Настройки</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'mio_settings_group' ); ?>

                        <p>
                            <label class="mio-switch">
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_convert]" value="1" <?php checked( ! empty( $settings['auto_convert'] ) ); ?>>
                                <span>Автоматически конвертировать при загрузке</span>
                            </label>
                        </p>

                        <fieldset class="mio-fieldset">
                            <legend>Форматы</legend>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[formats][]" value="webp"
                                    <?php checked( in_array( 'webp', $formats, true ) ); ?>>
                                WebP <span class="mio-muted">(поддержка ~95% браузеров)</span>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[formats][]" value="avif"
                                    <?php checked( in_array( 'avif', $formats, true ) ); ?>>
                                AVIF <span class="mio-muted">(лучшее сжатие, поддержка новее)</span>
                            </label>
                        </fieldset>

                        <p>
                            <label for="mio-q-webp">Качество WebP: <output id="mio-q-webp-out"><?php echo (int) $settings['quality_webp']; ?></output></label><br>
                            <input type="range" id="mio-q-webp" name="<?php echo esc_attr( self::OPTION ); ?>[quality_webp]" min="1" max="100"
                                value="<?php echo (int) $settings['quality_webp']; ?>">
                        </p>

                        <p>
                            <label for="mio-q-avif">Качество AVIF: <output id="mio-q-avif-out"><?php echo (int) $settings['quality_avif']; ?></output></label><br>
                            <input type="range" id="mio-q-avif" name="<?php echo esc_attr( self::OPTION ); ?>[quality_avif]" min="1" max="100"
                                value="<?php echo (int) $settings['quality_avif']; ?>">
                        </p>

                        <?php submit_button( 'Сохранить настройки' ); ?>
                    </form>
                </div>

                <div class="mio-card">
                    <h2>Массовая конвертация</h2>
                    <p class="mio-muted">Пройдётся по всем JPG/PNG в медиатеке и создаст .webp / .avif рядом с оригиналами.</p>

                    <p>
                        <button type="button" class="button button-primary" id="mio-start-bulk">Запустить массовую конвертацию</button>
                        <button type="button" class="button" id="mio-clear-log">Очистить лог</button>
                    </p>

                    <div class="mio-progress">
                        <div class="mio-progress-bar" id="mio-progress-bar"></div>
                    </div>
                    <p id="mio-status" class="mio-status">Ожидание…</p>

                    <h3>Статистика</h3>
                    <ul class="mio-stats">
                        <li>Сконвертировано: <strong><?php echo (int) $stats['converted']; ?></strong></li>
                        <li>Ошибок: <strong><?php echo (int) $stats['failed']; ?></strong></li>
                        <li>Сэкономлено: <strong><?php echo esc_html( size_format( (int) $stats['saved'] ) ); ?></strong></li>
                    </ul>

                    <h3>Лог</h3>
                    <div id="mio-log" class="mio-log">
                        <?php if ( empty( $log ) ) : ?>
                            <em class="mio-muted">Записей пока нет.</em>
                        <?php else : ?>
                            <?php foreach ( array_reverse( array_slice( $log, -50 ) ) as $entry ) : ?>
                                <div><?php echo esc_html( $entry['time'] . ' — ' . $entry['message'] ); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mio-card mio-card--brand">
                    <h2>Автор плагина</h2>
                    <p><strong>Константин Милютин</strong></p>
                    <p>
                        🌐 <a href="https://milyutin-codes.ru" target="_blank" rel="noopener">milyutin-codes.ru</a><br>
                        💬 <a href="https://vk.com/milyutin_codes" target="_blank" rel="noopener">vk.com/milyutin_codes</a>
                    </p>
                    <p class="mio-muted">Версия плагина: <?php echo esc_html( MIO_VERSION ); ?></p>
                    <p class="mio-muted">
                        Сервер:
                        Imagick <?php echo $support['imagick'] ? '✅' : '❌'; ?> ·
                        WebP <?php echo $support['webp'] ? '✅' : '❌'; ?> ·
                        AVIF <?php echo $support['avif'] ? '✅' : '❌'; ?>
                    </p>
                </div>

            </div>
        </div>
        <?php
    }
}