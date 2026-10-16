<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Массовая конвертация через AJAX батчами.
 */
class MIO_Bulk {

    public function __construct(
        private MIO_Converter $converter,
        private MIO_Logger    $logger
    ) {}

    public function init(): void {
        add_action( 'wp_ajax_mio_bulk_init', [ $this, 'ajax_init' ] );
        add_action( 'wp_ajax_mio_bulk_step', [ $this, 'ajax_step' ] );
        add_action( 'wp_ajax_mio_clear_log', [ $this, 'ajax_clear_log' ] );
    }

    public function ajax_init(): void {
        $this->guard();

        $ids = $this->get_all_image_ids();
        wp_send_json_success( [ 'total' => count( $ids ) ] );
    }

    public function ajax_step(): void {
        $this->guard();

        $offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
        $limit  = min( 20, max( 1, (int) ( $_POST['limit'] ?? 5 ) ) );

        $ids      = $this->get_all_image_ids();
        $slice    = array_slice( $ids, $offset, $limit );
        $settings = MIO_Plugin::get_settings();

        $result = [
            'processed'   => 0,
            'converted'   => 0,
            'skipped'     => 0,
            'failed'      => 0,
            'saved'       => 0,
            'next_offset' => $offset + count( $slice ),
            'done'        => false,
            'log'         => [],
        ];

        $quality = [
            'webp' => (int) $settings['quality_webp'],
            'avif' => (int) $settings['quality_avif'],
        ];

        foreach ( $slice as $id ) {
            $stats = $this->converter->convert_attachment( (int) $id, (array) $settings['formats'], $quality );

            $result['processed']++;
            $result['converted'] += (int) $stats['converted'];
            $result['skipped']   += (int) $stats['skipped'];
            $result['failed']    += (int) $stats['failed'];
            $result['saved']     += (int) $stats['saved'];

            if ( $stats['converted'] > 0 ) {
                $result['log'][] = sprintf(
                    'ID %d: +%d файлов, сэкономлено %s',
                    $id,
                    $stats['converted'],
                    size_format( (int) $stats['saved'] )
                );
            }
            if ( ! empty( $stats['errors'] ) ) {
                foreach ( array_slice( $stats['errors'], 0, 2 ) as $err ) {
                    $result['log'][] = 'Ошибка (ID ' . $id . '): ' . $err;
                    $this->logger->log( 'Ошибка (ID ' . $id . '): ' . $err );
                }
            }
        }

        if ( $result['next_offset'] >= count( $ids ) ) {
            $result['done'] = true;
        }

        $this->logger->add_stats( $result );

        if ( $result['converted'] > 0 || $result['failed'] > 0 ) {
            $this->logger->log( sprintf(
                'Пакет обработан: +%d, ошибок %d, сэкономлено %s',
                $result['converted'],
                $result['failed'],
                size_format( (int) $result['saved'] )
            ) );
        }

        wp_send_json_success( $result );
    }

    public function ajax_clear_log(): void {
        $this->guard();
        $this->logger->clear();
        wp_send_json_success();
    }

    private function guard(): void {
        check_ajax_referer( 'mio_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Недостаточно прав', 403 );
        }
    }

    /**
     * Получить ID всех JPG/PNG-вложений.
     */
    private function get_all_image_ids(): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s
               AND post_mime_type IN (%s, %s)
               AND post_status = %s
             ORDER BY ID ASC",
            'attachment',
            'image/jpeg',
            'image/png',
            'inherit'
        );

        $ids = $wpdb->get_col( $sql );
        return array_map( 'intval', $ids );
    }
}