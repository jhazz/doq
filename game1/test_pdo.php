<?php

require_once 'secrets.php';

class Api{
    static private $dbh;
    
    static public function test(){
        self::$dbh = new PDO(Secrets::get('pdo'), Secrets::get('pdoLogin'), Secrets::get('pdoPass'));
        print "DBH:<pre>";
        print_r(self::$dbh);
        print "</pre><hr>";
        self::$dbh->beginTransaction();
        $sth=self::$dbh->prepare("INSERT INTO browsers (agent) VALUES(?)");
        $sth->execute([$_SERVER['HTTP_USER_AGENT']]);
        $browserId=self::$dbh->lastInsertId();
        self::$dbh->commit();
        print "Browser ID = ".$browserId;
    }
    
}
Api::test();






?>
