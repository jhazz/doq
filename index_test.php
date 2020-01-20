<?php
require_once './doq/Logger.php';
require_once './doq/data/BaseProvider.php';
require_once './doq/data/View.php';
require_once './doq/Cacher.php';
require_once './doq/Template.php';
require_once './doq/i18n.php';
require_once './doq/Render.php';
require_once './doq/Session.php';

require_once './config.php';
require_once './lang/lang_ru.php';



function main() {
  //session_start();
  doq\Logger::init();
  doq\Logger::info('Лог ошибок:');
  $configMTime=filemtime('config.php');
  doq\Session::init();
  print("USER ID IS ".doq\Session::$userId);
  if (doq\Session::$isDisabled) {
    print " YOU ARE DISABLED! ";
  }
  #doq\Logger::error(E_ERROR,'This is error!');
  #throw new Exception('Some Error Message');
  print '<meta charset="utf-8">';


  $viewQueryCacher=doq\Cacher::create($GLOBALS['doq']['env']['@caches']['mysql1_querys']);
  doq\data\View::$defaultCacher=$viewQueryCacher;
  doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);

  if($viewQueryCacher===false) {
    return;
  }
  list($ok,$viewProducts)=doq\data\View::create($GLOBALS['doq']['model'],$GLOBALS['doq']['views']['Products'],$GLOBALS['doq']['env']['@dataConnections'],'Products');

  $viewProducts->prepare($configMTime,true);
  doq\data\Scripter::dumpQuery($viewProducts->query);

  $params=[];
  list($ok,$products)=$viewProducts->read($params,'products');

  print $products->dataset->dumpData();

  $template=doq\Template::create();
  $template->setTemplatePath($GLOBALS['doq']['env']['#templatesPath']);
  $template->setCachePath($GLOBALS['doq']['env']['#parsedTemplatesCachePath']);
  if ($template->load('page1')){
    print '<meta http-equiv="content-type" content="text/html; charset=utf-8" /><pre>';
    $page1=doq\Render::create();
    $page1->build($products,$template);
    foreach ($page1->out as $i=>&$s) {
      print "{$s}\n";  
    }
  }

}

main();

/*    print "<hr>";
    $path='[123]/abc/def/ghi[@id=12]/jk[@id=34]z[@no=12][@xxxo=34]/kl[3]';
    $arrayPath=explode('/', $path);

    $x=$arrayPath[1];
    preg_match_all('#(\w*?)(\[(.*?)])#',$x,$pathElement);
    print_r($pathElement);

    print "<hr>";

    $x=$arrayPath[4];
    preg_match_all('#(\w*?)(\[(.*?)])#',$x,$pathElement);
    print_r($pathElement);
*/


?>