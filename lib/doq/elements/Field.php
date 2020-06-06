<?php
namespace doq\elements;

class Field {
  public static function put($context,&$template,&$block,&$render){
    if(isset($block['params']['path'])) {
      $path=$block['params']['path'];
    } else $path='';

    if($path!=='') {
      $context->open($path);
    }
    $scope=$context->top;
    $render->out[]='<input type="text" value="'.$scope->value().'"><br/><span style="font-size:10px;">{'.$scope->path.'}</span>';
    if($path!=='') {
      $context->close();
    }

    return true;
  }
}