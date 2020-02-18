<?php
namespace doq;

class Template
{
    public $templateSource;
    public $templateSplitted;
    public $blocks;
    public $srcTemplateFilename;
    public $templatesPath;
    public $cache;
    public $isCacheable;
    private static $defaultCache;
    public static $defaultTemplatesPath;

    public static function setDefaultCache(&$defaultCache)
    {
        self::$defaultCache=&$defaultCache;
    }

    public static function setDefaultTemplatesPath($defaultTemplatesPath)
    {
        self::$defaultTemplatesPath=$defaultTemplatesPath;
    }
    public static function create($templatesPath=null, &$cache=null)
    {
        if ($templatesPath===null) {
            $templatesPath=self::$defaultTemplatesPath;
        }
        if (!is_dir($templatesPath)) {
            $res=[false,\doq\tr('doq', 'Templates path "%s" do not refers to a directory. Please check out environment or create this directory first', $templatesPath)];
            \trigger_error($res[1], E_USER_ERROR);
            return $res;
        }

        if ($cache!==null) {
            $newTemplate=new self($templatesPath, true, $cache);
        } else {
            $newTemplate=new self($templatesPath, false);
        }
        return [$newTemplate, null];
    }

    /**
     * @param string
     * @param boolean
     * @param \doq\Cache
     * 
     */
    public function __construct($templatesPath, $isCacheable, &$cache=null){
        $this->templatesPath=$templatesPath;
        $this->isCacheable=$isCacheable;
        $this->cache=&$cache;
    }

    public function load($from)
    {
        // TODO Allow subfolders for templates structure. For now, only flat list is allowed. Convert $from to any hash()
        $base=basename($from, 'html');
        $this->srcTemplateFilename=$this->templatesPath.'/'.$base.'.html';
        $key=\str_replace(['/','\\',' ','#',':'], '-', $base);

        if (!file_exists($this->srcTemplateFilename)) {
            trigger_error(\doq\tr('doq', 'Template file "%s" not found in folder "%s"', $this->srcTemplateFilename, $this->templatesPath), E_USER_ERROR);
            return false;
        } else {
            $timeSource=filemtime($this->srcTemplateFilename);
        }
        
        $err=null;
        $doLoadTemplate=true;
        if ($this->isCacheable) {
            list($cachedData, $err) = $this->cache->get($timeSource, $key);
            if ($err===null) {
                $this->rootBlock=unserialize($cachedData);
                \doq\Logger::debug('template', "Reuse cached template parser data from {$filenameParsed}", __FILE__);
                $doLoadTemplate=false;
            }
        }

        if ($doLoadTemplate) {
            try {
                $this->templateText=file_get_contents($this->srcTemplateFilename);
            } catch (Exception $e) {
                trigger_error(\doq\tr('doq', 'Template file "%s" is nod readable', $this->srcTemplateFilename), E_USER_ERROR);
                return false;
            }
            $this->templateSplitted=preg_split('/\{\%(.*?)\%\}/', $this->templateText, -1, PREG_SPLIT_DELIM_CAPTURE);
            $this->rootBlock=array('tag'=>'root');
        }


        $this->parse(0, $this->rootBlock);
        if ($this->isCacheable) {
            $this->cache->put($timeSource, $key, $this->rootBlock);
        }
        
        return $this;
    }

    private function parse($srcBlockNo=0, &$parentBlock)
    {
        $cnt=count($this->templateSplitted);
        for ($blockNo=$srcBlockNo; $blockNo<$cnt; $blockNo++) {
            $block=&$this->templateSplitted[$blockNo];
            if (!($blockNo & 1)) {
                # текстовый блок просто добавляется
                $s=trim($block);
                #if($parentBlock['tag']=='fragment'){
                #  print 'adding '.$s;
                #}
                if ($s!='') {
                    if (!isset($parentBlock['@'])) {
                        $parentBlock['@']=[];
                    }
                    array_push($parentBlock['@'], $s);
                }
            } else {
                $arr=array();
                $strBlock=$block;
                $count=preg_match_all('(((\w*?)\s*=\s*(["\'])(.*?)\3\s*?)|\w+)', $block, $arr);
                $block=array('tag'=>strtolower($arr[0][0]));
                for ($j=1;$j<$count;$j++) {
                    $paramName=$arr[2][$j];
                    if (!$paramName) {
                        trigger_error(\doq\tr('doq','Template error in "%s" at tag "%s"', $this->filename, $strBlock), E_USER_ERROR);
                        return -1;
                    }
                    $paramValue=$arr[4][$j];
                    $block['params'][$paramName]=$paramValue;
                }
                if (isset($block['tag'])) {
                    if (($block['tag']=='begin')||($block['tag']=='fragment')) {
                        $block['@']=array();
                        $newPos=$this->parse($blockNo+1, $block);
                        if ($newPos==-1) {
                            break;
                        } else {
                            $blockNo=$newPos;
                        }
                    }
                    if ($block['tag']=='end') {
                        return $blockNo;
                    }
                }
                if (!isset($parentBlock['@'])) {
                    $parentBlock['@']=array();
                }
                array_push($parentBlock['@'], $block);
            }
        }
        return $blockNo;
    }
}
