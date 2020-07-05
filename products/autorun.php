<?php
$ROOT_PATH=dirname(__FILE__,2);
$APP_NAME=extractAppName();
$APP_PATH=$ROOT_PATH.'/'.$APP_NAME;
$TMP_PATH=sys_get_temp_dir();

$GLOBALS['doq']=['env'=>array_merge (require_once($ROOT_PATH.'/common/env.php'),require_once('env.php'))];

require_once($GLOBALS['doq']['env']['#libPath'].'/classloader.php');
\doq\Logger::init();
\doq\I18n::init();
\doq\I18n::target($GLOBALS['doq']['env']['@lang']['#defaultTarget']);
\doq\Html::setLink(['rel'=>'icon', 'type'=>'image/png', 'href'=>'../www/favicon/favicon-16x16.png', 'sizes'=>'16x16']);
\doq\Html::setLink(['rel'=>'icon', 'type'=>'image/png', 'href'=>'../www/favicon/favicon-32x32.png', 'sizes'=>'32x32']);

function extractAppName(){
    $s=$_SERVER['SCRIPT_FILENAME'];
    $c=(strpos("\\", $s) !== false)?"\\":"/";
    $parts=explode($c,$s);
    return $parts[count($parts)-2];
}

?>