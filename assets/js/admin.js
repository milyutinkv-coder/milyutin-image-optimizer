/* global jQuery, mioData */
( function ( $ ) {
    'use strict';

    $( function () {

        // Живое обновление значений ползунков качества.
        $( '#mio-q-webp' ).on( 'input', function () {
            $( '#mio-q-webp-out' ).text( this.value );
        } );
        $( '#mio-q-avif' ).on( 'input', function () {
            $( '#mio-q-avif-out' ).text( this.value );
        } );

        // ---- Массовая конвертация ----
        let running = false;

        function formatBytes( b ) {
            b = Number( b ) || 0;
            if ( b < 1024 ) return b + ' B';
            if ( b < 1048576 ) return ( b / 1024 ).toFixed( 1 ) + ' KB';
            return ( b / 1048576 ).toFixed( 2 ) + ' MB';
        }

        function appendLog( lines ) {
            const $log = $( '#mio-log' );
            if ( ! $log.length || ! lines || ! lines.length ) return;

            // Очистим "записей пока нет"
            $log.find( 'em.mio-muted' ).remove();

            lines.forEach( function ( line ) {
                $log.prepend( '<div>' + line + '</div>' );
            } );
        }

        $( '#mio-start-bulk' ).on( 'click', function ( e ) {
            e.preventDefault();
            if ( running ) return;
            running = true;

            const $btn      = $( this );
            const $progress = $( '#mio-progress-bar' );
            const $status   = $( '#mio-status' );

            $btn.prop( 'disabled', true );
            $status.text( mioData.i18n.starting );
            $progress.css( 'width', '0%' );

            $.post( mioData.ajaxUrl, {
                action: 'mio_bulk_init',
                nonce:  mioData.nonce
            } ).done( function ( res ) {
                if ( ! res || ! res.success ) {
                    finish( 'Ошибка: ' + ( ( res && res.data ) || 'unknown' ) );
                    return;
                }

                const total     = res.data.total || 0;
                const batchSize = 5;
                let offset      = 0;
                let processed   = 0;
                let converted   = 0;
                let failed      = 0;
                let saved       = 0;

                if ( total === 0 ) {
                    finish( 'Нет изображений для обработки.' );
                    return;
                }

                function step() {
                    $.post( mioData.ajaxUrl, {
                        action: 'mio_bulk_step',
                        nonce:  mioData.nonce,
                        offset: offset,
                        limit:  batchSize
                    } ).done( function ( r ) {
                        if ( ! r || ! r.success ) {
                            finish( 'Ошибка: ' + ( ( r && r.data ) || 'unknown' ) );
                            return;
                        }

                        const d = r.data;
                        processed += d.processed;
                        converted += d.converted;
                        failed    += d.failed;
                        saved     += d.saved;
                        offset     = d.next_offset;

                        appendLog( d.log );

                        const pct = Math.min( 100, Math.round( offset / total * 100 ) );
                        $progress.css( 'width', pct + '%' );
                        $status.text(
                            'Обработано ' + processed + ' из ' + total +
                            ' · сконвертировано ' + converted +
                            ' · ошибок ' + failed +
                            ' · сэкономлено ' + formatBytes( saved )
                        );

                        if ( d.done ) {
                            finish( mioData.i18n.done + ' Сэкономлено: ' + formatBytes( saved ) );
                        } else {
                            step();
                        }
                    } ).fail( function () {
                        finish( mioData.i18n.netError );
                    } );
                }

                function finish( msg ) {
                    running = false;
                    $btn.prop( 'disabled', false );
                    $status.text( msg );
                    $progress.css( 'width', '100%' );
                    // Обновим страницу через пару секунд, чтобы подтянуть статистику.
                    setTimeout( function () { location.reload(); }, 2000 );
                }

                step();
            } ).fail( function () {
                running = false;
                $btn.prop( 'disabled', false );
                $status.text( mioData.i18n.netError );
            } );
        } );

        // ---- Очистка лога ----
        $( '#mio-clear-log' ).on( 'click', function ( e ) {
            e.preventDefault();
            if ( ! window.confirm( 'Очистить лог и статистику?' ) ) return;

            const $btn = $( this );
            $btn.prop( 'disabled', true );

            $.post( mioData.ajaxUrl, {
                action: 'mio_clear_log',
                nonce:  mioData.nonce
            } ).always( function () {
                location.reload();
            } );
        } );

    } );
} )( jQuery );