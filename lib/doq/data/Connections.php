<?php
namespace doq\data;

class Connections
{
	private static $config;
	private static $items;

	public static function init(&$config)
	{
		self::$items=[];
		self::$config=&$config;
	}

	public static function &getConfig(){
		return self::$config;
	}
	 
	public static function getConnection($connectionName)
	{
		if (!isset(self::$config[$connectionName])) {
			trigger_error(\doq\tr('doq','Unknown connection name %s',$connectionName),E_USER_ERROR);
			return [false,NULL];
		}

		if (isset(self::$items[$connectionName])) {
			return [true,&self::$items[$connectionName]];
		} else {
			$cfgConnection=&self::$config[$connectionName];
			if (isset($cfgConnection['@environments'])) {
				$dataEnvironments=&$cfgConnection['@environments'];
				if (isset($_SERVER['DOQ_ENVIRONMENT'])) {
					$currentEnvironment=$_SERVER['DOQ_ENVIRONMENT'];
					if (isset($dataEnvironments[$currentEnvironment])) {
						$cfgConnection=&$dataEnvironments[$currentEnvironment];
					} else {
						if (isset($dataEnvironments['*'])) {
							$cfgConnection=&$dataEnvironments['*'];
						} else {
							trigger_error(\doq\tr('doq','Environment variable DOQ_ENVIRONMENT has value "%s" that not found in configuration. You may use "*" as default connection configuration',$currentEnvironment),E_USER_ERROR);
							return [false,NULL];
						}
					}
				} else {
					if (isset($dataEnvironments['*'])) {
						$cfgConnection=&$dataEnvironments['*'];
					} else {
						trigger_error(\doq\tr('doq','Environment variable DOQ_ENVIRONMENT is undefined and "*" environment is absent in connection configuration'),E_USER_ERROR);
						return [false,NULL];
					}
				}
			
			}
			$providerName=$cfgConnection['#provider'];

			switch ($providerName) {
				case 'mysql':
					$connection=new \doq\data\mysql\Connection($connectionName,$cfgConnection);
					self::$items[$connectionName]=&$connection;
					return [true,&$connection];
				default:
					trigger_error(\doq\t('Unknown data provider name %s',$providerName),E_USER_ERROR);
					return [false,NULL];
			}
		}
	}
}

?>