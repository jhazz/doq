<?php
require_once '../autorun.php';

function main()
{
    $schemaFile=$GLOBALS['doq']['env']['#commonPath'].'/schema.php';
    $schemaFileTime=filemtime($schemaFile);
    print '<meta charset="utf-8">';

    if (isset($GLOBALS['doq']['env']['@caches']['querys'])) {
        list($ok, $queryCache)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['querys']);
        if ($ok) {
            doq\data\View::setDefaultCache($queryCache);
        }
    }

    if (isset($GLOBALS['doq']['env']['@caches']['templates'])) {
        list($ok, $templatesCache)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['templates']);
        if ($ok) {
            doq\TemplateParser::setDefaultCache($templatesCache);
        }
    }
    doq\TemplateParser::setDefaultTemplatesPath($GLOBALS['doq']['env']['#templatesPath']);
    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);

    list($ok, $viewProducts)=doq\data\View::create($GLOBALS['doq']['schema'],$GLOBALS['doq']['views']['Products'],'Products1');
    $viewProducts->prepare($schemaFileTime, true);
    doq\Logger::query($viewProducts->query, 'View products');

    $params=[];
    /** @var \doq\data\Datanode $products */
    list($ok, $products)=$viewProducts->read($params, 'view_products');
    print $products->dataset->dataToHTML();
   
    /** @var \doq\TemplateParser Template parser */
    $templateParser=null;
    list($ok,$templateParser)=\doq\TemplateParser::create();

    if ($templateParser->readTemplate('page1')) {
        print '<meta http-equiv="content-type" content="text/html; charset=utf-8" /><pre>';
        list($ok,$page1)=doq\Render::create();
        $page1->build($products, $templateParser);
        foreach ($page1->out as $i=>&$s) {
            print "$s\n";
        }
    }
}
  
main();
?>
