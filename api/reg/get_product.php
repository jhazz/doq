<?php
require_once '../doq/Logger.php';

if(!$GLOBALS['doq']) {
  $GLOBALS['doq']=[];
}

$GLOBALS['doq']['classes']=[
  'doq\Logger'=>['doq/Logger.php'],
  'doq\Template'=>['doq/Template.php'],
  'doq\I18n'=>['doq/I18n.php']
];


\doq\Logger::init();
\doq\Logger::info('Лог ошибок');

spl_autoload_register(function ($class) {
  $classModule=$GLOBALS['doq']['classes'][$class];
  if($classModule){
    foreach ($classModule as $k=>$moduleSource){
      if($moduleSource){
        doq\Logger::info('Подключил '.$moduleSource);
        require_once '../'.$moduleSource;
      }
    }
  }
});

\doq\I18n::init();
\doq\I18n::target('ru-RU');
trigger_error(\doq\tr('default','session_non_initialized',),E_WARNING);

\doq\I18n::category('default');
$template=doq\Template::create();
trigger_error(\doq\I18n::t('session_non_initialized',),E_USER_WARNING);
#die('Die message'); // just outputs Die message, no 'Fatal error' message
function one(){
    throw new Exception('Throw error message');
}
function two_call_one(){
    one();
}
two_call_one();

?>
