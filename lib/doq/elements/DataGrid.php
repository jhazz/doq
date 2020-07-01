<?php
namespace doq\elements;
use \doq\data\Scope;

class DataGrid
{
    public static function begin($context, &$template, &$block, &$render)
    {
        //$render->out[]='[It is a begin of DataGrid "'.$block['params']['id'].'" ]';
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
        list($scope, $err)=$context->open($path);
        if ($err!==null) {
            return false;
        }
        $datanode=$scope->datanode;
        if ($datanode->type!==\doq\data\Datanode::NT_DATASET) {
            trigger_error(\doq\t('Wrong path to Dataset - %s is not a Dataset!', $scope->path));
        }

        $columnCount=count($columns);
        if ($scope->seek(Scope::TO_START)) {
            $render->out[]='(empty)';
            $context->close();
            return true;
        }

        list($cssTable,$cssCell,$cssThead)=$render->addRenderStyles(
            [   'cssTable'=>'datagrid-tab',
                'cssCell'=>'datagrid-cell',
                'cssTableHead'=>'datagrid-thead'
            ],
            $block['params'],
            ['datagrid-tab'=>['border-collapse'=>'collapse'],
            'datagrid-cell'=>[
                'border'=>'1px solid #008800',
                'font-family'=>'arial,sans','font-size'=>'13x', 'padding'=>'3px'
                ],
            'datagrid-thead'=>[
                'border'=>'1px solid #008800','background'=>'#a0f0a0', 
                'font-family'=>'arial,sans','font-size'=>'10px', 'padding'=>'3px',]
            ]);
            
        
        $render->out[]='<table class="'.$cssTable.'"><tr>';
        for ($i=0;$i<$columnCount;$i++) {
            $c=(isset($columns[$i]['width']))?' width="'.$columns[$i]['width'].'" ' : '';
            $render->out[]='<th class="'.$cssThead.'"'.$c.'>'.$columns[$i]['path'].'</th>';
        }
        $render->out[]='</tr>';

        $i=0;
        $basePath=$scope->path;
        while (true) {
            $render->out[]='<tr valign="top">';
            for ($j=0;$j<$columnCount;$j++) {
                $cellPath=$columns[$j]['path'];
                $c=(isset($columns[$j]['width']))?' width="'.$columns[$j]['width'].'" ' : '';
                $render->out[]='<td class="'.$cssCell.'"'.$c.'>';
                $rowScope=$context->top;
                $key=$rowScope->curTupleKey;
                $rowScope->path=$basePath.'['.$key.']';
                list($cellScope, $err)=$context->open($cellPath);
                if ($err===null) {
                    if (isset($cellBlocks[$cellPath])) {
                        $render->fromTemplate($context, $template, $cellBlocks[$cellPath]);
                    } elseif (isset($cellBlocks['*'])) {
                        $render->fromTemplate($context, $template, $cellBlocks['*']);
                    } else {
                        $render->out[]=$cellScope->asString().'<br/><span style="font-size:8px; ">'.$cellScope->path.'</span>';
                    }
                    $render->out[]='</td>';
                    $rowScope=$context->close();
                }
            }
            $render->out[]='</tr>';
            $i++;
            $scope=$context->top;
            if (($scope->seek(Scope::TO_NEXT)) || ($i>100)) {
                break;
            }
        }
        $render->out[]='</table>';
        $context->close();
        return true;
    }
}
