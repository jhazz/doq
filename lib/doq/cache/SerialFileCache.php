<?php
namespace doq\cache;

class SerialFileCache extends \doq\Cache
{
    public $cacheFolder;
    public $filePrefix;
    public $fileSuffix;
  
    /**
     * 
     * #filePrefix
     * #fileSuffix
     * #forceCreateFolder
     */
    public function __construct(&$cacheParams)
    {
        $s=$cacheParams['#targetFolder'];
        if (!$s) {
            trigger_error(\doq\tr('doq','Undefined parameter #targetFolder in cache config'), E_USER_ERROR);
            $s='default';
        }
    
        $this->cacheFolder=$GLOBALS['doq']['env']['#cachesPath'].'/'.$s;
        $this->filePrefix=(isset($cacheParams['#filePrefix'])?$cacheParams['#filePrefix']:'');
        $this->fileSuffix=(isset($cacheParams['#fileSuffix'])?$cacheParams['#fileSuffix']:'.txt');
    
        /** @var bool try to use any folder for cache */
        $tryUseAny=false; 
    
        if (!is_dir($this->cacheFolder)) {
            if (isset($cacheParams['#forceCreateFolder'])) {
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
        \doq\Logger::debug('cache','Uses cachefolder "'.$this->cacheFolder.'"', __FILE__);
    }

    public function get($prevModifyTime, $key)
    {
        $fileName=$this->cacheFolder.'/'.$this->filePrefix.$key.$this->fileSuffix;
        if (file_exists($fileName) && (filemtime($fileName)===$prevModifyTime)) {
            $data=unserialize(file_get_contents($fileName));
            return [true,&$data];
        } else {
            return [false,null];
        }
    }
  
    public function put($setModifyTime, $key, &$data)
    {
        $fileName=$this->cacheFolder.'/'.$this->filePrefix.$key.$this->fileSuffix;
        file_put_contents($fileName, serialize($data));
        touch($fileName, $setModifyTime);
    }
}
?>