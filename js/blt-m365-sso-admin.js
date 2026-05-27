/**
 * Admin scripts for BLT M365 WP SSO.
 *
 * Handles the "Test Authentication" button on the Azure Credentials tab.
 */
( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        var btn = document.getElementById( 'blt-m365-sso-test-btn' );

        if ( ! btn || typeof window.bltM365Sso === 'undefined' ) {
            return;
        }

        var cfg     = window.bltM365Sso;
        var spinner = document.getElementById( 'blt-m365-sso-test-spinner' );
        var result  = document.getElementById( 'blt-m365-sso-test-result' );

        function escapeHtml( value ) {
            var div = document.createElement( 'div' );
            div.textContent = ( value === undefined || value === null ) ? '' : String( value );
            return div.innerHTML;
        }

        function showNotice( type, message, detail ) {
            var html = '<div class="notice notice-' + type + ' inline"><p><strong>' + escapeHtml( message ) + '</strong>';
            if ( detail ) {
                html += '<br><span class="m365-sso-test-detail">' + escapeHtml( detail ) + '</span>';
            }
            html += '</p></div>';
            result.innerHTML = html;
        }

        function setBusy( busy ) {
            btn.disabled = busy;
            if ( spinner ) {
                spinner.classList.toggle( 'is-active', busy );
            }
        }

        btn.addEventListener( 'click', function () {
            setBusy( true );
            result.innerHTML = '<p class="m365-sso-test-testing">' + escapeHtml( cfg.testing ) + '</p>';

            var body = new URLSearchParams();
            body.append( 'action', cfg.action );
            body.append( 'nonce', cfg.nonce );

            fetch( cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            } ).then( function ( response ) {
                return response.json();
            } ).then( function ( json ) {
                var data    = ( json && json.data ) ? json.data : {};
                var message = data.message || cfg.failed;
                showNotice( json && json.success ? 'success' : 'error', message, data.detail || '' );
            } ).catch( function () {
                showNotice( 'error', cfg.failed, '' );
            } ).then( function () {
                setBusy( false );
            } );
        } );
    } );
}() );
