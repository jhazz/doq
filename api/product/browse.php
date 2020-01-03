<?php
require_once '../autorun.php';

function main()
{
    $schemaFile=$GLOBALS['doq']['env']['#commonPath'].'/schema.php';
    $schemaFileTime=filemtime($schemaFile);
    print "Схема в файле {$schemaFile} была обновлена в ".$schemaFileTime;
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
    # require_once $schemaFile;

    /** @var doq\Cacher $viewPlanCacher Кэш плана создаваемого вида */
    $viewPlanCacher=doq\Cacher::create($GLOBALS['doq']['env']['@caches']['mysql1_dataplans']);
    doq\data\View::$defaultCacher=$viewPlanCacher;
    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);
  
    if ($viewPlanCacher===false) {
        return;
    }

    /** @var doq\data\View $viewProducts View to PRODUCTS*/
    $viewProducts=null;

    list($ok, $viewProducts)=doq\data\View::create(
        $GLOBALS['doq']['schema'],
        $GLOBALS['doq']['views']['Products'],
        'Products_View_12345'
    );
  
    $viewProducts->prepare($schemaFileTime, true); # Второй параметр означает форсированную перестройку кэша плана данных вне зависимости от даты самого кэша
    doq\data\Scripter::dumpPlan($viewProducts->plan);

    $params=[];
    list($ok, $products)=$viewProducts->read($params, 'products');
  
    $products->dataObject->dumpData();

    $template=doq\Template::create();
    $template->setTemplatePath($GLOBALS['doq']['env']['#templatesPath']);
    $template->setCachePath($GLOBALS['doq']['env']['#cachesPath'].'/templates');

    if ($template->readTemplate('page1')) {
        print '<meta http-equiv="content-type" content="text/html; charset=utf-8" /><pre>';
        $page1=doq\Render::create();
        $page1->build($products, $template);
        foreach ($page1->out as $i=>&$s) {
            print "$s\n";
        }
    }
}
  
main();
?>
