<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Простой логгер: хранит записи и статистику в опциях WP.
 */
class MIO_Logger {

    public const OPTION_LOG   = 'mio_log';
    public const OPTION_STATS = 'mio_stats';
    public const MAX_ENTRIES  = 200;

    public function log( string $message ): void {
        $log   = get_option( self::OPTION_LOG, [] );
        $log   = is_array( $log ) ? $log : [];
        $log[] = [
            'time'    => current_time( 'mysql' ),
            'message' => $message,
        ];

        if ( count( $log ) > self::MAX_ENTRIES ) {
            $log = array_slice( $log, -self::MAX_ENTRIES );
        }

        update_option( self::OPTION_LOG, $log, false );
    }

    public function add_stats( array $stats ): void {
        $total = get_option( self::OPTION_STATS, [ 'converted' => 0, 'failed' => 0, 'saved' => 0 ] );
        if ( ! is_array( $total ) ) {
            $total = [ 'converted' => 0, 'failed' => 0, 'saved' => 0 ];
        }

        $total['converted'] = (int) ( $total['converted'] ?? 0 ) + (int) ( $stats['converted'] ?? 0 );
        $total['failed']    = (int) ( $total['failed']    ?? 0 ) + (int) ( $stats['failed']    ?? 0 );
        $total['saved']     = (int) ( $total['saved']     ?? 0 ) + (int) ( $stats['saved']     ?? 0 );

        update_option( self::OPTION_STATS, $total, false );
    }

    public function get_stats(): array {
        $s = get_option( self::OPTION_STATS, [] );
        return wp_parse_args( is_array( $s ) ? $s : [], [ 'converted' => 0, 'failed' => 0, 'saved' => 0 ] );
    }

    public function get_log(): array {
        $l = get_option( self::OPTION_LOG, [] );
        return is_array( $l ) ? $l : [];
    }

    public function clear(): void {
        delete_option( self::OPTION_LOG );
        delete_option( self::OPTION_STATS );
    }
}