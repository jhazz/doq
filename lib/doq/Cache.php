<?php
namespace doq;

abstract class Cache
{
    public static $cacheConfig;
    public static $isInited;
    
    
    public static function init(&$cacheConfig=null){
        if($cacheConfig==null){
            $cacheConfig=&$GLOBALS['doq']['env']['@cache'];
        }
        if(!$cacheConfig){
            $err=\doq\tr('doq','Cache is not configured');
            throw new \Exception($err);
        }
        self::$cacheConfig=&$cacheConfig;
        self::$isInited=true;
    }
    
    /**
     * Build cache provider
     * @param array &$cacheCfg 
     * @return  [\doq\Cache, err]
     */
    public static function create($cacheTargetName=null)
    {
        if(!self::$isInited){
            self::init();
        }
        if($cacheTargetName==null){
            if(isset(self::$cacheConfig['#defaultTarget'])){
                $cacheTargetName=self::$cacheConfig['#defaultTarget'];
            }
        }
        if(!$cacheTargetName){
            $err=\doq\tr('doq','Error create Cache without target name');
            trigger_error($err, E_USER_ERROR);
            return [null, $err];
        }
        $cacheTargetConfig=&self::$cacheConfig['@targets'][$cacheTargetName];
        $cacheProvider=$cacheTargetConfig ['#provider'];
        
        switch ($cacheProvider) {
            case 'SerialFileCache':
                return [new cache\SerialFileCache($cacheTargetConfig), null];
                /*
            case 'jsonfile':
                return [true,new JSONFileCache($cacheTargetConfig)];
            case 'memcache':
                return new MemoryСache($cacheTargetConfig);
                */
            default:
                $err=\doq\tr('doq', 'Unknown cache provider "%s" . Check characters cases', $cacheType);
                trigger_error($err, E_USER_ERROR);
                return [null, $err];
            end;
            }
    }

    /** 
     * Gets data from cache by a key string
     * @param int $prevModifyTime the time of previous cache put
     * @param string $key a key indentifying data stored in cache
     * @return mixed
     */
    abstract public function get($prevModifyTime, $key);

    /**
     * @param int $setModifyTime time that equal to a source file/data modification time
     * @param string $key is a key identitying storing to the cache data
     * @param mixed &$data any data to store
     * @param int $ttl time to live timestamp (in seconds)
     * @return array pair of [true,$newCacheObject] or [false,errorMessage]
     */
    abstract public function put($setModifyTime, $key, &$data, $ttl=null);
    


}

