<?php
require_once 'autorun.php';
doq\Html::subTitle('Testing');
doq\Logger::placeConsole();
doq\Html::body();


list($viewProducts,$err)=doq\data\View::create('products');
$roles=['store.editors'];
$viewProducts->prepareWriter($viewProducts->viewModifyTime,true, $roles);

print '<pre>';
print_r($viewProducts->viewCfg);
print '</pre>';
print '<hr>';

print '<pre>';
print_r($viewProducts->writerDefs);
print '</pre>';



?>
