<?php
use Drupal\Core\Database\Database;
use Drupal\commerce_product\Entity\Product;
function argval(string $name,$default=null){foreach($_SERVER['argv'] as $a){if(strpos($a,"--$name=")===0)return substr($a,strlen("--$name="));}return $default;}
function arghas(string $name): bool {return in_array("--$name", $_SERVER['argv'], true);}

$connection=Database::getConnection();
$id=argval('id',null);
$keyValue=argval('key',null);
$all=arghas('all');
if(!$id && !$keyValue && !$all){echo "Usage: --id=123 | --key='Title' | --all\n";exit(1);}

$q=$connection->select('drd_merge_log','l')->fields('l');
if($id) $q->condition('id',(int)$id);
if($keyValue) $q->condition('key_value',$keyValue);
$logs=$q->execute()->fetchAll();
if(!$logs){echo "No matching log entries.\n";exit(0);}

foreach($logs as $log){
  $t=$connection->startTransaction();
  try{
    $keeper=Product::load((int)$log->keeper_id);
    $donor =Product::load((int)$log->donor_id);
    if(!$keeper||!$donor) throw new \RuntimeException('Missing keeper or donor.');
    $field=$log->images_field;
    if(!$keeper->hasField($field)||!$donor->hasField($field)) throw new \RuntimeException("Missing field {$field} on one product.");

    $restoreIds=array_filter(explode(',',(string)$log->moved_target_ids));
    if($restoreIds){
      $kItems=$keeper->get($field)->getValue();
      $kItems=array_values(array_filter($kItems,fn($item)=>!in_array((string)($item['target_id']??''),$restoreIds,true)));
      $keeper->set($field,$kItems);
      foreach($restoreIds as $tid){$donor->get($field)->appendItem(['target_id'=>(int)$tid]);}
    }
    $donor->set('status',1);
    $keeper->save();$donor->save();
    echo "Rolled back log #{$log->id} (keeper #{$log->keeper_id} ⇐ donor #{$log->donor_id})\n";
  }catch(\Throwable $e){
    echo "FAILED rollback log #{$log->id}: ".$e->getMessage()."\n";
    $t->rollBack();
  }
}
