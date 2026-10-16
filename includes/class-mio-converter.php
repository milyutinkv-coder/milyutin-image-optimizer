<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Конвертация изображений: Imagick (приоритет) → GD через WP_Image_Editor.
 */
class MIO_Converter {

    /**
     * Конвертировать оригинал + все размеры вложения.
     *
     * @return array{converted:int, skipped:int, failed:int, saved:int, errors:string[]}
     */
    public function convert_attachment( int $attachment_id, array $formats, array $quality ): array {
        $stats = [ 'converted' => 0, 'skipped' => 0, 'failed' => 0, 'saved' => 0, 'errors' => [] ];

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            $stats['failed']++;
            $stats['errors'][] = "Файл не найден для ID {$attachment_id}";
            return $stats;
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
            return $stats; // не JPG/PNG — пропускаем
        }

        $files = [ $file ];
        $meta  = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            $dir = dirname( $file );
            foreach ( $meta['sizes'] as $size ) {
                if ( ! empty( $size['file'] ) ) {
                    $files[] = $dir . DIRECTORY_SEPARATOR . $size['file'];
                }
            }
        }

        foreach ( $files as $path ) {
            foreach ( $formats as $fmt ) {
                $q   = (int) ( $quality[ $fmt ] ?? 82 );
                $res = $this->convert_file( $path, $fmt, $q );

                switch ( $res['status'] ) {
                    case 'converted':
                        $stats['converted']++;
                        $stats['saved'] += (int) $res['saved'];
                        break;
                    case 'skipped':
                        $stats['skipped']++;
                        break;
                    default:
                        $stats['failed']++;
                        if ( ! empty( $res['error'] ) ) {
                            $stats['errors'][] = $res['error'];
                        }
                }
            }
        }

        return $stats;
    }

    /**
     * Конвертировать один файл.
     *
     * @return array{status:string, saved?:int, error?:string}
     */
    public function convert_file( string $src, string $format, int $quality ): array {
        if ( ! file_exists( $src ) ) {
            return [ 'status' => 'failed', 'error' => "Нет файла: {$src}" ];
        }

        $new = preg_replace( '/\.(jpe?g|png)$/i', '.' . $format, $src );
        if ( ! $new || $new === $src ) {
            return [ 'status' => 'failed', 'error' => "Не удалось определить имя для {$src}" ];
        }

        // Уже сконвертировано и свежее оригинала — пропускаем.
        if ( file_exists( $new ) && filemtime( $new ) >= filemtime( $src ) ) {
            return [ 'status' => 'skipped' ];
        }

        $orig_size = (int) @filesize( $src );

        $ok = $format === 'webp'
            ? $this->make_webp( $src, $new, $quality )
            : $this->make_avif( $src, $new, $quality );

        if ( ! $ok || ! file_exists( $new ) ) {
            return [ 'status' => 'failed', 'error' => "Ошибка конвертации {$src} → {$format}" ];
        }

        $new_size = (int) @filesize( $new );
        return [ 'status' => 'converted', 'saved' => max( 0, $orig_size - $new_size ) ];
    }

    /* ------------------ Реализация ------------------ */

    private function make_webp( string $src, string $dst, int $q ): bool {
        if ( class_exists( 'Imagick' ) ) {
            try {
                $im = new \Imagick( $src );
                $im->setImageFormat( 'webp' );
                $im->setImageCompressionQuality( $q );
                $im->setOption( 'webp:method', '6' );
                $im->stripImage();
                $ok = $im->writeImage( $dst );
                $im->clear();
                if ( $ok && file_exists( $dst ) ) {
                    return true;
                }
            } catch ( \Throwable $e ) {
                // упадём на GD
            }
        }

        // Fallback: WP_Image_Editor (GD). WebP поддержан с WP 5.8.
        if ( function_exists( 'imagewebp' ) ) {
            $editor = wp_get_image_editor( $src );
            if ( ! is_wp_error( $editor ) ) {
                $editor->set_quality( $q );
                if ( method_exists( $editor, 'set_mime_type' ) ) {
                    $editor->set_mime_type( 'image/webp' );
                }
                $saved = $editor->save( $dst );
                if ( ! is_wp_error( $saved ) && file_exists( $dst ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function make_avif( string $src, string $dst, int $q ): bool {
        if ( class_exists( 'Imagick' ) ) {
            try {
                $im = new \Imagick( $src );
                $im->setImageFormat( 'avif' );
                $im->setImageCompressionQuality( $q );
                $im->stripImage();
                $ok = $im->writeImage( $dst );
                $im->clear();
                if ( $ok && file_exists( $dst ) ) {
                    return true;
                }
            } catch ( \Throwable $e ) {
                return false;
            }
        }

        // Резерв через GD (PHP 8.1+).
        if ( defined( 'IMG_AVIF' ) && function_exists( 'imageavif' ) ) {
            $info = @getimagesize( $src );
            if ( $info ) {
                $im = false;
                switch ( $info[2] ) {
                    case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg( $src ); break;
                    case IMAGETYPE_PNG:  $im = @imagecreatefrompng( $src );  break;
                }
                if ( $im ) {
                    $ok = @imageavif( $im, $dst, $q );
                    imagedestroy( $im );
                    return (bool) $ok;
                }
            }
        }

        return false;
    }

    /* ------------------ Поддержка сервера ------------------ */

    public function supports_format( string $format ): bool {
        if ( class_exists( 'Imagick' ) ) {
            try {
                $formats = array_map( 'strtoupper', \Imagick::queryFormats() );
                if ( in_array( strtoupper( $format ), $formats, true ) ) {
                    return true;
                }
            } catch ( \Throwable $e ) {
                // игнорируем
            }
        }

        if ( $format === 'webp' ) {
            return function_exists( 'imagewebp' );
        }
        if ( $format === 'avif' ) {
            return defined( 'IMG_AVIF' ) && function_exists( 'imageavif' ) && ( imagetypes() & IMG_AVIF );
        }
        return false;
    }

    public function get_server_support(): array {
        return [
            'imagick' => class_exists( 'Imagick' ),
            'webp'    => $this->supports_format( 'webp' ),
            'avif'    => $this->supports_format( 'avif' ),
        ];
    }
}