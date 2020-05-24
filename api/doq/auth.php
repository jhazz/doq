<?php
require_once 'autorun.php';
        
$requestText=file_get_contents("php://input");
$request=json_decode($requestText, true) ?: [];

//doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);
switch($_GET['action']) {
  case 'getNonce':
    list($snonce,$err)=\doq\Auth::getFormNonce();
    if ($nonce===false) {
      print json_encode (['error' => 'Could not acquire form nonce from database']);
    } else {
      print json_encode (['nonce' => $nonce]);
    }
    break;
  case 'signupNewUser':
    $r=\doq\Auth::signupNewUser($request);
    print json_encode ($r);
    break;
  case 'getLoginNonces':
    $r=\doq\Auth::getLoginNonces($request);
    print json_encode ($r);
    break;
  case 'signIn':
    $r=\doq\Auth::signIn($request);
    print json_encode ($r);
    break;
}
?>