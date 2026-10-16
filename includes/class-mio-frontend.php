<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Заменяет <img> на <picture> с источниками AVIF → WebP → оригинал.
 */
class MIO_Frontend {

    public function init(): void {
        // Контент постов, виджеты и т.п.
        add_filter( 'wp_content_img_tag',      [ $this, 'filter_img_tag' ], 10, 3 );
        // Изображения из шаблона (wp_get_attachment_image)
        add_filter( 'wp_get_attachment_image', [ $this, 'filter_attachment_image' ], 10, 5 );
    }

    public function filter_img_tag( $html, $context, $attachment_id ): string {
        return $this->wrap_picture( (string) $html );
    }

    public function filter_attachment_image( $html, $attachment_id, $attr, $sizes, $icon ): string {
        if ( $icon || is_admin() ) {
            return (string) $html;
        }
        return $this->wrap_picture( (string) $html );
    }

    /* ---------------- Внутренняя логика ---------------- */

    private function wrap_picture( string $html ): string {
        if ( $html === '' || stripos( $html, '<picture' ) !== false ) {
            return $html;
        }

        if ( ! preg_match( '/<img\s+([^>]+)>/i', $html, $m ) ) {
            return $html;
        }
        $attrs = $m[1];

        if ( ! preg_match( '/src=(["\'])([^"\']+)\1/i', $attrs, $sm ) ) {
            return $html;
        }
        $src = $sm[2];

        // Только JPG/PNG.
        if ( ! preg_match( '/\.(jpe?g|png)(\?|$)/i', $src ) ) {
            return $html;
        }

        $settings = MIO_Plugin::get_settings();
        $formats  = (array) $settings['formats'];
        if ( empty( $formats ) ) {
            return $html;
        }

        // srcset, если есть.
        $srcset = '';
        if ( preg_match( '/srcset=(["\'])([^"\']+)\1/i', $attrs, $sm2 ) ) {
            $srcset = $sm2[2];
        }

        // AVIF первым (предпочтительнее), затем WebP.
        $ordered = [];
        if ( in_array( 'avif', $formats, true ) ) $ordered[] = 'avif';
        if ( in_array( 'webp', $formats, true ) ) $ordered[] = 'webp';

        $sources_html = '';
        foreach ( $ordered as $fmt ) {
            $new_src = $this->url_to_converted( $src, $fmt );
            if ( ! $new_src ) {
                continue;
            }

            $source_srcset = '';
            if ( $srcset ) {
                $source_srcset = $this->convert_srcset( $srcset, $fmt );
                if ( $source_srcset === '' ) {
                    continue; // не все размеры сконвертированы — пропускаем формат
                }
            } else {
                $source_srcset = $new_src;
            }

            $sources_html .= sprintf(
                '<source type="%s" srcset="%s">',
                esc_attr( 'image/' . $fmt ),
                esc_attr( $source_srcset )
            );
        }

        if ( $sources_html === '' ) {
            return $html;
        }

        return '<picture>' . $sources_html . $html . '</picture>';
    }

    /**
     * Вернуть URL сконвертированного файла, если он существует.
     */
    private function url_to_converted( string $url, string $fmt ): string {
        // Убираем query string.
        $clean = preg_replace( '/\?.*$/', '', $url );
        $clean = preg_replace( '/\.(jpe?g|png)$/i', '.' . $fmt, $clean );

        if ( $clean === $url ) {
            return '';
        }

        $upload = wp_upload_dir();
        if ( strpos( $url, $upload['baseurl'] ) !== 0 ) {
            return '';
        }

        $rel  = substr( $url, strlen( $upload['baseurl'] ) );
        $rel  = preg_replace( '/\?.*$/', '', $rel );
        $path = $upload['basedir'] . $rel;
        $new  = preg_replace( '/\.(jpe?g|png)$/i', '.' . $fmt, $path );

        if ( ! $new || $new === $path || ! file_exists( $new ) ) {
            return '';
        }

        $new_rel = substr( $new, strlen( $upload['basedir'] ) );
        return $upload['baseurl'] . str_replace( DIRECTORY_SEPARATOR, '/', $new_rel );
    }

    /**
     * Преобразовать строку srcset для выбранного формата.
     * Возвращает '' если хотя бы один URL не удалось сконвертировать.
     */
    private function convert_srcset( string $srcset, string $fmt ): string {
        $parts  = preg_split( '/\s*,\s*/', trim( $srcset ) );
        $result = [];

        foreach ( $parts as $part ) {
            $part = trim( $part );
            if ( $part === '' ) {
                continue;
            }

            $pieces = preg_split( '/\s+/', $part );
            $url    = $pieces[0] ?? '';
            if ( $url === '' ) {
                return '';
            }

            $new_url = $this->url_to_converted( $url, $fmt );
            if ( $new_url === '' ) {
                return '';
            }
            $pieces[0] = $new_url;
            $result[]  = implode( ' ', $pieces );
        }

        return implode( ', ', $result );
    }
}