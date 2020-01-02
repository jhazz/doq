<?php
$GLOBALS['config']=array_merge (
    require('config.php'),
    require_once(dirname(__FILE__,2).'/common/config.php')
);
require_once($GLOBALS['config']['libPath'].'/classloader.php');
\doq\Logger::init();
\doq\I18n::init($GLOBALS['config']);
\doq\I18n::target('ru-RU');

?>