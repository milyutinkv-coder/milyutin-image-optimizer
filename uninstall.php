<?php
/**
 * Вызывается при УДАЛЕНИИ плагина (не при деактивации).
 * Очищаем настройки и лог. Сконвертированные .webp/.avif не трогаем.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Опции.
delete_option( 'mio_settings' );
delete_option( 'mio_log' );
delete_option( 'mio_stats' );

// Мультисайт.
if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $sites as $blog_id ) {
        switch_to_blog( $blog_id );
        delete_option( 'mio_settings' );
        delete_option( 'mio_log' );
        delete_option( 'mio_stats' );
        restore_current_blog();
    }
}