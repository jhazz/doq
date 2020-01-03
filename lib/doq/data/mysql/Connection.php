<?php
namespace doq\data\mysql;

class Connection extends \doq\data\Connection
{
	public $mysqli;

	public function __construct($connectionName, &$cfgConnection)
	{

		$dbCfg=&$cfgConnection['@params'];
		$host=$dbCfg['host'];
		$this->provider='mysql';
		$this->mysqli=new \Mysqli($host,$dbCfg['login'],$dbCfg['password'],$dbCfg['dbase'],$dbCfg['port']);
		if ($this->mysqli->connect_error) {
			trigger_error(\doq\t('dataset_error_connect_dbconnection',$connectionName),E_USER_ERROR);
			$this->isConnected=false;
			unset($this->mysqli);
		} else {
			$this->isConnected=true;
			$this->mysqli->set_charset('utf8');
		}
	}
}

Dataset::$useFetchAll=method_exists('\mysqli_result','fetch_all');

?>