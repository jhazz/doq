<?php
namespace doq\data;

class ScopeStack
{
    public $stack;
    public $top;

    public static function create(Datanode $datanode, $indexName='', $indexKey=null)
    {
        $scopeStack=new ScopeStack();
        $scope=$datanode->dataset->makeScope($datanode, $indexName, $indexKey);
        $scopeStack->pushScope($scope);
        return [true,$scopeStack];
    }

    private function pushDatanode(Datanode $datanode, $newPath)
    {
        $scope=$datanode->dataset->makeScope($datanode);
        $scope->path=$newPath;
        $this->top = $this->stack[] = $scope;
        return $scope;
    }

    private function pushScope(Scope $scope)
    {
        $this->top = $this->stack[] = $scope;
        return $scope;
    }

    public function push($addPath)
    {
        $datasetScope=null;
        $apath=explode('/', $addPath);
        $apathLen=count($apath);
        $scopeStackLen=count($this->stack);

        if (($apathLen>1) && ($apath[0]=='') && ($scopeStackLen)) {
            $rootScope=$this->stack[0];
            $this->pushScope($rootScope);
            return [true,$rootScope];
        }

        $scope=$this->top;
        if ($scope->datanode->type==Datanode::NT_DATASET) {
            $datasetScope=$scope;
        }
        $path=$scope->path;
        if ($addPath=='') {
            $this->stack[]=$scope;
            return [true,$scope];
        }


        for ($posInPath=0;$posInPath<$apathLen;$posInPath++) {
            $pathElementName=$apath[$posInPath];
            if ($pathElementName==='') {
                break;
            }
            switch ($scope->datanode->type) {
                case Datanode::NT_DATASET:
                    $datasetScope=$scope;
                    if (!isset($scope->datanode->childNodes[$pathElementName])) {
                        trigger_error(\doq\tr(
                            'doq',
                            'Dataset "%s" in the local scope has no node with name "%s"',
                            $scope->datanode->dataset->id,
                            $pathElementName,
                        ), E_USER_ERROR);
                        return [false,'Undefined name '.$pathElementName];
                    }
                    $childNode=$scope->datanode->childNodes[$pathElementName];

                    if ($childNode->type==Datanode::NT_DATASET) {
                        $scope=$scope->makeDetailScope($scope->path, $pathElementName);
                        $scope->seek(Scope::SEEK_TO_START);
                    } else {
                        $nextDatanode=$scope->datanode->childNodes[$pathElementName];
                        $path.='/'.$pathElementName;
                        $scope=$nextDatanode->dataset->makeScope($nextDatanode, '', null, $datasetScope, $path);
                    }
                break;
                case Datanode::NT_SUBCOLUMNS:
                    if (!isset($scope->datanode->childNodes[$pathElementName])) {
                        trigger_error(\doq\tr('doq', 'Dataset %s has no column %s', $scope->datanode->dataset->id, $pathElementName), E_USER_ERROR);
                        return [false,'Undefined name '.$pathElementName];
                    }
                    $nextDatanode=$scope->datanode->childNodes[$pathElementName];
                    $path.='/'.$pathElementName;
                    if (!isset($datasetScope)) {
                        $err=\doq\tr('doq', 'Column %s has no dataset in previous scopes of path %s. Subcolumn should be the next scope after any dataset scope', $pathElementName, $path);
                        trigger_error($err, E_USER_ERROR);
                        return [false,$err];
                    }
                    $scope=$nextDatanode->dataset->makeScope($nextDatanode, '', null, $datasetScope, $path);
                break;
                    case Datanode::NT_COLUMN:
                        $err=\doq\tr('doq', 'Column %s cannot not have any subnames like %s', $scope->path, $pathElementName);
                        trigger_error($err, E_USER_ERROR);
                    return [false,$err];
                break;
            }
        }

        $this->pushScope($scope);
        return [true,$scope];
    }

    public function pop()
    {
        $stackLen=count($this->stack);
        if ($stackLen) {
            unset($this->top);
            array_pop($this->stack);
            if ($stackLen>1) {
                $this->top=$this->stack[$stackLen-2];
            }
        } else {
            $scope=null;
            trigger_error(\doq\tr('doq', 'Scope stack reach emptyness. Seems like had called unusable pop from stack'), E_USER_ERROR);
        }
        return true;
    }
}
