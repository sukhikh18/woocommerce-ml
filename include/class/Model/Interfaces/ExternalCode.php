<?php

namespace NikolayS93\Exchange\Model\Interfaces;

interface ExternalCode
{
    function getExternal();
    function setExternal($ext);
    static function fillExistsFromDB( &$objects );
}