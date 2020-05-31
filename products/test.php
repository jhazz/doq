<?php
require_once 'autorun.php';
doq\Html::subTitle('Testing');
doq\Logger::placeConsole();
doq\Html::body();

list($viewProducts,$err)=doq\data\View::create('products');
list($datanode, $rowCount, $err)=$viewProducts->read();
print 'Total rows:'.$rowCount.'<br>';

list($products, $err)=\doq\data\ScopeStack::create($datanode);
do{
    list($sku,$title)=$products->extract(['SKU','TITLE']);
    print $products->top->rowNo.': SKU='.$sku.' TITLE='.$title.'<br>';
} while (!$products->next());

?>
