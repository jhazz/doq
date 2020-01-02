<?php
require_once '../doq/Logger.php';
require_once '../doq/data/BaseProvider.php';
require_once '../doq/data/View.php';
require_once '../doq/Cacher.php';
require_once '../doq/Template.php';
require_once '../doq/Translate.php';
require_once '../doq/Render.php';
require_once '../config.php';
require_once '../lang/lang_ru.php';




function main() {
  session_start();

  doq\Logger::init();
  $configMTime=filemtime('config.php');
  #doq\Logger::info('Лог ошибок:');
  #doq\Logger::error(E_ERROR,'This is error!');
  #throw new Exception('Some Error Message');
  print '<meta charset="utf-8">';

  if(!isset($_SESSION['accepted'])){
    $viewPlanCacher=doq\Cacher::create($GLOBALS['doq']['env']['@caches']['mysql1_dataplans']);
    doq\data\View::$defaultCacher=$viewPlanCacher;
    doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);

    if($viewPlanCacher===false) {
      return;
    }
    list($ok,$viewProducts)=doq\data\View::create($GLOBALS['doq']['model'],$GLOBALS['doq']['views']['Products'],$GLOBALS['doq']['env']['@dataConnections'],'Products');

    $viewProducts->prepare($configMTime,true);
    doq\data\Scripter::dumpPlan($viewProducts->plan);

    $params=[];
    list($ok,$products)=$viewProducts->read($params,'products');

    $products->dataObject->dumpData();

    $template=doq\Template::create();
    $template->setTemplatePath($GLOBALS['doq']['env']['#templatesPath']);
    $template->setCachePath($GLOBALS['doq']['env']['#parsedTemplatesCachePath']);
    if ($template->readTemplate('page1')){
      print '<meta http-equiv="content-type" content="text/html; charset=utf-8" /><pre>';
      $page1=doq\Render::create();
      $page1->build($products,$template);
      foreach ($page1->out as $i=>&$s) {print $s; print "\n";}
    }

  }
}

main();



?>