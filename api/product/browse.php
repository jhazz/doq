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

function extractor(){
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


    function walkOverFields($currentPath, &$fieldDefs, &$result){
        $keyField=$fieldDefs['#keyField'];
        foreach ($fieldDefs['@fields'] as $fieldNo=>&$fieldDef) {
            $ref=isset($fieldDef['#ref'])?$fieldDef['#ref']:false; 
            $kind=false;
            if(isset($fieldDef['#kind'])){
                $kind = $f['#kind'] = $fieldDef['#kind'];
            }
            $f=['#type'=>$fieldDef['#type']];
            // if(isset($fieldDef['#refSchema'])){
            //     $f['#refSchema']=$fieldDef['#refSchema'];
            // }
            if(isset($fieldDef['#label'])){
                $f['#label'] = $fieldDef['#label'];
            }
            if($currentPath!='') {
                $path=$currentPath.'/'.$fieldDef['#field'];
            } else {
                $path=$fieldDef['#field'];
            }
            if($path==$keyField){
                $f['isKeyField']=1;
            }
            if(isset($fieldDef['#columnId'])){
                $f['#columnId']=$fieldDef['#columnId'];
            }
            if(isset($fieldDef['#tupleFieldNo'])) {
                $f['#tupleFieldNo']=intval($fieldDef['#tupleFieldNo']);
            }

            if(($kind=='lookup')||($kind=='aggregation')) {
                // if(isset($fieldDef['#refDataset'])){
                //     $f['#refDataset'] = $fieldDef['#refDataset'];
                // }
                // if(isset($fieldDef['#ref'])){
                //     $f['#ref'] = $fieldDef['#ref'];
                // }
                $reftype=false;
                if(isset($fieldDef['#refType'])) {
                    $reftype= $f['#refType']=$fieldDef['#refType'];
                }
                $result[$path] = $f;

                if ($reftype=='join') {
                    walkOverFields($path, $fieldDef['@dataset'], $result);
                }
            } else {
                $result[$path] = $f;
            }
        }
    }

    function toPlainArray(\doq\data\Datanode $node, &$dstArray, $parentPath='',  $level=10){
        if($level<0){
            return;
        }
        if($node->type!==\doq\data\Datanode::NT_DATASET){
            throw new \Exception('Not a dataset!');
        }
        $attrs=[
            '#nodeName'=>$node->name,
            '#dataSource'=>$node->dataset->queryDefs['#dataSource'],
            '#schema'=>$node->dataset->queryDefs['@dataset']['#schema'],
            '#dataset'=>$node->dataset->queryDefs['@dataset']['#datasetName'],
            '#keyField'=>$node->dataset->queryDefs['@dataset']['#keyField']
        ];
        $dstArray[$node->name]=&$attrs;
        walkOverFields($parentPath, $node->dataset->queryDefs['@dataset'], $r);
        $attrs['@fields']=$r;
        $rows=[];
        foreach($node->dataset->tuples as $rowNo=>&$tuple){
            $rows[]=$tuple;
        }
        $attrs['@tuples']=&$rows;
        if(isset($node->childNodes)){
            foreach($node->childNodes as $childNodeName=>&$childNode){
                if($childNode->type==\doq\data\Datanode::NT_DATASET){
                    toPlainArray($childNode, $dstArray, $parentPath, $level-1);
                }
            }
        }
        return $dstArray;
    }

    print "<pre>";
    $dstArray=[];
    toPlainArray($products, $dstArray);
    print json_encode($dstArray, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

}

extractor();

#main();
?>
