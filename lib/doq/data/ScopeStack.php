<?php
namespace doq\data;


class ScopeStack {
  public $stack;
  public $top;

  public static function create(DataNode $dataNode,$indexName='',$indexKey=NULL) {
    $scopeStack=new ScopeStack();
    $scope=$dataNode->dataObject->makeScope($dataNode,$indexName,$indexKey);
    $scopeStack->pushScope($scope);
    return [true,&$scopeStack];
  }

  private function pushDataNode(DataNode $dataNode, $newPath) {
    $scope=$dataNode->dataObject->makeScope($dataNode);
    $scope->path=$newPath;
    $this->top = $this->stack[] = $scope;
    return $scope;
  }

  private function pushScope(Scope $scope) {
    $this->top = $this->stack[] = $scope;
    return $scope;
  }

  public function push($addPath) {
    $datasetScope=NULL;
    $apath=explode('/',$addPath);
    $apathLen=count($apath);
    $scopeStackLen=count($this->stack);

    if (($apathLen>1) && ($apath[0]=='') && ($scopeStackLen)) {
      # TODO: При переходе на корневой узел '/' внутри стека скопов возвращается ссылка на корневой скоп, а не его копия
      # надо быть осторожным с использованием циклов внутри такого перехода. Вплоть до блокировки
      $rootScope=$this->stack[0];
      $this->pushScope($rootScope);
      return [true,$rootScope];
    }

    $scope=$this->top;
    if ($scope->dataNode->type==DataNode::NT_DATASET) {
      $datasetScope=$scope;
    }
    $path=$scope->path;
    if($addPath=='') {
      $this->stack[]=$scope;
      return [true,$scope];
    }


    for($posInPath=0;$posInPath<$apathLen;$posInPath++){
      $pathElementName=$apath[$posInPath];
      if($pathElementName==='') break;
      switch($scope->dataNode->type) {
        case DataNode::NT_DATASET:
          $datasetScope=$scope;
          if(!isset($scope->dataNode->childNodes[$pathElementName])) {
            trigger_error(\doq\t('Dataset object has no name [%s] in the namespace',$pathElementName),E_USER_ERROR);
            return [false,'Undefined name '.$pathElementName];
          }
          $childNode=$scope->dataNode->childNodes[$pathElementName];
          if ($pathElementName=='THE_PRODUCT_TYPE') {
            $z=1;
          }
          if($childNode->type==DataNode::NT_DATASET) {
            $scope=$scope->makeDetailScope($scope->path,$pathElementName);
            $scope->seek(Scope::SEEK_TO_START);
          } else {
            $nextDataNode=$scope->dataNode->childNodes[$pathElementName];
            $path.='/'.$pathElementName;
            $scope=$nextDataNode->dataObject->makeScope($nextDataNode,'',NULL,$datasetScope,$path);
          }
          break;
        case DataNode::NT_SUBCOLUMNS:
          if(!isset($scope->dataNode->childNodes[$pathElementName])) {
            trigger_error(\doq\t('DataObject %s has no column %s',$scope->dataNode->dataObject->id,$pathElementName),E_USER_ERROR);
            return [false,'Undefined name '.$pathElementName];
          }
          $nextDataNode=$scope->dataNode->childNodes[$pathElementName];
          $path.='/'.$pathElementName;
          if(!isset($datasetScope)) {
            trigger_error(\doq\t('Column %s has no dataset in previous scopes of path %s',$pathElementName,$path),E_USER_ERROR);
            return [false,'Subcolumn should bethe next scope after any dataset scope'];
          }
          $scope=$nextDataNode->dataObject->makeScope($nextDataNode,'',NULL,$datasetScope,$path);
          break;
        case DataNode::NT_COLUMN:
          trigger_error(\doq\t('Column %s cannot not have any subnames like %s',$scope->path,$pathElementName),E_USER_ERROR);
          return [false,'Try to get sub name of scalar value'];
          break;
      }
    }

    $this->pushScope($scope);
    return [true,$scope];
  }

  public function pop(){
    $stackLen=count($this->stack);
    if($stackLen) {
      unset($this->top);
      array_pop($this->stack);
      if($stackLen>1) {
        $this->top=$this->stack[$stackLen-2];
      }
    } else {
      $scope=NULL;
      trigger_error('Scope stack reach emptyness. Seems you have made unusable pop()',E_USER_ERROR);
    }
    return true;
  }


}
?>