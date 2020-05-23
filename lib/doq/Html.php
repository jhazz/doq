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
    
    
    public static function setMeta($param,$value){
        if(!isset(self::$metaArray)){
            self::$metaArray=[];
        }
        self::$metaArray[$param]=$value;
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
        self::$isHtmlHeadInited=true;
        print '<!DOCTYPE HTML><html>';
    }
    
    static function head(){
        if(!isset(self::$isHtmlHeadInited)){
            self::htmlHead();
        }
        if(!isset(self::$isHeadInited)){
            print "\n<head>\n";
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