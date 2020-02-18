<?php
namespace doq;

abstract class Cache
{
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
     * @return array pair of [true,$newCacheObject] or [false,errorMessage]
     */
    abstract public function put($setModifyTime, $key, &$data);
    
    public static function create(&$cacheParams)
    {
        $cacheType=$cacheParams['#type'];
        switch ($cacheType) {
            case 'serialfile':
                return [new cache\SerialFileCache($cacheParams), null];
                /*
            case 'jsonfile':
                return [true,new JSONFileCache($cacheParams)];
            case 'memcache':
                return new MemoryСache($cacheParams);
                */
            default:
                $err=\doq\tr('doq', 'Unknown cache type "%s"', $cacheType);
                trigger_error($err, E_USER_ERROR);
                return [false, $err];
            end;
            }
    }


}

