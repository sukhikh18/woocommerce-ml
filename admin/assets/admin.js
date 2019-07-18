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

    var preloader =
          '<div class="preloader"><div class="sk-folding-cube">'
        + '   <div class="sk-cube1 sk-cube"></div>'
        + '   <div class="sk-cube2 sk-cube"></div>'
        + '   <div class="sk-cube4 sk-cube"></div>'
        + '   <div class="sk-cube3 sk-cube"></div>'
        + '</div></div>';

    $('#get_statistic').on('click', function(event) {
        event.preventDefault();

        $('#statistic_table').append( preloader );
        $('#postsinfo').append( preloader );
        $('#termsinfo').append( preloader );
        setTimeout(function() {
            $('#statistic_table').find('.preloader').remove();
            $('#postsinfo').find('.preloader').remove();
            $('#termsinfo').find('.preloader').remove();
        }, 20000);

        $.ajax({
            url: ajaxurl,
            type: 'GET',
            dataType: 'JSON',
            data: {
                action: 'update_statistic',
                exchange_nonce: ml2e.nonce
            },
        })
        .done(function(response) {
            $('#statistic_table').html(response.table).find('.preloader').remove();
            $('#postsinfo').html(response.posts).find('.preloader').remove();
            $('#termsinfo').html(response.terms).find('.preloader').remove();
            console.log("Statistic updated");
        })
        .fail(function() {
            console.log("error");
        });
    }).click();

    $('#post_mode').on('change', function(event) {
        var selector = '#skip_post-wrap,#skip_post_author-wrap,#skip_post_title-wrap,' +
                       '#skip_post_content-wrap,#skip_post_excerpt-wrap,' +
                       '#skip_post_meta_value-wrap,#skip_post_attribute_value-wrap';

        if( 'off' == $(this).val() ) {
            $(selector).fadeOut();
        }
        else {
            $(selector).fadeIn();
        }
    }).change();

    $('#attribute_mode').on('change', function(event) {
        var selector = '#pa_name-wrap, #pa_desc-wrap';
        if( 'off' == $(this).val() ) {
            $(selector).fadeOut();
        }
        else {
            $(selector).fadeIn();
        }
    }).change();

    $('#category_mode').on('change', function(event) {
        var selector = '#cat_name-wrap, #cat_desc-wrap, #skip_parent-wrap';
        if( 'off' == $(this).val() ) {
            $(selector).fadeOut();
        }
        else {
            $(selector).fadeIn();
        }
    }).change();

    $('#developer_mode').on('change', function(event) {
        var selector = '#dev_name-wrap, #dev_desc-wrap';
        if( 'off' == $(this).val() ) {
            $(selector).fadeOut();
        }
        else {
            $(selector).fadeIn();
        }
    }).change();

    $('#warehouse_mode').on('change', function(event) {
        var selector = '#wh_name-wrap, #wh_desc-wrap';
        if( 'off' == $(this).val() ) {
            $(selector).fadeOut();
        }
        else {
            $(selector).fadeIn();
        }
    }).change();
});
