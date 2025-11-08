#!/usr/bin/env bash
# Writes *.sh and *.php into the CURRENT DIRECTORY (project root)
set -euo pipefail
ROOT_DEFAULT="web"
HERE="$(pwd)"

# --- backup_db.sh ---
cat > "$HERE/backup_db.sh" <<'BASH'
#!/usr/bin/env bash
set -euo pipefail
ROOT="web"; OUTDIR=""
for a in "$@"; do
  case "$a" in
    --root=*) ROOT="${a#*=}";;
    --outdir=*) OUTDIR="${a#*=}";;
    --help|-h) echo "Usage: $0 [--root=web] [--outdir=web/db-backups]"; exit 0;;
    *) echo "Unknown option: $a" >&2; exit 1;;
  esac
done
: "${OUTDIR:=web/db-backups}"
mkdir -p "$OUTDIR"
TS=$(date +%F-%H%M)
# Let Drush add .gz (pass .sql)
drush --root="$ROOT" sql-dump --gzip --result-file="$OUTDIR/pre-merge-dupes-$TS.sql"
echo "Backup written to $OUTDIR/pre-merge-dupes-$TS.sql.gz"
BASH

# --- find_split_products.sh ---
cat > "$HERE/find_split_products.sh" <<BASH
#!/usr/bin/env bash
set -euo pipefail
ROOT="$ROOT_DEFAULT"
ARGS=()
for arg in "\$@"; do
  case "\$arg" in
    --root=*) ROOT="\${arg#*=}";;
    --images-field=*|--key=*|--bundle=*|--max=*) ARGS+=("\$arg");;
    --csv|--show-ambiguous) ARGS+=("\$arg");;
    --help|-h)
      echo "Usage: \$0 [--root=$ROOT_DEFAULT] [--images-field=field_images] [--key=title] [--bundle=default] [--max=200] [--csv] [--show-ambiguous]"
      exit 0;;
    *) echo "Unknown option: \$arg" >&2; exit 1;;
  esac
done
SELF_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="\$SELF_DIR/find_split_products.php"
drush --root="\$ROOT" scr "\$PHP_SCRIPT" "\${ARGS[@]}"
BASH

# --- merge_product_dupes.sh ---
cat > "$HERE/merge_product_dupes.sh" <<BASH
#!/usr/bin/env bash
set -euo pipefail
ROOT="$ROOT_DEFAULT"
ARGS=()
for arg in "\$@"; do
  case "\$arg" in
    --root=*) ROOT="\${arg#*=}";;
    --images-field=*|--key=*|--bundle=*) ARGS+=("\$arg");;
    --apply) ARGS+=("\$arg");;
    --help|-h)
      echo "Usage: \$0 [--root=$ROOT_DEFAULT] [--images-field=field_images] [--key=title] [--bundle=default] [--apply]"
      exit 0;;
    *) echo "Unknown option: \$arg" >&2; exit 1;;
  esac
done
SELF_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="\$SELF_DIR/merge_product_dupes.php"
drush --root="\$ROOT" scr "\$PHP_SCRIPT" "\${ARGS[@]}"
BASH

# --- rollback_merge_dupes.sh ---
cat > "$HERE/rollback_merge_dupes.sh" <<'BASH'
#!/usr/bin/env bash
set -euo pipefail
ROOT="web"
ARGS=()
for arg in "$@"; do
  case "$arg" in
    --root=*) ROOT="${arg#*=}";;
    --id=*|--key=*) ARGS+=("$arg");;
    --all) ARGS+=("$arg");;
    --help|-h) echo "Usage: $0 [--root=web] [--id=123] [--key='Exact Title'] [--all]"; exit 0;;
    *) echo "Unknown option: $arg" >&2; exit 1;;
  esac
done
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="$SELF_DIR/rollback_merge_dupes.php"
drush --root="$ROOT" scr "$PHP_SCRIPT" "${ARGS[@]}"
BASH

# --- find_split_products.php ---
cat > "$HERE/find_split_products.php" <<'PHP'
<?php
use Drupal\Core\Database\Database;
function argval(string $name, $default = null){foreach($_SERVER['argv'] as $a){if(strpos($a,"--$name=")===0)return substr($a,strlen("--$name="));}return $default;}
function arghas(string $name): bool {return in_array("--$name", $_SERVER['argv'], true);}

$imagesField = argval('images-field','field_images');
$key         = argval('key','title');
$bundle      = argval('bundle',null);
$max         = (int)(argval('max',200));
$csv         = arghas('csv');
$showAmb     = arghas('show-ambiguous');

$imgTable="commerce_product__{$imagesField}";
$connection=Database::getConnection();
if(!$connection->schema()->tableExists('commerce_product_field_data')){fwrite(STDERR,"Missing commerce_product_field_data\n");exit(1);}
if(!$connection->schema()->tableExists('commerce_product__variations')){fwrite(STDERR,"Missing commerce_product__variations\n");exit(1);}
if(!$connection->schema()->tableExists($imgTable)){fwrite(STDERR,"Warning: table {$imgTable} not found, treating images as 0\n");}

$cond=[];$args=[];
if($bundle){$cond[]="p.type = :bundle";$args[':bundle']=$bundle;}
$sql="
  SELECT p.product_id, p.title, p.type,
         COALESCE(v.c,0) AS num_variations,
         COALESCE(i.c,0) AS num_images,
         p.$key AS k
  FROM commerce_product_field_data p
  LEFT JOIN (SELECT entity_id, COUNT(*) c FROM commerce_product__variations GROUP BY entity_id) v ON v.entity_id=p.product_id
  LEFT JOIN (SELECT entity_id, COUNT(*) c FROM {$imgTable} GROUP BY entity_id) i ON i.entity_id=p.product_id
