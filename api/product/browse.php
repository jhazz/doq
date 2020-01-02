<?php
require_once '../autorun.php';

function main() {
    $schemaFile=$GLOBALS['config']['commonPath'].'/schema.php';
    $schemaFileTime=filemtime($schemaFile);
    print "Время схемы: ".$schemaFileTime;
    /*
    doq\Session::init();
    print("USER ID IS ".doq\Session::$userId);
    if (doq\Session::$isDisabled) {
      print " YOU ARE DISABLED! ";
    }
    #doq\Logger::error(E_ERROR,'This is error!');
    #throw new Exception('Some Error Message');
    */
    print '<meta charset="utf-8">';
    require_once $schemaFile;

    $viewPlanCacher=doq\Cacher::create($GLOBALS['doq']['env']['@caches']['mysql1_dataplans']);
    doq\data\View::$defaultCacher=$viewPlanCacher;
    doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);
  
    if($viewPlanCacher===false) {
      return;
    }
    list($ok,$viewProducts)=doq\data\View::create($GLOBALS['doq']['model'],$GLOBALS['doq']['views']['Products'],$GLOBALS['doq']['env']['@dataConnections'],'Products');
  
    $viewProducts->prepare($schemaFileTime,true);
    doq\data\Scripter::dumpPlan($viewProducts->plan);

    $params=[];
    list($ok,$products)=$viewProducts->read($params,'products');
  
    $products->dataObject->dumpData();

    $template=doq\Template::create();
    #$template->setTemplatePath($GLOBALS['doq']['env']['#templatesPath']);
    $template->setTemplatePath($GLOBALS['config']['rootPath'].'/frontend/templates');
    $template->setCachePath($GLOBALS['config']['rootPath'].'/templates');
    if ($template->readTemplate('page1')){
      print '<meta http-equiv="content-type" content="text/html; charset=utf-8" /><pre>';
      $page1=doq\Render::create();
      $page1->build($products,$template);
      foreach ($page1->out as $i=>&$s) {
        print "$s\n";  
      }
    }
  
  }
  
  main();

?>