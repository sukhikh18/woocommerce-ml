<?php
// $Page->add_metabox( new Admin\Metabox(
//     'uploadbox',
//     __('Upload New Files', DOMAIN),
//     function() {
//         Plugin::get_admin_template('uploadbox', false, $inc = true);
//     }
// ) );
?>
<div class="uploader-inline" style="text-align: center;">
    <!-- <button class="close dashicons dashicons-no"><span class="screen-reader-text">Закрыть окно загрузчика</span></button> -->

    <div class="uploader-inline-content no-upload-message">
        <div class="upload-ui">
            <h2 class="upload-instructions drop-instructions"><?php _e( 'Drop files anywhere to upload' ); ?></h2>
            <p class="upload-instructions drop-instructions"><?php _ex( 'or', 'Uploader: Drop files here - or - Select Files' ); ?></p>
            <button id="upload-button" type="button" class="browser button button-hero"><?php _e( 'Select Files' ); ?></button>
            <input id="upload-new-files-input" type='file' name='files[]' multiple style="display: none;" />
        </div>

        <div class="upload-inline-status"></div>

        <div class="post-upload-ui">
            <?php
            $max_upload_size = wp_max_upload_size();
            if ( ! $max_upload_size ) {
                $max_upload_size = 0;
            }
            ?>

            <p class="max-upload-size"><?php
            printf( __( 'Maximum upload file size: %s.' ), esc_html( size_format( $max_upload_size ) ) );
            ?></p>
        </div>
    </div>
</div>
