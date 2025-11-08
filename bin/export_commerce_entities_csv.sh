#!/usr/bin/env bash
set -euo pipefail
drush ev "
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
\$rows=[];
foreach (\Drupal::entityTypeManager()->getDefinitions() as \$id=>\$def){
  if (str_starts_with(\$id,'commerce_') || \$def->getProvider()==='profile'){
    \$rows[]=[ \$id, \$def->getProvider(), (string)\$def->getLabel(), \$def->getClass(),
      \$def->entityClassImplements(ContentEntityInterface::class)?'yes':'',
      \$def->entityClassImplements(ConfigEntityInterface::class)?'yes':'' ];
  }
}
usort(\$rows, fn(\$a,\$b)=>strcmp(\$a[0],\$b[0]));
\$h=fopen('php://temp','r+');
fputcsv(\$h,['entity_id','provider','label','class','content','config']);
foreach(\$rows as \$r){ fputcsv(\$h,\$r); }
rewind(\$h); \$csv=stream_get_contents(\$h); fclose(\$h);
\$ts=date('Ymd-His'); \$uri=\"public://commerce-entities-\$ts.csv\";
\$file=\Drupal::service('file.repository')->writeData(\$csv,\$uri, \Drupal\\Core\\File\\FileSystemInterface::EXISTS_REPLACE);
\$url=\Drupal::service('file_url_generator')->generateString(\$file->getFileUri());
print \"Saved CSV: \$url\\nFile URI: \".\$file->getFileUri().\"\\n\";
"
