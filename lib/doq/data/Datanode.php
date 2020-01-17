<?php
namespace doq\data;


class Datanode{
  const NT_COLUMN='! Column';
  const NT_SUBCOLUMNS='! Subcolumns';
  const NT_DATASET='! Dataset';

  public $type;
  public $parameters;
  public $dataset;
  public $childNodes;

  public function __construct($type,$nodeId,$parendNode=NULL) {
    if($parendNode!==NULL) {
      $parendNode->childNodes[$nodeId]=$this;
    }
    $this->type=$type;
    if($this->type!==self::NT_COLUMN) {
      $this->childNodes=[];
    }
  }
}

?>
