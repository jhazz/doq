<?php
require_once '../autorun.php';

function htmlRenderer()
{
    $schemaFile=$GLOBALS['doq']['env']['#commonPath'].'/schema.php';
    $schemaFileTime=filemtime($schemaFile);

    if (isset($GLOBALS['doq']['env']['@caches']['querys'])) {
        list($queryCache,$err)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['querys']);
        if ($err===null) {
            doq\data\View::setDefaultCache($queryCache);
        }
    }

    if (isset($GLOBALS['doq']['env']['@caches']['templates'])) {
        list($templatesCache, $err)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['templates']);
        if ($err===null) {
            doq\Template::setDefaultCache($templatesCache);
        }
    }
    doq\Template::setDefaultTemplatesPath($GLOBALS['doq']['env']['#templatesPath']);
    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);

    list($viewProducts,$err)=doq\data\View::create($GLOBALS['doq']['schema'],$GLOBALS['doq']['views']['Products'],'Products1');
    $viewProducts->prepare($schemaFileTime, true);
    doq\Logger::debugQuery($viewProducts->queryDefs, 'View products');

    $params=[];
    list($products, $err)=$viewProducts->read($params, 'VIEW1');
    //print $products->dataset->dataToHTML();
   
    list($template, $err)=\doq\Template::create();
    $template->load('page1');
    list($page1, $err)=doq\Render::create();
    $page1->build($products, $template);
    print "<html><head><meta charset='utf-8'>";
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

