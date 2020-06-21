<?php
require_once 'autorun.php';
doq\Html::subTitle('Testing');
?><style>body,html {padding:0; margin:0; width:100%; height:100%;}
pre {font-family:'dejavu sans mono',consolas;font-size:8pt;height:100%; overflow:auto; width:100%; white-space:pre-wrap;}
</style><?php
doq\Logger::placeConsole();
doq\Html::body();


list($viewProducts,$err)=doq\data\View::create('products');
$roles=['store.editors'];
$viewProducts->prepareWriter($viewProducts->viewModifyTime,true, $roles);

?><table border=1 width='100%' height='100%'><tr><td width="50%"><pre>
<?php

$params=<<<END
    {
    "@filter":[{"#columnId":"SKU", "#operand":"LIKE", "@values":["ОР"]}],
    "#pageSize":10,"#pageNo":1
    }
END;
$phpParams=json_decode($params,true);
list($datanode, $rowCount, $err)=$viewProducts->read($phpParams);

print "<h1>Dump of Datanode->queryDefs</h1>";
print json_encode($viewProducts->queryDefs,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

print "<hr><h1>Datanode->toArray()</h1>";
print json_encode($datanode->toArray(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

print "<hr><h1>data\\Context walker over datanode useing next(), extractAllFields()</h1>";
list($products, $err)=\doq\data\Context::create($datanode);
do{
    $r=$products->extractAllFields();
    print json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    print '<br>';
} while (!$products->next());


?>
</pre></td>
<td width="50%"><pre>

<?php
print json_encode($viewProducts->writerDefs,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
?>
</pre></td></tr></table>
