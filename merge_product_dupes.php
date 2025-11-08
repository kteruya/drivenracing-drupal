<?php
use Drupal\Core\Database\Database;
use Drupal\commerce_product\Entity\Product;
function argval(string $name,$default=null){foreach($_SERVER['argv'] as $a){if(strpos($a,"--$name=")===0)return substr($a,strlen("--$name="));}return $default;}
function arghas(string $name): bool {return in_array("--$name", $_SERVER['argv'], true);}

$imagesField=argval('images-field','field_images');
$key=argval('key','title');
$bundle=argval('bundle',null);
$apply=arghas('apply');

$connection=Database::getConnection();
$imgTable="commerce_product__{$imagesField}";
if(!$connection->schema()->tableExists($imgTable)){fwrite(STDERR,"Image field table {$imgTable} not found.\n");exit(1);}

$connection->schema()->createTable('drd_merge_log',[
  'fields'=>[
    'id'=>['type'=>'serial','not null'=>TRUE],
    'merged_at'=>['type'=>'int','not null'=>TRUE],
    'key_value'=>['type'=>'varchar','length'=>512,'not null'=>TRUE],
    'keeper_id'=>['type'=>'int','not null'=>TRUE],
    'donor_id'=>['type'=>'int','not null'=>TRUE],
    'moved_target_ids'=>['type'=>'text','size'=>'big','not null'=>TRUE],
    'images_field'=>['type'=>'varchar','length'=>128,'not null'=>TRUE],
    'note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
  ],
],['if_not_exists'=>TRUE]);

$cond=[];$args=[];
if($bundle){$cond[]="p.type = :bundle";$args[':bundle']=$bundle;}
$sql="
  SELECT p.product_id, p.title, p.type, p.$key AS k,
         COALESCE(v.c,0) AS num_variations,
         COALESCE(i.c,0) AS num_images
  FROM commerce_product_field_data p
  LEFT JOIN (SELECT entity_id, COUNT(*) c FROM commerce_product__variations GROUP BY entity_id) v ON v.entity_id=p.product_id
  LEFT JOIN (SELECT entity_id, COUNT(*) c FROM {$imgTable} GROUP BY entity_id) i ON i.entity_id=p.product_id
";
if($cond) $sql.=" WHERE ".implode(" AND ",$cond);
$sql.=" ORDER BY p.$key, p.product_id";
$rows=$connection->query($sql,$args)->fetchAll();

$byKey=[];foreach($rows as $r){$byKey[(string)$r->k][]=$r;}
$planned=[];$groups=0;
foreach($byKey as $kval=>$items){
  if(count($items)<2) continue; $groups++;
  $keepers=array_values(array_filter($items,fn($r)=>((int)$r->num_variations>0)&&((int)$r->num_images==0)));
  $donors =array_values(array_filter($items,fn($r)=>((int)$r->num_variations==0)&&((int)$r->num_images>0)));
  if(count($keepers)===1 && count($donors)===1){$planned[]=['key'=>$kval,'keeper'=>$keepers[0],'donor'=>$donors[0]];}
}

echo "== Merge plan (images field: {$imagesField}, key: {$key}".($bundle?", bundle: {$bundle}":"").") ==\n";
echo "Duplicate groups seen: {$groups}\n";
echo "Resolvable pairs: ".count($planned)."\n";
foreach($planned as $p){
  echo "- {$p['key']}: keep #{$p['keeper']->product_id} (vars={$p['keeper']->num_variations}, imgs={$p['keeper']->num_images}) <= donor #{$p['donor']->product_id} (vars={$p['donor']->num_variations}, imgs={$p['donor']->num_images})\n";
}
if(!$apply){echo "(DRY RUN) Add --apply to execute.\n";exit(0);}

$time=time();
foreach($planned as $p){
  $t=$connection->startTransaction();
  try{
    $keeper=Product::load((int)$p['keeper']->product_id);
    $donor =Product::load((int)$p['donor']->product_id);
    if(!$keeper||!$donor) throw new \RuntimeException("Missing keeper or donor entity.");
    $field=$imagesField;
    if(!$keeper->hasField($field)||!$donor->hasField($field)) throw new \RuntimeException("One product misses field {$field}.");

    $moved=[];$donorItems=$donor->get($field)->getValue();
    foreach($donorItems as $item){$moved[]=$item['target_id']??null;$keeper->get($field)->appendItem($item);}
    $donor->set($field,[]);
    $donor->set('status',0);
    $keeper->save();$donor->save();

    $connection->insert('drd_merge_log')->fields([
      'merged_at'=>$time,'key_value'=>(string)$p['key'],'keeper_id'=>(int)$keeper->id(),'donor_id'=>(int)$donor->id(),
      'moved_target_ids'=>implode(',',array_filter($moved,fn($v)=>$v!==null)),
      'images_field'=>$field,'note'=>'moved images; donor unpublished (soft-delete)',
    ])->execute();

    echo "OK merged '{$p['key']}' donor #{$donor->id()} → keeper #{$keeper->id()} (moved ".count(array_filter($moved))." items)\n";
  }catch(\Throwable $e){
    echo "FAILED '{$p['key']}': ".$e->getMessage()."\n";
    $t->rollBack();
  }
}
echo "Done.\n";
