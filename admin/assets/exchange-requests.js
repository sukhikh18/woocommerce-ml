jQuery(document).ready(function($) {

    /**
     * @global ml2e
     */

    var error_msg = 'Случилась непредвиденая ошибка, попробуйте повторить позже';

    const $progress = $('.progress .progress-fill');
    const $report = $('#ex-report-textarea');
    const $status = $('#ajax_action');
    const allProgress = 5;

    var progress = 0;

    const addReport = function(msg) {
        if(!msg) return;

        $report.append(msg + '\n');
        $report.scrollTop($report[0].scrollHeight);
    }

    const timer = {
        d : new Date(0, 0, 0, 0, 0, 0, 0, 0),
        timer : 'not init. interval',
        del : ' : ',

        addLead : function(num)
        {
            var s = num+"";
            if (s.length < 2)
                s = "0" + s;
            return s;
        },

        stop : function()
        {
            clearInterval( this.timer );
        },

        start : function()
        {
            var self = this;
            function updateClock()
            {
                self.d.setSeconds(self.d.getSeconds() + 1);

                var h = self.d.getHours(),
                    m = self.d.getMinutes(),
                    s = self.d.getSeconds();

                $('#timer.ex-timer').text( self.addLead(h) + self.del + self.addLead(m) + self.del + self.addLead(s) );
            }

            this.timer = setInterval(updateClock, 1000);
        },
    }

    const ajax_request = {
        request: null,

        __setProgress: function( int ) {
            int = parseFloat(int);
            if( int ) $progress.css('width', 100 * int / allProgress + '%' );

            if( 100 == int ) {
                $progress.css('background', '#14B278');
            }
        },

        error: function( response ) {
            this.end();
            $status.html( '<span style="color: red;">' + error_msg + '</span>' );
            $progress.css('background', '#ED3752');
            addReport( response );

            return false;
        },

        start: function() {
            var self = this;

            this.request = $.ajax({
                type: 'POST',
                url: ml2e.exchange_url,

                data: {
                    'type': 'catalog',
                    'mode': 'import',
                },

                error: function() { self.error(); },

                beforeSend: function(jqXHR, settings) {
                    if( 0 == progress ) {
                        // write a progress status (files prepared)
                        this.data = this.data.replace('mode=import', 'mode=file');
                    }

                    return true;
                },

                success: function( response ) {
                    // First query
                    if( 0 == progress ) {
                        addReport('Успешный старт выгрузки информации');
                        progress = 1;
                        self.__setProgress(progress);
                        self.start();
                    }

                    else if( 0 === response.indexOf('success') ) {
                        addReport('Выгрузка успешно завершена');
                        self.__setProgress(100);
                        self.end();
                    }

                    else if( 0 === response.indexOf('progress') || ml2e.debug_only ) {
                        addReport( response.split('\n', 2)[1] );
                        self.__setProgress( progress++ );
                        self.start();
                    }

                    if( 0 <= response.indexOf('error') || 0 === response.indexOf('failure') ) {
                        this.error( response );
                        return false;
                    }
                }
            });
        },

        stop: function() {
            if( this.request ) this.request.abort();
        },

        end: function() {
            $( '#stop-exchange' ).attr('disabled', 'true');
            $( '#exchangeit' ).attr('disabled', 'true');

            self.stop();
            timer.stop();
        }
    }

    $( '#exchangeit' ).on('click', function(event) {
        event.preventDefault();

        timer.start();
        ajax_request.start();

        $(this).removeClass('button-primary');
        $( '#exchangeit' ).attr('disabled', 'true');
        $( '#stop-exchange' ).removeAttr('disabled');
    });

    $( '#stop-exchange' ).on('click', function(event) {
        event.preventDefault();

        timer.stop();
        error_msg = 'Импорт товаров прерван!';

        ajax_request.stop();
        $( '#stop-exchange' ).attr('disabled', 'true');
        $( '#exchangeit' ).attr('disabled', 'true');
    });
});
