<?php
require_once 'autorun.php';

doq\Html::subTitle('Testing');
//doq\Session::init();
doq\Logger::placeConsole();
doq\Html::body();
doq\data\Connections::init();

list($viewProducts,$err)=doq\data\View::create('products');
$params=[];
list($dataset,$err)=$viewProducts->read($params);

print '<pre>';
print_r ($dataset);


/* 'SELECT u.LOGIN, c.CLIENT_ID, c.USER_ID, u.IS_DISABLED 
 * FROM sys_clients c, sys_users u 
 * WHERE u.USER_ID=c.USER_ID AND c.CLIENT_KEY=? AND c.REFRESH_TIME>(now()-INTERVAL '.self::TIMEOUT_CLIENT.' SECOND
 */

?>
