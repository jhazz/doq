<?php
require_once 'autorun.php';
doq\Html::subTitle('Testing');
doq\Logger::placeConsole();
doq\Html::body();

if(isset($_POST['params'])){
    $params=$_POST['params'];
} else $params=<<<END
    {
    "@filter":
        [
            {"#columnId":"SKU", "#operand":"LIKE", "@values":["ОР-Д2-4.0"]}
        ],
    "#pageSize":10,
    "#pageNo":1
    }
END;
?>
<form method='post'>
<textarea cols=90 rows=15 name='params'><?=$params?></textarea>
<br>
<input type='submit' value='Отправить'>
</form>

<?php

$phpParams=json_decode($params,true);
list($viewProducts,$err)=doq\data\View::create('products');
list($datanode, $rowCount, $err)=$viewProducts->read($phpParams);
//['#columnId'=>'SKU', '#operand'=>'LIKE', '@values'=>['ОР-Д2-4.0']]);


print 'Total rows:'.$rowCount.'<br>';


list($products, $err)=\doq\data\ScopeStack::create($datanode);
do{
    list($sku,$title)=$products->extract(['SKU','TITLE']);
    print $products->top->rowNo.': SKU='.$sku.' TITLE='.$title.'<br>';
} while (!$products->next());




?>
