<?php
namespace doq\data;

class Connection {
    public static $cfgDataConnections;
    public static $dataConnections;
  
    public static function init(&$cfgDataConnections) {
      self::$dataConnections=[];
      self::$cfgDataConnections=&$cfgDataConnections;
    }
  
    public static function getDataConnection($connectionName) {
      if(!isset(self::$cfgDataConnections[$connectionName])) {
        trigger_error(\doq\tr('doq','Unknown connection name %s',$connectionName),E_USER_ERROR);
        return [false,NULL];
      }
  
      if(isset(self::$dataConnections[$connectionName])) {
        return [true,&self::$dataConnections[$connectionName]];
      } else {
       $cfgConnection=&self::$cfgDataConnections[$connectionName];
       $providerName=$cfgConnection['#provider'];
  
       switch($providerName) {
         case 'mysql':
            $connection=new \doq\data\mysql\Connection($connectionName,$cfgConnection);
            self::$dataConnections[$connectionName]=&$connection;
            return [true,&$connection];
            break;
         default:
           trigger_error(\doq\t('Unknown data provider name %s',$providerName),E_USER_ERROR);
           return [false,NULL];
       }
      }
    }
  }

  ?>