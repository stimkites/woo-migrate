/**
 *      Admin JS scripts
 */

'use strict';

/*global jQuery*/

(function($){

    /**
     * Input filter prototype
     */
    $.fn.inputFilter = function(inputFilter) {
        return this.on("input keydown keyup mousedown mouseup select contextmenu drop", function() {
            if (inputFilter(this.value)) {
                this.oldValue = this.value;
                this.oldSelectionStart = this.selectionStart;
                this.oldSelectionEnd = this.selectionEnd;
            } else if (this.hasOwnProperty("oldValue")) {
                this.value = this.oldValue;
                this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
            } else {
                this.value = "";
            }
        });
    };


    let

        /**
         * Define if we are launched
         */
        __launched = false,

        /**
         * Define if we are connected
         */
        __connected = false,

        /**
         * Switch data type
         */
        __switch_types = function(){
            $( '.migrate-rel' ).hide();
            $( '.migrate-rel.' + $( this ).val() ).show();
        },

        __collect_data = function( verb ){
            let data = { action: 'wtwmgr', do: verb };
            $( '#mainform' ).find( 'input, select, textarea' ).each( function(){
                if( this.type === 'checkbox' || this.type === 'radio' ) {
                    if ( this.checked )
                        data[this.name] = this.value;
                } else
                    data[ this.name ] = this.value;
            } );
            // Deactivate warning on leaving page
            setTimeout( function(){
                $('input, textarea, select').off( 'change' );
                $( ':input.wc-enhanced-select' ).each( function() {
                    $( this ).selectWoo().addClass( 'enhanced' );
                    $('.wc-enhanced-select').select2({ escapeMarkup: function (text) { return text; } });
                });
            }, 100 );
            return data;
        },

        /**
         * Prevent clicking on a form element
         */
        __noclick = function( e ){
            e.stopPropagation();
            e.preventDefault();
            return false;
        },

        /**
         * Block - unblock UI routine
         */
        __blocked = null,
        __block = function( elem ){
            __blocked = elem;
            $( elem ).block( {
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: '.4'
                }
            } );
        },
        __unblock = function(){
            $( __blocked ).unblock();
        },


        /**
         * Check connection
         */
        __check_connection = function( e ){
            let v = $('#url').val();
            if( ! v || v.indexOf( 'http' ) === -1 ) return __noclick( e );
            __block( $( this ).parents( 'td' ).first() );
            $.post( ajaxurl, __collect_data( 'connect' ), data => {
                __unblock();
                if( data.result === 'Ok' ) {
                    $('#wtwm-launch').removeAttr('disabled title').removeClass('disabled');
                    $('#wtwm-check-connection').attr('disabled', true).addClass('disabled');
                    $('#url').addClass('disabled').attr( 'disabled', true );
                    __connected = true;
                } else {
                    console.log( data );
                    if( data.error ) alert( data.error );
                    $('#wtwm-launch').attr('disabled', true).addClass('disabled');
                }
            } );
            return __noclick( e );
        },

        /**
         * Status calls handler
         */
        __stat = 0,

        /**
         * Stop status calls
         */
        __stop_stat = function(){
            $( '.on-stat' )
                .removeClass( 'disabled' )
                .removeAttr( 'disabled' );
            $( '#wtwm-stop' ).attr( 'disabled', true );
            if( ! __connected ) {
                $('#wtwm-launch').attr( 'disabled', true );
                $('#url').removeClass('disabled').removeAttr('disabled');
                $('#wtwm-check-connection').attr('disabled', false).removeClass('disabled');
            } else {
                $('#wtwm-launch').removeAttr( 'disabled' );
                $('#url').addClass('disabled').attr('disabled', true);
                $('#wtwm-check-connection').attr('disabled', true).addClass('disabled');
            }
            clearTimeout( __stat );
            return true;
        },

        /**
         * Display notice before transferring data to remote host
         *
         * @private
         */
        __show_verification = function( data ){
            let s = '', k = '', v = '', bb = [ 'b', 'Kb', 'Mb', 'Gb', 'Tb' ], j = 0;
            for( let i in data )
                if( data.hasOwnProperty( i ) ) {
                    k = i; v = data[i];
                    if( i === 'zsize' ) {
                        k = 'zip size';
                        while( v > 1024 && ++j < 5 ) v = Math.ceil( v / 1024 );
                        v += bb[j];
                    }
                    s += '<p><b>' + k + '</b>: ' + v + '</p><br/>';
                }
            $( '#data-to-verify' ).html( s );
            $('#wtwm-check-connection').attr('disabled', true).addClass('disabled');
            $('#url').addClass('disabled').attr( 'disabled', true );
            $( '#launch-controls' ).fadeOut( 200, () => {
                $( '#verify-data' ).fadeIn();
            } );
        },

        /**
         * Cancel verification, remove everything from server
         *
         * @private
         */
        __cancel_verification = function( e ){
            __block( '#mainform' );
            $.post( ajaxurl, { action: 'wtwmgr', do: 'cancel' }, data => {
                __unblock();
                if( data.error )
                    return alert( data.error );
                $( '#progress' ).fadeOut();
                $( '#verify-data' ).fadeOut( 200, () => {
                    $( '#launch-controls' ).fadeIn();
                } );
                __stop_stat();
            } );
            return __noclick( e );
        },

        /**
         * Status calls
         */
        __status = function(){
            $.post( ajaxurl, { action: 'wtwmgr', do: 'status' }, data => {
                $( '#progress label' ).html( data.operation );
                $( '#progress span' ).css( 'width', data.progress + '%' );
                if( data.completed )
                    return __stop_stat() && __show_verification( data.completed );
                if( data.error )
                    return __stop_stat() && alert( data.error );
                if( ! data.active ) return __stop_stat();
                __stat = setTimeout( __status, 500 );
            } );
        },

        /**
         * Start status calls
         */
        __start_stat = function(){
            $( '#mainform button:not([disabled]), #mainform input:not([disabled]), ' +
               '#mainform select:not([disabled]), #mainform textarea:not([disabled])' )
                .addClass( 'disabled on-stat' )
                .attr( 'disabled', true );
            $( '#progress' ).fadeIn();
            $( '#progress label' ).html('Checking...');
            $( '#progress span' ).css( 'width', '0%' );
            $( '#wtwm-stop' ).removeAttr( 'disabled' );
            __stat = setTimeout( __status, 500 );
        },

        /**
         * Status check call
         */
        __status_check = function(){
            __block( $( '#wtwm-check-connection' ).parents( 'td' ).first() );
            $.post( ajaxurl, { action: 'wtwmgr', do: 'status' }, data => {
                __unblock();
                if( data.completed )
                    return __show_verification( data.completed );
                if( data.active )
                    return __start_stat();
            } );
        },

        /**
         * Launch migration
         */
        __launch = function( e ){
            if( ! __connected ) return __noclick( e );
            __block( $( this ).parents( '#mainform' ).first() );
            $.post( ajaxurl, __collect_data( 'launch' ), data => {
                __unblock();
                if( data.result === 'Ok' ) {
                    __launched = true;
                    return __start_stat();
                }
                console.log( data );
                if( data.error ) alert( data.error );
            } );
            return __noclick( e );
        },

        /**
         * Interrupt (attempt to) migration
         */
        __stop = function( e ){
            $.post( ajaxurl, { action: 'wtwmgr', do: 'stop' } );
            return __noclick( e );
        },

        /**
         * Transfer data to remote host
         *
         * @param e
         * @returns {boolean}
         * @private
         */
        __transfer = function( e ){
            __block( $( '#mainform') );
            $.post( ajaxurl, { action: 'wtwmgr', do: 'transfer' }, data => {
                console.log( data );
                __unblock();
                if( data.error )
                    return alert( data.error );
                __start_stat();
                $( '#wtwm-cancel' ).removeAttr( 'disabled' ).removeClass( 'disabled' );
            } );
            return __noclick( e );
        },

        /**
         * Assign event handlers
         */
        __assign = function(){
            $( '.data-range' ).off().inputFilter( v => { return /^\d*-?\d*$/.test( v ) } );
            $( '#migrate-data-type' ).off().on( 'change', __switch_types ).trigger( 'change' );
            $( '#wtwm-check-connection' ).off().on( 'click', __check_connection );
            $( '#wtwm-launch' ).off().on( 'click', __launch );
            $( '#wtwm-stop' ).off().on( 'click', __stop );
            $( '#wtwm-cancel' ).off().on( 'click', __cancel_verification );
            $( '#wtwm-transfer' ).off().on( 'click', __transfer );
        };

    return {

        /**
         * Initialize events
         */
        init : function(){
            $( document ).on( 'ready ajaxStop', __assign );
            $( document ).on( 'ready', __status_check );
        }
    }
})(jQuery.noConflict()).init();