<?php
/** Explicit additive media optimization; originals, products and stock are never rewritten. */
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || !in_array(untrailingslashit(home_url()),['http://localhost:8199','http://localhost:8080'],true)){exit(1);}
$mode=$args[0] ?? 'inspect';
if($mode==='hero') {
    $source=get_theme_file_path('assets/images/editorial-specimen.webp');$target=get_theme_file_path('assets/images/editorial-specimen-small.webp');
    if(!is_file($target)){$editor=wp_get_image_editor($source);if(is_wp_error($editor)){WP_CLI::error('Image editor unavailable');}$editor->set_quality(86);$editor->resize(480,null,false);$saved=$editor->save($target,'image/webp');if(is_wp_error($saved)){WP_CLI::error('Derivative generation failed');}}
    echo wp_json_encode(['source_bytes'=>filesize($source),'derivative_bytes'=>filesize($target),'source_unchanged'=>true,'generated_with'=>'WordPress image editor, WebP quality 86, 480px width'])."\n";return;
}
// Append only the new card size to actual catalog-photo attachment metadata.
$ids=[];foreach(wc_get_products(['limit'=>-1,'status'=>'publish','return'=>'objects']) as $product){foreach(array_merge([$product->get_image_id()],$product->get_gallery_image_ids()) as $id){if($id){$ids[$id]=true;}}}
if($mode==='cards-baseline') {
    if(untrailingslashit(home_url())!=='http://localhost:8199'){WP_CLI::error('Baseline reversal only in disposable clone');}
    foreach(array_keys($ids) as $id){$meta=wp_get_attachment_metadata($id);if(isset($meta['sizes']['commerce-card'])){unset($meta['sizes']['commerce-card']);wp_update_attachment_metadata($id,$meta);}}
    echo "Clone-only card metadata baseline restored; no image files deleted.\n";return;
}
if($mode==='cards') {
    $count=0;$bytes=0;
    foreach(array_keys($ids) as $id){$meta=wp_get_attachment_metadata($id);$file=get_attached_file($id);if(!$file || !$meta || ($meta['width']??0)<360 || ($meta['height']??0)<450){continue;}
        if(empty($meta['sizes']['commerce-card'])){$editor=wp_get_image_editor($file);if(is_wp_error($editor)){WP_CLI::error('Catalog image editor unavailable');}$editor->set_quality(86);$resized=$editor->resize(360,450,true);if(is_wp_error($resized)){WP_CLI::error('Resize failed');}$saved=$editor->save($editor->generate_filename());if(is_wp_error($saved)){WP_CLI::error('Derivative save failed');}
            $meta['sizes']['commerce-card']=['file'=>basename($saved['path']),'width'=>$saved['width'],'height'=>$saved['height'],'mime-type'=>$saved['mime-type'],'filesize'=>filesize($saved['path'])];wp_update_attachment_metadata($id,$meta);$count++;}
        $bytes+=$meta['sizes']['commerce-card']['filesize']??0;
    }
    echo wp_json_encode(['catalog_photo_attachments'=>count($ids),'new_sizes_added'=>$count,'total_card_derivative_bytes'=>$bytes,'originals_preserved'=>true,'only_metadata_added'=>'commerce-card'])."\n";
}
