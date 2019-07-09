<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Section;

$Page->add_section( new Section(
    'statistic',
    __('Report', DOMAIN),
    function() {
        ?>
        <p class="submit" style="margin-top: -65px;"><input type="button" name="get_statistic" id="get_statistic" class="button  button-primary right" value="Обновить статистику"></p>
        <!-- <div class="postbox" style="margin-bottom: 0;">
            <h2 class="hndle" style="cursor: pointer;"><span>Статистика</span></h2>
            <div class="inside">
                -->
                <div id="statistic_table">
                    <?php statisticTable( true ); ?>
                </div><!--
            </div>
        </div> -->
        <?php
    }
) );