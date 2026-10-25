<?php
/** Read-only lab footprint and source comparison; no historical files or secrets. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local'
    || get_option('commerce_d1_installation')!=='d1-public-demo-lab'
    || !in_array(home_url(),['http://localhost:8190','http://localhost:8191'],true)) { exit(1); }
$source=wp_parse_url(home_url(),PHP_URL_PORT)===8190?'/opt/demo-catalog/h5-catalog.json':'/opt/client-fixture/content.json';
$fixture=json_decode(file_get_contents($source),true,512,JSON_THROW_ON_ERROR);$mismatches=[];
foreach ($fixture['products'] as $row) {
    if ($row['type']!=='simple') { continue; }
    $p=wc_get_product(wc_get_product_id_by_sku($row['sku']));
    if (!$p || (int)$p->get_stock_quantity()!==(int)$row['stock']) {
        $mismatches[]=['sku'=>$row['sku'],'expected'=>(int)$row['stock'],'actual'=>$p?$p->get_stock_quantity():null];
    }
}
global $wpdb;
$tables=$wpdb->get_results('SHOW TABLE STATUS',ARRAY_A);$db=0;
foreach ($tables as $table) { $db+=(int)$table['Data_length']+(int)$table['Index_length']; }
$uploads=wp_upload_dir();$bytes=0;$count=0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads['basedir'],FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) { $bytes+=$file->getSize();$count++; }
}
echo wp_json_encode(['origin'=>home_url(),'database_data_index_bytes'=>$db,'uploads_bytes'=>$bytes,
    'upload_files'=>$count,'simple_stock_source_mismatches'=>$mismatches,
    'php_version'=>PHP_VERSION,'local_only'=>true,'hosting_capacity_test'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
