<?php
class Secrets{
    private static $secrets=[
        'crypto_method'=>'aes-256-ctr',
        'snonce_salt'=>'wzqf89yqwf',
        'snonce_size'=>20,
        'user_id_salt'=>'abHu342h_234hj',
        'browser_id_salt'=>'22$@iu342h_234bbdz',
        'session_id_salt'=>'sacr21c1412c2Hjgj',
        'login_db_salt'=>'cj3hsdjfhjks4hJHsdfhk34@#',
        '@pdo/dsn'=>'mysql:host=localhost;dbname=game',
        '@pdo/login'=>'vlad',
        '@pdo/pass'=>'raptor14'
    ];

    public static function get($name) {
        return self::$secrets[$name];
    }

}
?>