function jsonLoader($options){
    $schemaFile=$GLOBALS['doq']['env']['#commonPath'].'/schema.php';
    $schemaFileTime=filemtime($schemaFile);

    if (isset($GLOBALS['doq']['env']['@caches']['querys'])) {
        list($queryCache, $err)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['querys']);
        if ($err===null) {
            doq\data\View::setDefaultCache($queryCache);
        }
    }

    /*
    if (isset($GLOBALS['doq']['env']['@caches']['templates'])) {
        list($templatesCache, $err)=doq\Cache::create($GLOBALS['doq']['env']['@caches']['templates']);
        if ($err===null) {
            doq\Template::setDefaultCache($templatesCache);
        }
    }
    doq\Template::setDefaultTemplatesPath($GLOBALS['doq']['env']['#templatesPath']);
    */
    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);

    list($viewProducts,$err)=doq\data\View::create(
        $GLOBALS['doq']['schema'],
        $GLOBALS['doq']['views']['Products'],
        'Products1');
    $viewProducts->prepare($schemaFileTime, true);
    #doq\Logger::debugQuery($viewProducts->queryDefs, 'View products');

    if(isset($options['@params'])) {
        $params=$options['@params'];
    } else {
        $params=[];
    }
    $viewId='VIEW1';
    if(isset($options['#viewId'])){
        $viewId=$options['#viewId'];
    }
    list($products, $err)=$viewProducts->read($params, $viewId);


    function walkOverFields($currentPath, &$fieldDefs, &$result){
        $keyField=$fieldDefs['#keyField'];
        foreach ($fieldDefs['@fields'] as $fieldNo=>&$fieldDef) {
            $ref=isset($fieldDef['#ref'])?$fieldDef['#ref']:false; 
            $kind=false;
            if(isset($fieldDef['#kind'])){
                $kind = $f['#kind'] = $fieldDef['#kind'];
            }
            $f=['#type'=>$fieldDef['#type']];
            // if(isset($fieldDef['#refSchema'])){$f['#refSchema']=$fieldDef['#refSchema'];}
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
                // if(isset($fieldDef['#refDataset'])){$f['#refDataset'] = $fieldDef['#refDataset'];}
                // if(isset($fieldDef['#ref'])){$f['#ref'] = $fieldDef['#ref'];}
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
        if ($node->dataset->tuples!==null) {
            $rows=[];
            foreach ($node->dataset->tuples as $rowNo=>&$tuple) {
                $rows[]=$tuple;
            }
            $attrs['@tuples']=&$rows;
        }
        if(isset($node->childNodes)){
            foreach($node->childNodes as $childNodeName=>&$childNode){
                if($childNode->type==\doq\data\Datanode::NT_DATASET){
                    toPlainArray($childNode, $dstArray, $parentPath, $level-1);
                }
            }
        }
        return $dstArray;
    }
    $dstArray=[];
    toPlainArray($products, $dstArray);
    print json_encode($dstArray, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}

function showJSONParams(){
?><br><br>
    <script>

    function onRequestTabsRoute(params){
        switch(params.do){
            case 'showRequest':
                document.getElementById("layer_request").style.display="block"
                document.getElementById("layer_response").style.display="none"
                document.getElementById("m2_0").checked=true
                break
            case 'showResponse':
                document.getElementById("layer_request").style.display="none"
                document.getElementById("layer_response").style.display="block"
                document.getElementById("m2_1").checked=true
                break
        }
    }
    
    doq.require('doq.router',function(){
        doq.log('router added')
        doq.router.addRouteHandler('#requestTabs',onRequestTabsRoute)
        
    })
    
    function sendRequestForText(){
        location.href='#requestTabs?do=showResponse'
        document.getElementById("response_area").innerText="Please wait";
        var xhr=doq.postJSON('?a=json_demo1_post',document.getElementById("request_area").innerText,function(){
            document.getElementById("response_area").innerText=this.response;
        })
    }
    
    /*function postJSON(url,json,onload, responseType){
        if (!responseType){
            responseType='text'
        }
        let xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.responseType = responseType;
        xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8');            
        xhr.send(json);
        xhr.onload=onload;
        return xhr;
    }*/

    function makeTabs(menuName, tabs){
        var s='',i,d;
        for (i in tabs){
            d=tabs[i]
            s+='<input type="radio" id="'+menuName+'_'+i+'" name="'+menuName+'" '+((i==0)?'checked':'')+
            ' /><label onclick="location.href=\''+d.href+'\'" for="'+menuName+'_'+i+'">'+d.label+'</label>&nbsp;'
        }
        return s
    }



    </script>

    
<div class="menu-tabs">
<script>
document.write(makeTabs('m2',[
    {href:'#requestTabs?do=showRequest',label:'Request data form'}, 
    {href:'#requestTabs?do=showResponse',label:'Response received'}
] ));
</script>
</div>

<div id="layer_request" style="display:block" class="layer_form">
    <h4>This is a request parameters:</h4>
    <textarea cols="100" rows="20"  id="request_area">
{
    "#viewId":"VIEW1",
    "@params":{
        "@filter":
            [
                {"#columnId":"SKU", "#operand":"LIKE", "@values":["ОР-Д2-4.0"]}
            ],
        "#pageSize":10,
        "#pageNo":1
    }
}
    </textarea><br>
    <button onclick="sendRequestForText()">Execute and get text</button>
    <button onclick="sendRequestForJSON()">Execute and get JSON</button>
</div>
<div id="layer_response" style="display:none" class="layer_form">
    <h4>Response from server:</h4>
    <pre id="response_area" style='width:100%; border:solid #000000 1px; height:400px; overflow:auto'></pre>
</div>
    
<?php
}

function showTopMenu(){
    \doq\Logger::initJS();
    ?>

    <style>
        body{margin:0;padding:0; height:100%;}
        html{margin:0;padding:0; height:100%;}
        .menu-top{background:#111111; padding:10px; color:#8888ff;}
        .menu-top a{color:white;}
        .menu-tabs {background:#aaaaaa; padding:10px 0 5px 20;}
        .menu-tabs label {border-radius: 10px 10px 0 0; padding:5px; background:#555555; color:white;}
        .menu-tabs label:hover {color:#ff2222; cursor:pointer;}
        .menu-tabs input {display:none;}
        .menu-tabs input:checked + label {background:#ffffff; color:#0022aa;}
        .tree-list-item {background:#ffffff; }
        .tree-list-item:hover {background:#eeeeff; }
        .layer_form {padding:20px;}
    </style>
    <div class="menu-top">
    <a href="?a=json">JSON reader</a> | <a href="?a=html">HTML render</a> | <a href="?a=json_demo1">JSON request demo</a> |
    <a href="#logger?do=showPanel"'>Logger panel</a>
    </div>
<?php
}


$action='json_demo1';
if(isset($_GET['a'])){
    $action=$_GET['a'];
}

switch($action){
    case 'json': 
        showTopMenu();
        jsonLoader([]);
        break;
    case 'html':
        showTopMenu();
        htmlRenderer();
        break;
    case 'json_demo1_post': 
        $headers = getallheaders();
        if (stripos($headers["Content-type"],"application/json")!==false) {
            $s=file_get_contents("php://input");
            $r=json_decode($s, true) ?: [];
            jsonLoader($r);
        }
      break;
    case 'json_demo1': 
        showTopMenu();
        showJSONParams();
    break;
}

?>
