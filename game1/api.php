<?php
require_once 'secrets.php';

class Api{
    static public $browserKey;
    static public $sessionKey;
    static public $sessionId;
    static public $userId;
    static public $browserId;
    static public $actionHandlers=['hello', 'signupCheck', 'signup'];
    static private $dbh;
    static public $message;
    
    static public function init(){
        $headers = getallheaders();
            if (stripos($headers["Content-type"],"application/json")===false) {
            exit;
        }
        set_exception_handler(
            function ($exception) {
                print json_encode(['error'=>'Exception: '.$exception->getMessage(),
                    'file'=>$exception->getFile(), 
                    'line'=>$exception->getLine()
                ],
                JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            }
        );

        self::$dbh = new PDO(Secrets::get('pdo'), Secrets::get('pdoLogin'), Secrets::get('pdoPass'));
        self::$message='';
        
        $request=json_decode(file_get_contents("php://input"), true) ?: [];
        self::$userId=self::$browserId=self::$browserKey=false;
        
        if(isset($_COOKIE['BROWSER_KEY'])) {
            self::$browserKey=$_COOKIE['BROWSER_KEY']; // 24 chars
            self::$browserId=intval(self::decrypt(self::$browserKey, Secrets::get('browser_id_salt')));
            self::$message.='Ключ browserKey['.self::$browserKey.'] расшифрован как '.self::$browserId.' --';
        }

        if(self::$browserId){
            $sth=self::$dbh->prepare('SELECT COUNT(*) FROM browsers WHERE browser_id=?');
            $sth->execute([self::$browserId ]);
            $r=$sth->fetch(PDO::FETCH_NUM);
            if($r===false){
                $x=$sth->errorInfo();
                print '{"error":"Запрос на регистрацию браузера отклонен:","info":'.json_encode($x,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'}';
                return false;
            } else {
                if(intval($r[0])!==1){
                    self::$browserId=0;
                }
            }
        }
        
        if(!self::$browserId){
            self::$dbh->beginTransaction();
            $sth=self::$dbh->prepare("INSERT INTO browsers (agent,last_ip_addr) VALUES(?,?)");
            $sth->execute([$_SERVER['HTTP_USER_AGENT'],$_SERVER['REMOTE_ADDR']]);
            self::$browserId=self::$dbh->lastInsertId();
            if(self::$browserId){
                self::$dbh->commit();
                self::$browserKey=self::encrypt(self::$browserId, Secrets::get('browser_id_salt')); 
                setcookie('BROWSER_KEY', self::$browserKey, time()+31536000);
            } else {
                print '{"error":"К сожалению сервер базы данных не обрабатывает запросы. Авторизация невозможна. Все остальное, соответственно, тоже"}';
                return false;
            }
        } else {
            if(isset($_COOKIE['SESSION_KEY'])) {
                self::$sessionKey=$_COOKIE['SESSION_KEY']; 
                self::$sessionId=intval(self::decrypt(self::$sessionKey, Secrets::get('session_id_salt')));
                self::$message.='Ключ sessionKey['.self::$sessionKey.'] расшифрован как '.self::$sessionId.' --';
            }
        }
        
        if(self::$sessionId){
            $sth=self::$dbh->prepare('SELECT user_id FROM sessions WHERE browser_id=? AND session_id=? AND date_blocked IS NULL');
            $sth->execute([self::$browserId , self::$sessionId]);
            $r=$sth->fetch(PDO::FETCH_NUM);
            if($r===false){
                self::$message.='Сессия не найдена в базе. Генерим заново';
                self::$sessionId=0;
            } else {
                self::$userId=intval($r[0]); // null->0
            }
        }

        if(!self::$sessionId){
            self::$dbh->beginTransaction();
            $sth=self::$dbh->prepare("INSERT INTO sessions (ip_addr,browser_id) VALUES(?,?)");
            $r=$sth->execute([$_SERVER['REMOTE_ADDR'], self::$browserId]);
            if(!$r){
                $x=$sth->errorInfo();
                print '{"error":"Запрос на регистрацию сессии отклонен:","info":'.json_encode($x,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'}';
                return false;
            }
            
            self::$sessionId=self::$dbh->lastInsertId();
            if(self::$sessionId){
                self::$dbh->commit();
                self::$sessionKey=self::encrypt(self::$sessionId, Secrets::get('session_id_salt')); 
                setcookie('SESSION_KEY', self::$sessionKey);
            } else {
                print '{"error":"К сожалению сервер базы данных не обрабатывает запросы по сессиям. Авторизация невозможна. Все остальное, соответственно, тоже"}';
                return false;
            }
        } 
        
        
        $action=$request['action'];
        if(in_array($action, self::$actionHandlers)) {
            print json_encode(self::$action($request), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }
        return true;
   }
  
    public static function encrypt($message, $key)
    {
        $method=\Secrets::get('crypto_method');
        $saltSize=openssl_cipher_iv_length($method);
        if (function_exists('random_bytes')) {
            $salt = random_bytes($saltSize);
        } else if (function_exists('random_bytes')) {
            $salt = openssl_random_pseudo_bytes($saltSize);
        }
        $ciphertext = openssl_encrypt($message, $method, $key, OPENSSL_RAW_DATA, $salt);
        return base64_encode($salt.$ciphertext);
    }

    public static function decrypt($message, $key)
    {
        $method=\Secrets::get('crypto_method');
        $message = base64_decode($message, true);
        if ($message === false) {
            return false;
        }
        $saltSize=openssl_cipher_iv_length($method);
            
        $salt = substr($message, 0, $saltSize);
        $ciphertext = substr($message, $saltSize);
        $text= openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $salt);
        return $text;
    }
    
    private static function makeServerNonce(int $size=0) {
        if(!$size){
            $size=\Secrets::get('snonce_size');
        }
        if (function_exists('random_bytes')) {
            return random_bytes($size);
        } else if (function_exists('random_bytes')) {
            return openssl_random_pseudo_bytes($size);
        }
    }

    private static function hello($request){
        if(!self::$browserId){
            return ['error'=>'Авторизация не работает'];
        }
        $snonceBase=self::makeServerNonce();
        
        return [
            'snonce'=>self::encrypt($snonceBase, Secrets::get('snonce_salt')),
            'browserKey'=>self::$browserKey,
            'browserId'=>self::$browserId,
            'sessonKey'=>self::$sessionKey,
            'sessionId'=>self::$sessionId,
            'userId'=>self::$userId,
            'message'=>self::$message
            ];
    }

    private static function isEmail($s){
        return preg_match('/^((([0-9A-Za-z]{1}[-0-9A-z\.]{1,}[0-9A-Za-z]{1})|([0-9А-Яа-я]{1}[-0-9А-я\.]{1,}[0-9А-Яа-я]{1}))@([-A-Za-z]{1,}\.){1,2}[-A-Za-z]{2,})$/u',$s);
    }
    private static function signupCheck($request){
        if(!isset($request['login'])){
            return ['error'=>'Нет логина в запросе'];
        }
        $login=$request['login'];
        
        if (!preg_match('/^[a-zA-Z]+[a-zA-Z0-9._\-\@]+$/', $login)) {
            return ['error'=>'Логин не должен содержать других символов кроме латинских букв и цифр и @'];
        }
        
        $sha1Login=sha1(strtolower(trim($login)).\Secrets::get('login_db_salt'));

        $sth=self::$dbh->prepare('SELECT user_id FROM users WHERE email=? OR login=?');
        $sth->execute([$sha1Login, $sha1Login]);
        $r=$sth->fetch(PDO::FETCH_NUM);
        if($r===false){
            return['found'=>false];
        } else {
            $snonce=self::getNonce();
            return['found'=>true,'snonce'=>$snonce];
        }
    }
    
    private static function signup($request){
        if(!isset($request['login'])){
            return ['error'=>'Нет логина в запросе'];
        }
        if(!isset($request['passHash'])){
            return ['error'=>'Нет парольного хеша в запросе'];
        }
        
        return[
            'result'=>'ok'
        ];
    }
    

}
Api::init();






?>
