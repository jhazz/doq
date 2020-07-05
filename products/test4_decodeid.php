<?php
require_once 'autorun.php';
doq\Html::subTitle('Test encode/decody identity values');
?><style>body,html {padding:0; margin:0; width:100%; height:100vh;}
pre {font-family:'dejavu sans mono',consolas;font-size:8pt;height:90vh; overflow:auto; width:100%; white-space:pre-wrap;}
</style><?php
doq\Logger::placeConsole();
doq\Html::body();


class UnsafeCrypto
{
    //const METHOD = 'aes128';
    const METHOD = 'aes-256-ctr';
    
    public static function encrypt($message, $key)
    {
        $nonceSize = openssl_cipher_iv_length(self::METHOD);
        if (function_exists('random_bytes')) {
            $nonce=random_bytes($nonceSize);
        } else if (function_exists('random_bytes')) {
            $nonce = openssl_random_pseudo_bytes($nonceSize);
        }
        $ciphertext = openssl_encrypt($message, self::METHOD, $key, OPENSSL_RAW_DATA, $nonce);
        return base64_encode($nonce.$ciphertext);
    }

    public static function decrypt($message, $key)
    {
        $message = base64_decode($message, true);
        if ($message === false) {
            throw new Exception('Encryption failure');
        }
        $nonceSize = openssl_cipher_iv_length(self::METHOD);
        $nonce = substr($message, 0, $nonceSize);
        $ciphertext = substr($message, $nonceSize);
        $text= openssl_decrypt($ciphertext, self::METHOD, $key, OPENSSL_RAW_DATA, $nonce);
        return $text;
    }
}


print '<hr><pre>';
$message = '---';
print "Encoding text: ".$message."\n";
$key = 'bBaghjg';
print "Key text: ".$key."\n";

$encrypted = UnsafeCrypto::encrypt($message, $key);
print "Encrypted data: ".$encrypted."\n";

$decrypted = UnsafeCrypto::decrypt($encrypted, $key);
print "Decrypted data: ".$decrypted."\n";



?>