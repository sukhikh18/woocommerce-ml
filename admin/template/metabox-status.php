<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Metabox;

$Page->add_metabox( new Metabox(
    'status',
    __('Status', DOMAIN),
    function() { ?>
        <div id="timer" class='ex-timer'>
            <span class='hours'>0</span>:<span class='minutes'>00</span>:<span class='seconds'>00</span>
        </div>

        <p>
            <button type="button" class="button button-danger right" id="stop-exchange" disabled="true">Прервать импорт</button>
            <button type="button" class="button button-primary" id="exchangeit" data-action="start">Начать</button>
        </p>

        <p>
            <small>
                <span style="color: red;">*</span> Если прервать импорт, возобновить его не получится.
            </small>
        </p>
    <?php }
) );
