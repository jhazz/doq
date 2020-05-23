<?php
$GLOBALS['doq']=[
    'env'=>array_merge (
        require_once(dirname(__FILE__,2).'/common/env.php'),
        require_once('env.php')
    )
];
$GLOBALS['doq']['schema']=require_once($GLOBALS['doq']['env']['#commonPath'].'/schema.php');
$GLOBALS['doq']['views']=require_once($GLOBALS['doq']['env']['#commonPath'].'/views.php');
require_once($GLOBALS['doq']['env']['#libPath'].'/classloader.php');
\doq\Logger::init($GLOBALS['doq']['env']['@log']);
\doq\Html::init($GLOBALS['doq']['env']['@html']);
\doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);
\doq\I18n::init($GLOBALS['doq']['env']['@lang']);
\doq\I18n::target($GLOBALS['doq']['env']['@lang']['#defaultTarget']);

?>