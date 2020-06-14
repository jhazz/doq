<?php
namespace doq\cache;

class SerialFileCache extends \doq\Cache
{
    public $name;
    public $cacheFolder;
    public $filePrefix;
    public $fileSuffix;
    public $alwaysRebuild;
    /**
     * Cachecfg must have parameters:
     * #filePrefix
     * #fileSuffix
     * #forceCreateFolder
     */
    public function __construct(&$cacheCfg, $cacheTargetName)
    {
        $this->name=$cacheTargetName;
        $cachePath=self::$cacheConfig['@providers']['SerialFileCache']['#cachePath'];
        if(!$cachePath){
            trigger_error(\doq\tr('doq','Main cache path target has not been configured'), E_USER_ERROR);
            return;
        }
        
        $targetFolder=$cacheCfg['#targetFolder'];
        if (!$targetFolder) {
            trigger_error(\doq\tr('doq','Undefined parameter #targetFolder in cache config. Use default as a target folder'), E_USER_ERROR);
            $targetFolder='default';
        }
        $this->cacheFolder=$cachePath.'/'.$targetFolder;
        $this->filePrefix=(isset($cacheCfg['#filePrefix'])?$cacheCfg['#filePrefix']:'');
        $this->fileSuffix=(isset($cacheCfg['#fileSuffix'])?$cacheCfg['#fileSuffix']:'.txt');
        $this->alwaysRebuild=(isset($cacheCfg['#alwaysRebuild'])?intval($cacheCfg['#alwaysRebuild']):0);
        
        
        /** @var bool try to use any folder for cache */
        $tryUseAny=false; 
        
        if (!is_dir($this->cacheFolder)) {
            if (isset($cacheCfg['#forceCreateFolder'])) {
                if (mkdir($this->cacheFolder, 0777, true)===false) {
                    trigger_error(\doq\tr('doq','Unable to create cache folder "%s". Use local or temporary instead',$this->cacheFolder), E_USER_WARNING);
                    $tryUseAny=true;
                }
            } else {
                trigger_error(\doq\tr('doq','Cache folder "%s" not found', $this->cacheFolder), E_USER_WARNING);
                $tryUseAny=true;
            }
        }
    
        if ($tryUseAny) {
            if (function_exists('sys_get_temp_dir')) {
                $this->cacheFolder=sys_get_temp_dir();
            } else {
                $this->cacheFolder=getcwd();
            }
        }
        \doq\Logger::debug('doq','Uses cachefolder "'.$this->cacheFolder.'"', __FILE__);
    }

    public function get($prevModifyTime, $key)
    {
        if($this->alwaysRebuild) {
            return [null,'The cache "'.$this->name.'" should always be rebuilt as specified in the cache configuration'];
        }
        $fileName=$this->cacheFolder.'/'.$this->filePrefix.$key.$this->fileSuffix;
        if (file_exists($fileName) && (filemtime($fileName)===$prevModifyTime)) {
            $data=unserialize(file_get_contents($fileName));
            return [&$data,null];
        } else {
            return [null,'Cache missed'];
        }
    }
  
    public function put($setModifyTime, $key, &$data, $ttl=null)
    {
        $fileName=$this->cacheFolder.'/'.$this->filePrefix.$key.$this->fileSuffix;
        file_put_contents($fileName, serialize($data));
        touch($fileName, $setModifyTime);
    }
}
?>
