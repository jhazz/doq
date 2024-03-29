<?php
require_once 'autorun.php';


$action='json_demo1';
if(isset($_GET['a'])){
    $action=$_GET['a'];
}

switch($action){
    case 'json': 
        doq\Logger::placeConsole();
        showTopMenu();
        print '<pre>';
        jsonLoader([]);
        break;
    case 'html':
        doq\Logger::placeConsole();
        showTopMenu();
        htmlRenderer();
        break;
    case 'json_demo1': 
        doq\Logger::placeConsole();
        showTopMenu();
        showJSONParams();
    break;

    case 'json_demo1_post': # это не страница, а голый запрос на JSON
        $headers = getallheaders();
        if (stripos($headers["Content-type"],"application/json")!==false) {
            $s=file_get_contents("php://input");
            $r=json_decode($s, true) ?: [];
            jsonLoader($r);
        }
      break;
}




function htmlRenderer()
{

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
    list($template, $err)=\doq\Template::create();
    $template->load('page3');
    
    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);
    list($viewProducts,$err)=doq\data\View::create('products');
    

    $params=[];
    list($products, $err)=$viewProducts->read($params, 'VIEW1');
    doq\Logger::debugQueryDefs($viewProducts->queryDefs, 'View products',__FILE__, __LINE__);

    //print $products->dataset->dataToHTML();

    list($page1, $err)=doq\Render::create();
    $page1->build($products, $template);

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

    doq\data\Connections::init($GLOBALS['doq']['env']['@dataConnections']);
    //list($viewProducts,$err)=doq\data\View::create($GLOBALS['doq']['schema'],$GLOBALS['doq']['views']['Products'],'Products1');
    list($viewProducts,$err)=doq\data\View::create('products');
    $viewProducts->prepareQuery($schemaFileTime, true);
        
    doq\Logger::debugQueryDefs($viewProducts->queryDefs, 'View products');

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
    
    $dstArray=$products->toArray();
    print json_encode($dstArray, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}





function showJSONParams(){
?>
    <br><br>
    <script>
    
    doq.require('doq.router',function(){
        doq.log('router added')
        doq.router.addRouteHandler('#requestTabs',
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
        )
    })
    
    
    function sendRequestForText(){
        location.href='#requestTabs?do=showResponse'
        document.getElementById("response_area").innerText="Please wait";
        var xhr=doq.sendJSON('?a=json_demo1_post',document.getElementById("request_area").innerText,function(response, error){
            document.getElementById("response_area").innerText=response;
        },'text')
    }
    
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
    <a href="?a=json">JSON reader</a> | <a href="?a=html">HTML render</a> | <a href="?a=json_demo1">request demo</a> | <a href="test2_rparams.php">Short reading test</a> | <a href="test3_write.php">Write to datasource test</a>
    </div>
<?php
}




?>
