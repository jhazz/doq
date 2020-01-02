<?php
spl_autoload_register(function ($className) {
    $moduleSource=$GLOBALS['config']['libPath'].'/'.str_replace('\\', '/', $className).'.php';
    if(file_exists($moduleSource)){
        require_once $moduleSource;
    } else {
        trigger_error("Class file not found: '{$moduleSource}'",E_USER_ERROR);
    }
});
?>