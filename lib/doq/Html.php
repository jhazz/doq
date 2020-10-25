<?php
namespace doq;

class Html
{
    private static $env;
    public static $isInited;
    public static $isHtmlHeadInited;
    public static $isHeadInited;
    public static $isBodyInited;
    public static $metaArray;
    public static $title;
    public static $subTitle;
    public static $encoding;
    public static $headStrings;
    
    public static function init(&$env=null){
        if(isset(self::$isInited)){
            return;
        }
        
        if($env==null){
            $env=&$GLOBALS['doq']['env']['@html'];
        }
        self::$env=&$env;
        self::$isInited=true;
        
        $encoding='utf-8';
        if(isset($env['#encoding'])){
            $encoding=$env['#encoding'];
        }
        self::setMeta('charset',$encoding);
        
        if(isset($env['#title'])){
            self::$title=$env['#title'];
        }
    }
    
    
    /**
     * Add META parameter to HTML header.
     * Be careful, not html-safe. Function use no character escaping transformations
     * @param string $param parameter name
     * @param string $value parameter value
     */
    public static function setMeta($param,$value){
        if(!isset(self::$metaArray)){
            self::$metaArray=[];
        }
        self::$metaArray[$param]=$value;
    }
    /**
     * Add LINK tag to HTML header. 
     * Be careful, not html-safe. Function use no character escaping transformations
     * @param array $params key value pairs for LINK tag. 
     */
    public static function setLink($params){
        $a=['<link'];
        foreach($params as $k=>&$v){
            $a[]=$k.'="'.$v.'"';
        }
        $a[]='/>';
        self::addToHead(implode (' ',$a));
    }
    
    static function addToHead($s){
        if(!isset(self::$headStrings)){
            self::$headStrings=[];
        }
        self::$headStrings[]=$s;
    }

    public static function title($title){
        self::$title=$title;
    }

    public static function subTitle($subTitle){
        self::$subTitle=$subTitle;
    }
    
    private static function htmlHead(){
        if(!isset(self::$isInited)){
            self::init();
        }
        self::$isHtmlHeadInited=true;
        print '<!DOCTYPE HTML><html>';
    }
    
    static function head(){
        if(!isset(self::$isHtmlHeadInited)){
            self::htmlHead();
        }
        if(!isset(self::$isHeadInited)){
            print "\n<head>\n";
            print "<!-- \n  script_filename: {$_SERVER['SCRIPT_FILENAME']} \n \$env [#rootPath]: {$GLOBALS['doq']['env']['#rootPath']} \n server[php_self]: {$_SERVER['PHP_SELF']}-->\n\n";
            
            $c=(strpos($_SERVER['SCRIPT_FILENAME'],'\\') !== false)?'\\':'/';
            \doq\Logger::debug('Html','script_filename='.$_SERVER['SCRIPT_FILENAME']);
            $parts1=explode($c,$_SERVER['SCRIPT_FILENAME']);
            $cnt1=count($parts1);

            $root=$GLOBALS['doq']['env']['#rootPath'];
            \doq\Logger::debug('Html','Root path='.$root);
            $c=(strpos($root, '\\') !== false)?'\\':'/';
            $parts2=explode($c,$root);
            $cnt2=count($parts2);

            for($i=0;($i<$cnt1)&&($i<$cnt2);$i++){
                if($parts1[$i]!=$parts2[$i]){
                    break;
                }
            }
            $rootRelativeURL='';
            if($i==$cnt2){
                for($j=$cnt1-1;$j>$i;$j--){
                    $rootRelativeURL.='../';
                }
            } else {
                $err=\doq\tr('doq', 'Error detecting elements of the root path "%s"', $root);
                trigger_error($err, E_USER_ERROR);
            }
            $GLOBALS['doq']['#rootRelativeURL']=$rootRelativeURL;
            ?>
    <script src="<?=$rootRelativeURL?>www/doq/doq.js"></script>
    <script>
        doq.cfg.jsModulesRoot="<?=$GLOBALS['doq']['#rootRelativeURL']?>www"
        doq.cfg.APIRoot="<?=$GLOBALS['doq']['#rootRelativeURL']?>api"
        doq.cfg.CSRF="<?=\doq\Logger::getCSRF()?>"
    </script>
<?php
            self::$isHeadInited=true;
        }
    }
    
    public static function body(){
        if(self::$isBodyInited){
            return;
        }
        self::$isBodyInited=true;
        $nl="\n";
        self::head();
        if( (isset(self::$metaArray)) || (isset(self::$title)) || (isset(self::$headStrings)) ){
            if(isset(self::$title)){
                $title=self::$title;
                if(isset(self::$subTitle)){
                    $title.=' - '.self::$subTitle;
                }
                print '<title>'.$title.'</title>'.$nl;
            }
            if(isset(self::$metaArray)){
                foreach (self::$metaArray as $k=>$v){
                    print '<meta name="'.$k.'" content="'.$v.'"/>'.$nl;
                }
            }

            if(isset(self::$headStrings)){
                foreach (self::$headStrings as $v){
                    print $v.$nl;
                }
            }
        }
        print '</head>'.$nl;
        print '<body>'.$nl;
        
    }

}


?>