<?php
namespace doq;

class Render
{
    public $out;
    public $errors;
    public $cssStyles;
    public $jsIncludes;

    public static function create()
    {
        return [true, new Render()];
    }

    public function __construct()
    {
        $blockNo=0;
        $this->errors=[];
        $this->out=[];
        $this->cssStyles=[];
        $this->jsIncludes=[];
        $this->fragments=[];
    }

    public function addStyle($styleSelector, $style)
    {
        if (isset($this->cssStyles[$styleSelector])) {
            $this->cssStyles[$styleSelector]=\array_merge($this->cssStyles[$styleSelector], $style);
        } else {
            $this->cssStyles[$styleSelector]=$style;
        }
    }

    public function addRenderStyles($paramsDefaultSelectors, &$renderBlockParams, $styles)
    {
        $result=[];
        foreach($paramsDefaultSelectors as $paramName=>&$defaultSelectorName){
            if (isset($renderBlockParams[$paramName])) {
                $cssSelector=$renderBlockParams[$paramName];
            } else {
                $cssSelector=$defaultSelectorName;
            }
            
            if (isset($styles[$cssSelector])) {
                $style=$styles[$cssSelector];

                if (isset($this->cssStyles[$cssSelector])) {
                    $this->cssStyles[$cssSelector]=\array_merge($this->cssStyles[$cssSelector], $style);
                } else {
                    $this->cssStyles[$cssSelector]= $style;
                }
            }
            $result[]=$cssSelector;
        }
        return $result;
    }

    public function build(&$datanode, &$template)
    {
        list($ok, $scopeStack)=\doq\data\ScopeStack::create($datanode, $datanode->name.':');
        return $this->fromTemplate($scopeStack, $template, $template->rootBlock);
    }

    public function fromTemplate(&$scopeStack, &$template, &$block)
    {
        if (!isset($block['@'])) {
            return false;
        }
        $cnt=count($block['@']);
        $blockNo=0;
        while ($blockNo<$cnt) {
            $childBlock=&$block['@'][$blockNo];
            if (is_string($childBlock)) {
                $this->out[]=$childBlock;
            } else {
                $tagName=$childBlock['tag'];
                switch ($tagName) {
          case 'put':
          case 'begin':
            $elementName=$childBlock['params']['element'];
            $elementFile='doq/elements/'.$elementName.".php";
            $elementClassName='doq\\elements\\'.$elementName;
            if (!method_exists($elementClassName, $tagName)) {
                if (file_exists($elementFile)) {
                    require_once($elementFile);
                } else {
                    trigger_error(\doq\tr('doq', 'Error loading template element file %s', $elementFile), E_USER_ERROR);
                    return -1;
                }
                if (!method_exists($elementClassName, $tagName)) {
                    trigger_error(\doq\tr('doq', 'Template element defined in %s has does not contain defined public static method %s::%s', $elementFile, $elementClassName, $tagName), E_USER_ERROR);
                    return -1;
                }
            }
            if ($tagName=='begin') {
                $result=$elementClassName::begin($scopeStack, $template, $childBlock, $this);
            } elseif ($tagName=='put') {
                $result=$elementClassName::put($scopeStack, $template, $childBlock, $this);
            }
            break;
          case 'fragment':
            if (!isset($childBlock['params']['id'])) {
                trigger_error(\doq\t('tpl_err_frgm_tag', $elementFile), E_USER_ERROR);
            }
            $this->fragments[$childBlock['params']['id']]=&$childBlock;
            break;
          default:
            $s=$tagName;
            if (isset($childBlock['params'])) {
                foreach ($childBlock['params'] as $paramName=>$paramValue) {
                    $s.=" $paramName=$paramValue";
                }
            }
            $this->out[]="{{?$s}}";
        }
            }
            $blockNo++;
        }
        return true;
    }
}
