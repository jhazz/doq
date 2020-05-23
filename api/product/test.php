<?php
require_once '../autorun.php';
doq\Html::subTitle('Testing');
doq\Logger::placeConsole();
//doq\Session::init();
doq\Html::body();

print "Hello!";
doq\data\Connections::init();
list($viewProducts,$err)=doq\data\View::create($GLOBALS['doq']['schema'],$GLOBALS['doq']['views']['Products'],'Products1');


/* 'SELECT u.LOGIN, c.CLIENT_ID, c.USER_ID, u.IS_DISABLED 
 * FROM sys_clients c, sys_users u 
 * WHERE u.USER_ID=c.USER_ID AND c.CLIENT_KEY=? AND c.REFRESH_TIME>(now()-INTERVAL '.self::TIMEOUT_CLIENT.' SECOND
*/

?>
