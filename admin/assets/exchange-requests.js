jQuery(document).ready(function($) {
    /**
     * @var @global ml2e {
     *      exchange_url: server/exchange/
     *      files: exists filenames array
     *      productsCount: counts for calculate full path
     *      OffersCount:   counts for calculate full path
     *      debug_only: bool
     * }
     */

    var error_msg = 'Случилась непредвиденая ошибка, попробуйте повторить позже';

    var $progress = $('.progress .progress-fill'),
    var $report = $('#ex-report-textarea');
    var $status = $('#ajax_action');
    /** @var int */
    var fullSteps;

    var step = 0;

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

    function addReport(msg) {
        if(!msg) return;

        $report.append(msg + '\n');
        $report.scrollTop($report[0].scrollHeight);
    }

    const ajax_request = {
        request: null,

        getFilename: function() {
            return ml2e.files.shift();
        },

        stop: function() {
            if( this.request ) this.request.abort();
            timer.stop();
        },

        setProgress: function( int ) {
            /**
             * Fill success color
             */
            if( 100 == int ) {
                $progress.css('background', '#14B278');
                $progress.css('width', '100%');
                return;
            }

            /**
             * Fill the progress bar
             */
            int = parseFloat(int);
            if( int ) $progress.css('width', 100 * int / fullSteps + '%' );
        },

        error: function( response ) {
            /**
             * Stop procedure
             */
            this.stop();

            /**
             * Do not repeat, we have a error!
             */
            $( '#stop-exchange' ).attr('disabled', 'true');
            $( '#exchangeit' ).attr('disabled', 'true');

            /**
             * Fill error colors
             */
            $status.html( '<span style="color: red;">' + error_msg + '</span>' );
            $progress.css('background', '#ED3752');

            /**
             * Stay error message to textarea
             */
            addReport( response );

            return false;
        },

        onStart: function() {
            var self = this;

            /**
             * Initialaze exhcange, clear data, check allows..
             */
            this.request = $.ajax({
                error: self.error,
                type: 'GET',
                url: ml2e.exchange_url,

                data: {
                    'type': 'catalog',
                    'mode': 'init',
                },

                success: function( response ) {
                    addReport('Успешный старт выгрузки информации');
                    self.import( self.getFilename() );
                }
            });
        },

        import: function( filename ) {
            var self = this;

            this.request = $.ajax({
                // beforeSend: function(jqXHR, settings) {
                //     if( 0 == progress ) {
                //         // write a progress status (files prepared)
                //         this.data = this.data.replace('mode=import', 'mode=file');
                //     }

                //     return true;
                // },
                error: self.error,
                type: 'GET',
                url: ml2e.exchange_url,

                data: {
                    'type': 'catalog',
                    'mode': 'import',
                    'filename': filename
                },

                success: function( response ) {
                    /** Most important information in second string */
                    var answerMessage = response.split('\n', 2)[1];

                    if( 0 <= response.indexOf('error') || 0 === response.indexOf('failure') ) {
                        /** send response to report too */
                        this.error( response );
                        addReport( answerMessage );
                        // Frontend protection ;D
                        return false;
                    }

                    else if( 0 === response.indexOf('progress') ) {
                        addReport( answerMessage );
                        self.setProgress( step++ );
                        self.start();
                    }

                    else if( 0 === response.indexOf('success') ) {
                        addReport( answerMessage );

                        var nextFilename = self.getFilename();

                        if( nextFilename ) {
                            
                        }

                        /** Filenames is over */
                        else {
                        }
                    }
                }
            });
        },

        onEnd: function() {
            addReport('Выгрузка успешно завершена.');
        },

        start: function() {

            this.request = $.ajax({

                success: function( response ) {
                    else if( 0 === response.indexOf('success') ) {
                        addReport('Получен успешный ответ.');
                        self.setProgress(100);
                        self.end();
                    }
                }
            });
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