";
if($cond){$sql.=" WHERE ".implode(" AND ",$cond);}
$sql.=" ORDER BY p.$key, p.product_id";

$rows=$connection->query($sql,$args)->fetchAllAssoc('product_id');
if(!$rows){echo "No products found.\n";exit(0);}

$groups=[];
foreach($rows as $r){$groups[(string)$r->k][]=$r;}
$pairs=[];$amb=[];
foreach($groups as $kval=>$items){
  if(count($items)<2) continue;
  $keepers=array_values(array_filter($items,fn($r)=>((int)$r->num_variations>0)&&((int)$r->num_images==0)));
  $donors =array_values(array_filter($items,fn($r)=>((int)$r->num_variations==0)&&((int)$r->num_images>0)));
  if(count($keepers)===1 && count($donors)===1){
    $k=$keepers[0];$d=$donors[0];
    $pairs[]=['key'=>$kval,'bundle'=>(string)$k->type,'title'=>(string)$k->title,
      'keeper_id'=>(int)$k->product_id,'keeper_vars'=>(int)$k->num_variations,'keeper_imgs'=>(int)$k->num_images,
      'donor_id'=>(int)$d->product_id,'donor_vars'=>(int)$d->num_variations,'donor_imgs'=>(int)$d->num_images];
  }else{
    $amb[]=['key'=>$kval,'counts'=>['total'=>count($items),'keepers'=>count($keepers),'donors'=>count($donors)],'example_pids'=>array_map(fn($r)=>(int)$r->product_id,$items)];
  }
}
if($csv){
  $out=fopen('php://output','w');
  fputcsv($out,['key','title','bundle','keeper_id','keeper_vars','keeper_imgs','donor_id','donor_vars','donor_imgs']);
  $printed=0;foreach($pairs as $p){fputcsv($out,[$p['key'],$p['title'],$p['bundle'],$p['keeper_id'],$p['keeper_vars'],$p['keeper_imgs'],$p['donor_id'],$p['donor_vars'],$p['donor_imgs']]);$printed++;if($max>0&&$printed>=$max)break;}
  fclose($out);
  if($showAmb&&$amb)fwrite(STDERR,"Ambiguous groups: ".count($amb)."\n");
  exit(0);
}
function pad($s,$w){$s=(string)$s;return $s.str_repeat(' ',max(0,$w-strlen($s)));}
$header=['key'=>28,'bundle'=>10,'keeper_id'=>10,'k_vars'=>6,'k_imgs'=>6,'donor_id'=>10,'d_vars'=>6,'d_imgs'=>6,'title'=>40];
echo "== Discovery (images field: {$imagesField}, key: {$key}".($bundle?", bundle: {$bundle}":"").") ==\n";
printf("Pairs found: %d | Ambiguous groups: %d\n",count($pairs),count($amb));
echo pad('key',$header['key']).pad('bundle',$header['bundle']).pad('keeper_id',$header['keeper_id']).pad('k_vars',$header['k_vars']).pad('k_imgs',$header['k_imgs']).pad('donor_id',$header['donor_id']).pad('d_vars',$header['d_vars']).pad('d_imgs',$header['d_imgs']).'title'."\n";
echo str_repeat('-',28+10+10+6+6+10+6+6+40)."\n";
$printed=0;foreach($pairs as $p){
  echo pad(mb_strimwidth($p['key'],0,$header['key']-1,'…'),$header['key'])
     .pad($p['bundle'],$header['bundle'])
     .pad($p['keeper_id'],$header['keeper_id'])
     .pad($p['keeper_vars'],$header['k_vars'])
     .pad($p['keeper_imgs'],$header['k_imgs'])
     .pad($p['donor_id'],$header['donor_id'])
     .pad($p['donor_vars'],$header['d_vars'])
     .pad($p['donor_imgs'],$header['d_imgs'])
     .mb_strimwidth($p['title'],0,200,'')."\n";
  $printed++; if($max>0&&$printed>=$max)break;
}
if($showAmb&&$amb){
  echo "\n-- Ambiguous groups (not 1:1) --\n";
  foreach($amb as $a){
    echo "key='{$a['key']}' total={$a['counts']['total']} keepers={$a['counts']['keepers']} donors={$a['counts']['donors']} pids=[".implode(',',array_slice($a['example_pids'],0,10)).(count($a['example_pids'])>10?'…':'')."]\n";
  }
}
echo "\nTip: CSV: add --csv > /tmp/split-products.csv\n";
PHP

# --- merge_product_dupes.php ---
cat > "$HERE/merge_product_dupes.php" <<'PHP'
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
PHP

# --- rollback_merge_dupes.php ---
cat > "$HERE/rollback_merge_dupes.php" <<'PHP'
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
PHP

chmod +x "$HERE/backup_db.sh" "$HERE/find_split_products.sh" "$HERE/merge_product_dupes.sh" "$HERE/rollback_merge_dupes.sh"
echo "Created in $(pwd):"
ls -1 "$HERE"/{backup_db.sh,find_split_products.sh,merge_product_dupes.sh,rollback_merge_dupes.sh,find_split_products.php,merge_product_dupes.php,rollback_merge_dupes.php}
echo
echo "Usage examples:"
echo "  ./backup_db.sh --root=$ROOT_DEFAULT --outdir=$ROOT_DEFAULT/db-backups"
echo "  ./find_split_products.sh --root=$ROOT_DEFAULT --bundle=default"
echo "  ./merge_product_dupes.sh --root=$ROOT_DEFAULT --images-field=field_images      # dry-run"
echo "  ./merge_product_dupes.sh --root=$ROOT_DEFAULT --images-field=field_images --apply"
echo "  ./rollback_merge_dupes.sh --root=$ROOT_DEFAULT --id=123"
