<?php
namespace doq\elements;
use \doq\data\Scope;

class DataGrid
{
    public static function begin($scopeStack, &$template, &$block, &$render)
    {
        $render->out[]='[It is a begin of DataGrid "'.$block['params']['id'].'" ]';
        $cnt=count($block['@']);
        $properties=[];
        $columns=[];
        $cellBlocks=[];

        if (!$cnt) {
            return false;
        }

        for ($blockNo=0;$blockNo<$cnt;$blockNo++) {
            $subBlock=&$block['@'][$blockNo];
            if (is_array($subBlock)) {
                if ($subBlock['tag']=='column') {
                    $columns[]=&$subBlock['params'];
                } elseif ($subBlock['tag']=='begin') {
                    if ($subBlock['params']['element']=='cell') {
                        if (isset($subBlock['params']['forPath'])) {
                            $cellBlocks[$subBlock['params']['forPath']]=&$subBlock;
                        }
                    }
                }
            }
        }

        $id=$block['params']['id'];
        if (isset($block['params']['path'])) {
            $path=$block['params']['path'];
        } else {
            $path='';
        }
        list($scope, $err)=$scopeStack->open($path);
        if ($err!==null) {
            return false;
        }
        $datanode=$scope->datanode;
        if ($datanode->type!==\doq\data\Datanode::NT_DATASET) {
            trigger_error(\doq\t('Wrong path to Dataset - %s is not a Dataset!', $scope->path));
        }

        $columnCount=count($columns);
        if ($scope->seek(Scope::TO_START)) {
            $render->out[]='Dataset is empty';
            $scopeStack->close();
            return true;
        }

        list($cssTable,$cssCell)=$render->addRenderStyles(
            [   'cssTable'=>'datagrid-tab',
                'cssCell'=>'datagrid-cell'
            ],
            $block['params'],
            ['datagrid-tab'=>['border-collapse'=>'collapse'],
            'datagrid-cell'=>[
                'border'=>'1px  solid black',
                'padding'=>'3px',
                'font-family'=>'arial,sans',
                'font-size'=>'9px'
                ]
            ]);
            
        
        $render->out[]='<table class="'.$cssTable.'"><tr>';
        for ($i=0;$i<$columnCount;$i++) {
            $render->out[]='<td bgcolor="#a0f0a0">'.$columns[$i]['path'].'</td>';
        }
        $render->out[]='</tr>';

        $i=0;
        $basePath=$scope->path;
        while (true) {
            $render->out[]='<tr>';
            for ($j=0;$j<$columnCount;$j++) {
                $cellPath=$columns[$j]['path'];
                $render->out[]='<td class="'.$cssCell.'">';
                $rowScope=$scopeStack->top;
                $key=$rowScope->curTupleKey;
                $rowScope->path=$basePath.'['.$key.']';
                list($cellScope, $err)=$scopeStack->open($cellPath);
                if ($err===null) {
                    if (isset($cellBlocks[$cellPath])) {
                        $render->fromTemplate($scopeStack, $template, $cellBlocks[$cellPath]);
                    } elseif (isset($cellBlocks['*'])) {
                        $render->fromTemplate($scopeStack, $template, $cellBlocks['*']);
                    } else {
                        $render->out[]=$cellScope->asString().'<br/><span style="font-size:10px;">'.$cellScope->path.'</span>';
                    }
                    $render->out[]='</td>';
                    $rowScope=$scopeStack->close();
                }
            }
            $render->out[]='</tr>';
            $i++;
            $scope=$scopeStack->top;
            if (($scope->seek(Scope::TO_NEXT)) || ($i>100)) {
                break;
            }
        }
        $render->out[]='</table>';
        $scopeStack->close();
        return true;
    }
}
