<?php
require_once '../autorun.php';

function main()
{
    $schemaFile=$GLOBALS['doq']['env']['#commonPath'].'/schema.php';
    $schemaFileTime=filemtime($schemaFile);

    if (isset($GLOBALS['doq']['env']['@caches']['querys'])) {
        list($ok, $queryCache)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['querys']);
        if ($ok) {
            doq\data\View::setDefaultCache($queryCache);
        }
    }

    if (isset($GLOBALS['doq']['env']['@caches']['templates'])) {
        list($ok, $templatesCache)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['templates']);
        if ($ok) {
            doq\Template::setDefaultCache($templatesCache);
        }
    }
    doq\Template::setDefaultTemplatesPath($GLOBALS['doq']['env']['#templatesPath']);
    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);

    list($ok, $viewProducts)=doq\data\View::create(
        $GLOBALS['doq']['schema'],
        $GLOBALS['doq']['views']['Products'],
        'Products1');
    $viewProducts->prepare($schemaFileTime, true);
    doq\Logger::debugQuery($viewProducts->queryDefs, 'View products');

    $params=[];
    list($ok, $products)=$viewProducts->read($params, 'VIEW1');
    //print $products->dataset->dataToHTML();
   
    list($ok,$template)=\doq\Template::create();
    $template->load('page1');
    list($ok,$page1)=doq\Render::create();
    $page1->build($products, $template);
    print "<html><head><meta charset='utf-8'>'\n";
    if(count($page1->cssStyles)>0){
        print "<style>";
        foreach($page1->cssStyles as $styleSelector=>&$style){
            print '.'.$styleSelector.' {';
            foreach ($style as $styleParam=>&$styleValue) {
                print '    '.$styleParam.':'.$styleValue.";\n";
            }
            print "}\n";
        }
        print "</style>";
    }
    print "</head><body>";
    foreach ($page1->out as $i=>&$s) {
        print "$s\n";
    }

}
  
main();
?>
