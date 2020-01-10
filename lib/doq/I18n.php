<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq;

function t($s, $arg1=null, $arg2=null, $arg3=null, $arg4=null, $arg5=null, $arg6=null)
{
    return I18n::t($s, $arg1, $arg2, $arg3, $arg4, $arg5, $arg6);
}

function tr($category, $s, $arg1=null, $arg2=null, $arg3=null, $arg4=null, $arg5=null, $arg6=null)
{
    if (I18n::$selectedTargetLang!=null) {
        $r=I18n::$selectedTargetLang->getCategory($category);
        if ($r['success']===true) {
            $catData=$r['data'];
            if (isset($catData[$s])) {
                $s=$catData[$s];
            }
        }
    }
    return sprintf($s, $arg1, $arg2, $arg3, $arg4, $arg5, $arg6);
}

class I18n
{
    public $targetLangTag;
    public static $sourceLang;
    public static $langBasePath;
    public static $registry;
    public static $targets;
    public static $selectedTargetLang;
    private static $selectedCategory;
    private static $selectedTargetLangTag;

 
    /**
     * @param array $config  i18 config directives (#langPackFolder, #sourceLang)
     * @param string|null $langBasePath Full path to lang directory contains language countries directories
     * @return readyness
     */
    public static function init(&$env)
    {
        self::$sourceLang='en';
        self::$selectedCategory=false;
        if (isset($env['#sourceLang'])){
            self::$sourceLang=$env['#sourceLang'];
        }
        $langBasePath=$env['#langBasePath'];
        if(!is_dir($langBasePath)){
            return false;
        }

        self::$langBasePath=$langBasePath;
        self::$registry=[];
        self::$targets=[];
        self::$selectedTargetLangTag='';
        return true;
    }



    public static function target($targetLangTag){
        self::$selectedTargetLangTag=$targetLangTag;
        self::$selectedTargetLang=&self::getTarget($targetLangTag);
        return true;
    }
    /**
     * Loads destination language package
     * @param string $targetLangTag Language tag country-script-region
     */
    public static function &getTarget($targetLangTag)
    {
        if (isset(self::$targets[$targetLangTag])) {
            return self::$targets[$targetLangTag];
        } else {
            $r=new I18n($targetLangTag);
            self::$targets[$targetLangTag]=&$r;
            return $r;
        }
    }
    
    public function __construct($targetLangTag){
        $this->targetLangTag=$targetLangTag;
    }



    /** selects current category  */
    public static function category($selectingCategory)
    {
        if (self::$selectedTargetLang==null){
            return false;
        }
        $r=self::$selectedTargetLang->getCategory($selectingCategory);
        if($r['success']){
            self::$selectedCategory=&$r['data'];
        } else {
            self::$selectedCategory=false;
            return false;
        }
        return true;
    }
    
    /**
     * @param string $category application category
     * @return array Is load success
     */
    public function getCategory($category='app')
    {
        $targetLangTag=$this->targetLangTag;
        $basePath=self::$langBasePath;
    
        # [$country-$script-$region][$category][@data][data_loaded_from.php]
        if (isset(self::$registry[$targetLangTag][$category])) {
            $ref=&self::$registry[$targetLangTag][$category];
            if (isset($ref['@data'])) {
                return ['success'=>true, 'data'=>&$ref['@data'], 'from'=>'direct cache'];
            }
        }

        # сначала ищем есть ли уже загруженные версии
        # если была до этого попытка загрузки и файла точно нет, то уменьшаем детализацию языка
        $tagParts=explode('-', $targetLangTag);
        for ($i=count($tagParts); $i>0; $i--) {
            $partialTag=implode('-', $tagParts);
            if (!isset(self::$registry[$partialTag][$category]['#nofile'])) {
                if(isset(self::$registry[$partialTag][$category]['@data'])) {
                    $foundData=&self::$registry[$partialTag][$category]['@data'];
                    return ['success'=>true, 'data'=>&$foundData, 'from'=>'cache'];
                }
                $fileName=$basePath.'/'.$partialTag.'/'.$category.'.php';
                if (file_exists($fileName)) {
                    $foundData = require_once $fileName;
                    self::$registry[$partialTag][$category]['@data']=&$foundData;
                    return ['success'=>true, 'data'=>&$foundData, 'from'=>'included php'];
                } else {
                    self::$registry[$partialTag][$category]['#nofile']=true;
                }
            }
            array_pop($tagParts);
        }
        return ['success'=>false,'error'=>'Lang package for '.$targetLangTag.', category "'.$category.'" not found'];
    }



    public static function t($s, $arg1=null, $arg2=null, $arg3=null, $arg4=null, $arg5=null, $arg6=null)
    {
        if (!self::$selectedCategory){
            return sprintf($s, $arg1, $arg2, $arg3, $arg4, $arg5, $arg6);
        }
        if(isset(self::$selectedCategory[$s])){
            $s2=self::$selectedCategory[$s];
        }
        return sprintf($s2, $arg1, $arg2, $arg3, $arg4, $arg5, $arg6);
    }
}




?>

