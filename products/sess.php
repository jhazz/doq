<?php
require_once 'autorun.php';

doq\Html::subTitle('Session test');
//doq\Session::init();
doq\Logger::placeConsole();
doq\Html::body();

list($viewProducts,$err)=doq\data\View::create('products');
$params=[];
list($datanode, $rowCount, $err)=$viewProducts->read($params);
print 'Total rows:'.$rowCount.'<br>';

list($products, $err)=\doq\data\Context::create($datanode);
do{
    list($sku,$title)=$products->extract(['SKU','TITLE']);
    print $products->top->rowNo.'=='.$sku.'=>'.$title.'<br>';
} while (!$products->next());


/* 'SELECT u.LOGIN, c.CLIENT_ID, c.USER_ID, u.IS_DISABLED 
 * FROM sys_clients c, sys_users u 
 * WHERE u.USER_ID=c.USER_ID AND c.CLIENT_KEY=? AND c.REFRESH_TIME>(now()-INTERVAL '.self::TIMEOUT_CLIENT.' SECOND
 */

?>
