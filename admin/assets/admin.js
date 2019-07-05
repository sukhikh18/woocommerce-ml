jQuery(document).ready(function($) {
    var $progress = $('.progress .progress-fill');
    var $status = $('#exchangeStatus');
    var $report = $('#exchangeReport');

    var $startButton = $( '#exchangeit' );
    var $stopButton  = $( '#stop-exchange' );

    var Timer = new ExchangeTimer( '#timer.ex-timer' );

    var Exchange = new ExhangeProgress({
        $report: $report,
        $progress: $progress,
        onEnd: function() {
            Timer.stop();
        },
        onError: function() {
            /**
             * Do not repeat - we have a error!
             */
            $startButton.attr('disabled', 'true');
            $stopButton.attr( 'disabled', 'true');

            $status.html( '<span style="color: red;">' + this.error.msg + '</span>' );

            this.onEnd();
        }
    });

    $startButton.on('click', function(event) {
        event.preventDefault();

        Timer.start();
        Exchange.start();

        $(this).removeClass('button-primary');
        $( '#exchangeit' ).attr('disabled', 'true');
        $( '#stop-exchange' ).removeAttr('disabled');
    });

    $stopButton.on('click', function(event) {
        event.preventDefault();

        Timer.stop();
        Exchange.setError('Импорт товаров прерван!');

        $( '#stop-exchange' ).attr('disabled', 'true');
        $( '#exchangeit' ).attr('disabled', 'true');
    });

    // @todo add preloader
    $('#get_statistic').on('click', function(event) {
        event.preventDefault();

        $.ajax({
            url: ajaxurl,
            type: 'GET',
            dataType: 'html',
            data: {
                action: 'statistic_table',
                exchange_nonce: ml2e.nonce
            },
        })
        .done(function(html) {
            $('#statistic_table').html(html);
            console.log("Statistic updated");
        })
        .fail(function() {
            console.log("error");
        });
    });
});
