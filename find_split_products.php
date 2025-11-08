<?php
use Drupal\Core\Database\Database;

function argval($name, $default = null){
  foreach ($_SERVER['argv'] as $a) { if (strpos($a, "--$name=") === 0) return substr($a, strlen("--$name=")); }
  return $default;
}
function arghas($name){ return in_array("--$name", $_SERVER['argv'], true); }

$imagesField = argval('images-field','field_images');
$key         = argval('key','title');
$bundle      = argval('bundle',null);
$max         = (int) argval('max',200);
$csv         = arghas('csv');
$showAmb     = arghas('show-ambiguous');

$imgTable="commerce_product__{$imagesField}";
$connection=Database::getConnection();

if(!$connection->schema()->tableExists('commerce_product_field_data')){fwrite(STDERR,"Missing commerce_product_field_data\n");exit(1);}
if(!$connection->schema()->tableExists('commerce_product__variations')){fwrite(STDERR,"Missing commerce_product__variations\n");exit(1);}
if(!$connection->schema()->tableExists($imgTable)){fwrite(STDERR,"Warning: table {$imgTable} not found, treating images as 0\n");}

$cond=array(); $args=array();
if($bundle){ $cond[]="p.type = :bundle"; $args[':bundle']=$bundle; }

$sql = "
  SELECT p.product_id, p.title, p.type,
         COALESCE(v.c,0) AS num_variations,
         COALESCE(i.c,0) AS num_images,
         p.$key AS k
  FROM commerce_product_field_data p
  LEFT JOIN (SELECT entity_id, COUNT(*) c FROM commerce_product__variations GROUP BY entity_id) v ON v.entity_id=p.product_id
  LEFT JOIN (SELECT entity_id, COUNT(*) c FROM {$imgTable} GROUP BY entity_id) i ON i.entity_id=p.product_id
";
if($cond){ $sql .= " WHERE ".implode(" AND ", $cond); }
$sql .= " ORDER BY p.$key, p.product_id";

$rows=$connection->query($sql,$args)->fetchAllAssoc('product_id');
if(!$rows){ echo "No products found.\n"; exit(0); }

$groups=array();
foreach($rows as $r){
  $kval = (string)$r->k;
  if (!isset($groups[$kval])) $groups[$kval]=array();
  $groups[$kval][]=$r;
}

$pairs=array(); $amb=array();

foreach($groups as $kval=>$items){
  if(count($items)<2) continue;

  $keepers = array();
  $donors  = array();
  foreach ($items as $r) {
    $nv = (int)$r->num_variations;
    $ni = (int)$r->num_images;
    if ($nv>0 && $ni==0) $keepers[]=$r;
    if ($nv==0 && $ni>0) $donors[]=$r;
  }

  if(count($keepers)===1 && count($donors)===1){
    $k=$keepers[0]; $d=$donors[0];
    $pairs[] = array(
      'key'=>$kval,'bundle'=>(string)$k->type,'title'=>(string)$k->title,
      'keeper_id'=>(int)$k->product_id,'keeper_vars'=>(int)$k->num_variations,'keeper_imgs'=>(int)$k->num_images,
      'donor_id'=>(int)$d->product_id,'donor_vars'=>(int)$d->num_variations,'donor_imgs'=>(int)$d->num_images
    );
  } else {
    $example_pids = array();
    foreach ($items as $r){ $example_pids[] = (int)$r->product_id; }
    $amb[] = array('key'=>$kval,'counts'=>array('total'=>count($items),'keepers'=>count($keepers),'donors'=>count($donors)),'example_pids'=>$example_pids);
  }
}

if($csv){
  $out=fopen('php://output','w');
  fputcsv($out,array('key','title','bundle','keeper_id','keeper_vars','keeper_imgs','donor_id','donor_vars','donor_imgs'));
  $printed=0;
  foreach($pairs as $p){
    fputcsv($out,array($p['key'],$p['title'],$p['bundle'],$p['keeper_id'],$p['keeper_vars'],$p['keeper_imgs'],$p['donor_id'],$p['donor_vars'],$p['donor_imgs']));
    $printed++; if($max>0 && $printed>=$max) break;
  }
  fclose($out);
  if($showAmb && $amb) fwrite(STDERR,"Ambiguous groups: ".count($amb)."\n");
  exit(0);
}

function pad2($s,$w){ $s=(string)$s; $len=strlen($s); if($len>$w-1){ $s=substr($s,0,$w-1).'…'; $len=strlen($s);} return $s.str_repeat(' ', max(0,$w-$len)); }
$header=array('key'=>28,'bundle'=>10,'keeper_id'=>10,'k_vars'=>6,'k_imgs'=>6,'donor_id'=>10,'d_vars'=>6,'d_imgs'=>6,'title'=>40);

echo "== Discovery (images field: {$imagesField}, key: {$key}".($bundle?", bundle: {$bundle}":"").") ==\n";
printf("Pairs found: %d | Ambiguous groups: %d\n", count($pairs), count($amb));
echo pad2('key',$header['key']).pad2('bundle',$header['bundle']).pad2('keeper_id',$header['keeper_id']).pad2('k_vars',$header['k_vars']).pad2('k_imgs',$header['k_imgs']).pad2('donor_id',$header['donor_id']).pad2('d_vars',$header['d_vars']).pad2('d_imgs',$header['d_imgs']).'title'."\n";
echo str_repeat('-', 28+10+10+6+6+10+6+6+40)."\n";
$printed=0;
foreach($pairs as $p){
  echo pad2($p['key'],$header['key'])
     .pad2($p['bundle'],$header['bundle'])
     .pad2($p['keeper_id'],$header['keeper_id'])
     .pad2($p['keeper_vars'],$header['k_vars'])
     .pad2($p['keeper_imgs'],$header['k_imgs'])
     .pad2($p['donor_id'],$header['donor_id'])
     .pad2($p['donor_vars'],$header['d_vars'])
     .pad2($p['donor_imgs'],$header['d_imgs'])
     .substr($p['title'],0,200)."\n";
  $printed++; if($max>0 && $printed>=$max) break;
}
if($showAmb && $amb){
  echo "\n-- Ambiguous groups (not 1:1) --\n";
  foreach($amb as $a){
    echo "key='".$a['key']."' total=".$a['counts']['total']." keepers=".$a['counts']['keepers']." donors=".$a['counts']['donors']." pids=[".implode(',',array_slice($a['example_pids'],0,10)).(count($a['example_pids'])>10?'…':'')."]\n";
  }
}
echo "\nTip: CSV: add --csv > /tmp/split-products.csv\n";
