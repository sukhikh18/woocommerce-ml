jQuery(document).ready(function($) {

    /**
     * Upload new files
     */
    var $uploadInput = $('#upload-new-files-input');
    var $uploadUI = $('#metabox2 .inside');

    /**
     * click for to change
     */
    $('#upload-button').on('click', function(event) {
        event.preventDefault();

        $uploadInput.trigger('click');
    });

    $uploadInput.on('change', function(event) {
        send_files( event.target.files );
    });

    /**
     * Drop for to change
     */
    $uploadUI.on('dragover', function(event) {
        $uploadUI.addClass('dragged');
        return false;
    });

    $uploadUI.on('dragleave', function(event) {
        $uploadUI.removeClass('dragged');
        return false;
    });

    $uploadUI.on('drop', function(event) {
        event.preventDefault();

        $uploadUI.removeClass('dragged');
        $uploadUI.trigger('dragleave');
        $uploadUI.addClass('drop');

        if( event.originalEvent.dataTransfer.files.length ) {
            send_files(event.originalEvent.dataTransfer.files);
        }
    });

    function send_files(files) {
        var data = new FormData();

        data.append("action", "exchange_files_upload");
        $.each(files, function(i, file)
        {
            data.append("file_"+i, file);
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            cache: false,
            dataType: 'json',
            processData: false, // Don't process the files
            contentType: false, // Set content type to false as jQuery will tell the server its a query string request
            success: function(data, textStatus, jqXHR) {
                if( 'SUCCESS' == data.response ) {
                    location.reload();
                }
                else {
                    alert('Fatal error: Check later.');
                }
            }
        });

        return false;
    }
});