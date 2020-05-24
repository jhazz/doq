<?php
$ROOT_PATH=dirname(__FILE__,2);
$APP_NAME=extractAppName();
$APP_PATH=$ROOT_PATH.'/'.$APP_NAME;
$GLOBALS['doq']=['env'=>array_merge (require_once($ROOT_PATH.'/common/env.php'),require_once('env.php'))];

require_once($GLOBALS['doq']['env']['#libPath'].'/classloader.php');
\doq\Logger::init();

//\doq\Html::init($GLOBALS['doq']['env']['@html']);
//\doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);
\doq\I18n::init();
\doq\I18n::target($GLOBALS['doq']['env']['@lang']['#defaultTarget']);


function extractAppName(){
    $s=$_SERVER['SCRIPT_FILENAME'];
    $c=(strpos("\\", $s) !== false)?"\\":"/";
    $parts=explode($c,$s);
    return $parts[count($parts)-1];
}

?>