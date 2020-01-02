<?php
namespace doq;

class Cacher
{
    public static function create(&$cacheParams)
    {
        $cacheType=$cacheParams['#type'];
        switch ($cacheType) {
      case 'serialfile':
        return new SerialFileCacher($cacheParams);
      case 'jsonfile':
        return new JSONFileCacher($cacheParams);
      case 'memcache':
        return new MemcacheCacher($cacheParams);
      default:
         trigger_error(\doq\t('Unknown cacher type [%s]', $cacheType), E_USER_ERROR);
         return false;
      end;
    }
    }
}

class SerialFileCacher
{
    public $cacheFolder;
    public $filePrefix;
    public $fileSuffix;
  
    public function __construct(&$cacheParams)
    {
        $s=$cacheParams['#targetFolder'];
        if (!$s) {
            trigger_error(\doq\t('Undefined parameter #targetFolder in cache config! Use "dataplans"'), E_USER_ERROR);
            $s='dataplans';
        }
    
        $this->cacheFolder=$GLOBALS['doq']['env']['#cachesPath'].'/'.$s;
        $this->filePrefix=(isset($cacheParams['#filePrefix'])?$cacheParams['#filePrefix']:'');
        $this->fileSuffix=(isset($cacheParams['#fileSuffix'])?$cacheParams['#fileSuffix']:'.txt');
    
        $tryUseAny=false; # try to use any folder for cache
    
        if (!is_dir($this->cacheFolder)) {
            if (isset($cacheParams['#forceCreateFolder'])) {
                if (mkdir($this->cacheFolder, 0777, true)===false) {
                    trigger_error(\doq\t('Unable to create cache folder [%s]. Use local or temporary instead'), E_USER_WARNING);
                    $tryUseAny=true;
                }
            } else {
                trigger_error(\doq\t('Cache folder [%s] not found'), E_USER_WARNING);
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
        trigger_error("Uses cachefolder : ".$this->cacheFolder,E_USER_NOTICE);
    }
    public function get($mustHaveTime, $objectId)
    {
        $fileName=$this->cacheFolder.'/'.$this->filePrefix.$objectId.$this->fileSuffix;
        if (file_exists($fileName) && (filemtime($fileName)===$mustHaveTime)) {
            $data=unserialize(file_get_contents($fileName));
            return [true,&$data];
        } else {
            return [false,null];
        }
    }
  
    public function put($setTime, $objectId, &$data)
    {
        $fileName=$this->cacheFolder.'/'.$this->filePrefix.$objectId.$this->fileSuffix;
        file_put_contents($fileName, serialize($data));
        touch($fileName, $setTime);
    }
}
