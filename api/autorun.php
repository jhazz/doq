<?php 

/** application name is a name of the first subdirectory after folder 'api' */
function extractAppName(){
    $c=(strpos("\\", $_SERVER['SCRIPT_FILENAME']) !== false)?"\\":"/";
    $parts=explode($c,$_SERVER['SCRIPT_FILENAME']);
    $n=array_search('api',$parts);
    if(($n!==false)&&(count($parts)>$n)){
        return $parts[$n+1];
    }
    return '';
}

$ROOT_PATH=dirname(__FILE__,2);
if(!isset($APP_NAME)) {
    $APP_NAME=extractAppName();
}
$APP_PATH=$ROOT_PATH.'/'.$APP_NAME;

$GLOBALS['doq']=[];
if(!file_exists($APP_PATH.'/env.php')){
    $GLOBALS['doq']['env']=require_once($ROOT_PATH.'/common/env.php');
} else {
    $GLOBALS['doq']['env']=array_merge (require_once($ROOT_PATH.'/common/env.php'), require_once($APP_PATH.'/env.php'));
}
require_once($GLOBALS['doq']['env']['#libPath'].'/classloader.php');
?>