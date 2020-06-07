<?php
require_once 'autorun.php';
doq\Html::subTitle('Testing');
doq\Logger::placeConsole();
doq\Html::body();


list($viewProducts,$err)=doq\data\View::create('products');
$viewProducts->prepareWriter(0,true);

print '<pre>';
print_r($viewProducts->cfgView);
print '</pre>';
print '<hr>';

print '<pre>';
print_r($viewProducts->writerDefs);
print '</pre>';



?>
