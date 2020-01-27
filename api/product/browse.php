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

    print "<hr>";

    $datanode=$products;
    
    
    // list($ok, $scopeStack)=\doq\data\ScopeStack::create($datanode, $datanode->name.':');
    // list($ok, $scope)=$scopeStack->open('');
    // do { 
        
    // } while (!$scope->seek(\doq\data\Scope::TO_NEXT));
    // $scopeStack->close();
    #$fields=[];
    #\doq\data\Dataset::collectFieldList($datanode->dataset->queryDefs, $fields);
    #print '<pre>';
    #\doq\Logger::debug('app',$fields);
    function extractNode(\doq\data\Datanode $node, $parentPath='', $level=0)
    {
        if($level>10){
            print 'Reach maximum level 10<br>';
            return;
        }
        
        
        if($node->type!==\doq\data\Datanode::NT_DATASET){
            throw new \Exception('Not a dataset!');
        }
        $parentPath.='/'.$node->name;
        $s='';
        for ($i=$level;$i>0;$i--) {
            $s.='&nbsp;&nbsp;&nbsp;';
        }
        print $s.'"#nodeName:"'.$node->name."\",\n";
        // IS EQUAL print $s.'"#detailName:"'.$node->dataset->queryDefs['#detailDatasetName']."\",\n";
        print $s.'"#dataset:"'.$node->dataset->queryDefs['@dataset']['#datasetName']."\",\n";
        $fieldsStr='';
        // $fieldDefs=$node->dataset->queryDefs['@dataset']['@fields'];
        $first=true;
        $fieldDefs=[];    //=$node->dataset->getColumns();
        \doq\data\Dataset::collectFieldDefs($node->dataset->queryDefs, $fieldDefs);
        foreach ($fieldDefs as $fieldPath=>&$fieldDef) {
            $type=$fieldDef['#type']; 
            $kind=$fieldDef['#kind']; 
            if (!$type) {
                    throw new \Exception('Field '.$fieldDef['#field'].' has no type! JSON could be invalid');
                }

            if (!$first) {
                $fieldsStr.=',  ';
            }
            $fieldsStr.="\n".'"'.$parentPath.'/'.$fieldDef['#field'].'":{';
            $fieldsStr.='"type":"'.$type.'"';
            $first=false;
            if(($kind=='aggregation')||($kind=='lookup')) { 
                $fieldsStr.=', "#refSchema":"'.$fieldDef['#refSchema'].'"';
                $fieldsStr.=', "#refDataset":"'.$fieldDef['#refDataset'].'"';
                $fieldsStr.=', "#kind":"'.$kind.'"';
                $fieldsStr.=', "#refType":"'.$fieldDef['#refType'].'"';
            }
            $fieldsStr.='}';
        
        }
        print $s.'"@fields":['.$fieldsStr."],\n\n";
        print $s."\"@data\":[";
        
        foreach($node->dataset->tuples as $rowNo=>&$tuple){
            $rowStr='';
            
            foreach ($tuple as $tupleFieldNo=>&$value) {
                if ($rowStr) {
                    $rowStr.=', ';
                }   
                $rowStr.='"'.$value.'"';
            }
            print $s.'['.$rowStr."],\n";
        }
        print $s.']';
        print "\n\n";
        if(isset($node->childNodes)){
            foreach($node->childNodes as $childNodeName=>&$childNode){
                if($childNode->type==\doq\data\Datanode::NT_DATASET){
                    extractNode($childNode, $parentPath, $level+1);
                }
            }
        }
    }
    print "<pre>";
    extractNode($datanode);

    #\doq\Logger::debug('app',$datanode->childNodes);
}
  
main();
?>
