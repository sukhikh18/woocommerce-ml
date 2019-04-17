<?php
namespace NikolayS93\Exchange;

?>
<textarea id="ex-report-textarea" style="width: 100%; height: 350px;">
<?php

if( $last = Plugin::get('last_update') ) {
    echo "Последнее обновление: {$last}\n";
}

?>
</textarea>
