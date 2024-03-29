<?php
namespace doq\data;

class Connections
{
	private static $config;
	private static $items;
    private static $isInited;

	public static function init(&$config=null)
	{
		self::$items=[];
        if($config==null){
            $config=&$GLOBALS['doq']['env']['@dataConnections'];
        }
		self::$config=&$config;
        self::$isInited=true;
	}
//
//	public static function &getConfig(){
//		return self::$config;
//	}
	 
	public static function getConnection($connectionName)
	{
        if(self::$isInited==null){
            self::init();
        }
		if (!isset(self::$config[$connectionName])) {
            $err=\doq\tr('doq', 'Unknown connection name %s', $connectionName);
			trigger_error($err,E_USER_ERROR);
			return [false,$err];
		}

		if (isset(self::$items[$connectionName])) {
			return [&self::$items[$connectionName],null];
		} else {
			$cfgConnection=&self::$config[$connectionName];
			if (isset($cfgConnection['@environments'])) {
				$dataEnvironments=&$cfgConnection['@environments'];
				$currentEnvironment=getenv('DOQ_ENVIRONMENT');
				
				if (!!$currentEnvironment) {
					if (isset($dataEnvironments[$currentEnvironment])) {
						$cfgConnection=&$dataEnvironments[$currentEnvironment];
					} else {
						if (isset($dataEnvironments['*'])) {
							$cfgConnection=&$dataEnvironments['*'];
						} else {
                            $err=\doq\tr('doq', 'Environment variable DOQ_ENVIRONMENT has value "%s" that not found in configuration. You may use "*" as default connection configuration', $currentEnvironment);
							trigger_error($err,E_USER_ERROR);
							return [false,$err];
						}
					}
				} else {
					if (isset($dataEnvironments['*'])) {
						$cfgConnection=&$dataEnvironments['*'];
					} else {
                        $err=\doq\tr('doq', 'Environment variable DOQ_ENVIRONMENT is undefined and "*" environment is absent in connection configuration');
						trigger_error($err,E_USER_ERROR);
						return [false,$err];
					}
				}
			
			}
			$providerName=$cfgConnection['#provider'];

			switch ($providerName) {
				case 'mysql':
					$connection=new \doq\data\mysql\Connection($connectionName,$cfgConnection);
					self::$items[$connectionName]=&$connection;
					return [&$connection,null];
                default:
                    $err=\doq\t('Unknown data provider name %s', $providerName);
					trigger_error($err,E_USER_ERROR);
					return [false,$err];
			}
		}
	}
}

?>
