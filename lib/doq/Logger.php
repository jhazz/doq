<?php
namespace doq;

set_error_handler (
  function($errno, $errstr, $errfile, $errline)
  {
    switch($errno)
    {
      case E_NOTICE:
        Logger::info($errstr,$errfile,$errline);
      break;
      case E_PARSE:
        print "PARSE";
      break;
      default:
        Logger::error($errno,$errstr,$errfile,$errline);
    }
    return true;
  }, E_ALL | E_STRICT);

register_shutdown_function(
  function()
  {
    $error = @error_get_last();
    if(isset($error['type']))
    {
      Logger::error($error['type'],$error['message'],$error['file'],$error['line']);
    }
    Logger::dump();
  });

set_exception_handler(function($exception){
    Logger::error('Exception',$exception->getMessage(),$exception->getFile(),$exception->getLine());
    $stack=$exception->getTrace();
    $ss='';
    foreach($stack as $i=>&$stackItem){
            $s='';
            $ss.='&nbsp;&nbsp;';
            if(isset($stackItem['args'])){
                foreach ($stackItem as $name=>&$value) {
                    if ($s!='') {
                        $s.='';
                    }
                    $s.=$name.'='.$value;
                }
            }
            Logger::info('STACK:'.$ss.$stackItem['function'].'('.$s.')',$stackItem['file'],$stackItem['line']);
        }
        
    }
);
  
  
class Logger {
  public static $logArray;
  public static $doCollectInfo;
  public static $doCollectError;

  public static function init($doCollectInfo = true,$doCollectError = true)
  {
    self::$doCollectInfo = $doCollectInfo;
    self::$doCollectError= $doCollectError;
    self::$logArray      = array();
  }
  public static function info($data,$file = false,$line = false)
  {
    if(self::$doCollectInfo)
    {
      array_push(self::$logArray, array(0,$data,$file,$line));
    }
  }
  public static function error($name,$data,$file = false,$line = false)
  {
    if(self::$doCollectError)
    {
      array_push(self::$logArray, array(E_USER_ERROR,$data,$file,$line));
    }
  }
  public static function dump($to = false)
  {
    $s = '';
    foreach(self::$logArray as $i=>&$record)
    {
      switch($record[0])
      {
        case E_USER_ERROR: $typeName ='Doq error'; $bgColor="#f8e0e0"; $fw=""; break;
        case E_USER_NOTICE: $typeName ='Info'; $bgColor="#f8f8f0"; $fw=""; break;
        case E_NOTICE : $typeName = 'Note'; $bgColor="#f8f880"; $fw=""; break;
        case E_WARNING: $typeName = 'Warn'; $bgColor="#80f880"; $fw= ""; break;
        case 0: $typeName = ''; $bgColor="#bbbbbb"; $fw= ""; break;
        default:
        $typeName = 'Error '.$record[0]; $bgColor="#ffb0b0"; $fw="font-weight:bold;";
      }
      $text = $record[1];
      if(!is_scalar($text))
      {
        $text = var_export($text,true);
      }
      if($record[2] !== false)
      {
        $text .= ' -- '.$record[2];
      }
      if($record[3] !== false)
      {
        $text .= '@'.$record[3];
      }
      $s .= "<div style='text-align:left;margin:1px;padding:3px;font-family:arial,sans;font-size:12px;color:#0;background-color:$bgColor;$fw'>$typeName: $text</div>";
    }
    if(!$to) print '<div style="white-space: normal;padding:1px; background-color:#808080;">'.$s.'</div>';
  }
}

?>
