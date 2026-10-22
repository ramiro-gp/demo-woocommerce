<?php
/** Targeted local content adjustment following observed service inventory. */
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || untrailingslashit(home_url())!=='http://localhost:8080') { exit(1); }
$id=(int)get_option('wp_page_for_privacy_policy');
if(get_post_meta($id,'_commerce_legal_demo',true)!=='1') { WP_CLI::error('Only the marked demo page may be refined.'); }
$old='El titular debe identificar proveedor de hosting, correo, logística y sus contratos, ubicación de datos y posibles transferencias internacionales antes de publicar.';
$new='WordPress utiliza Gravatar para avatares en la administración: solicita imágenes a ese servicio con un hash del email y puede comunicar datos de conexión. El titular debe revisar su uso, proveedor de hosting, correo, logística, contratos, ubicación de datos y posibles transferencias internacionales antes de publicar.';
$content=get_post_field('post_content',$id);
if(str_contains($content,$old)) { wp_update_post(['ID'=>$id,'post_content'=>str_replace($old,$new,$content)]); }
elseif(!str_contains($content,$new)) { WP_CLI::error('Unexpected content; review merchant changes.'); }
$mpold='El plugin oficial de Mercado Pago está instalado, pero Checkout Pro permanece deshabilitado. Una futura habilitación requiere revisar su tratamiento de datos y sus condiciones.';
$mpnew='El plugin oficial de Mercado Pago está instalado y Checkout Pro permanece deshabilitado. Aun así, se observaron intentos de cargar SDK y otros recursos de Mercado Pago en el checkout; deshabilitar el medio de pago no demuestra ausencia de comunicaciones con ese proveedor. El titular debe revisar sus finalidades, datos, cookies y condiciones antes de publicar, además de los requisitos para habilitar cobros. El navegador de QA bloquea esas conexiones externas.';
$content=get_post_field('post_content',$id);
if(str_contains($content,$mpold)) { wp_update_post(['ID'=>$id,'post_content'=>str_replace($mpold,$mpnew,$content)]); }
elseif(!str_contains($content,$mpnew)) { WP_CLI::error('Unexpected Mercado Pago disclosure; review changes.'); }
echo "Observed Gravatar use documented in the marked demo privacy page.\n";
$pages=\CommerceCore\Legal::config()['pages'];$blocks=parse_blocks(get_post_field('post_content',wc_get_page_id('checkout')));$found=0;
$link=static fn($key,$label)=>'<a href="'.esc_url(get_permalink($pages[$key])).'">'.$label.'</a>';
$walk=function(&$items) use (&$walk,&$found,$link) { foreach($items as &$block) { if($block['blockName']==='woocommerce/checkout-terms-block') {
    if(!str_contains($block['attrs']['text'] ?? '','10 días corridos')) { WP_CLI::error('Unexpected terms content; preserve merchant changes.'); }
    $block['attrs']['checkbox']=true;
    $block['attrs']['text']='Leí y acepto '.$link('terms','los términos y condiciones').' y conozco '.$link('privacy','la política de privacidad').'. En compras a distancia, tenés un plazo legal general de 10 días corridos para comunicar tu arrepentimiento. Consultá el cómputo, devolución sin costo y excepciones en '.$link('withdrawal','Derecho de arrepentimiento').'.';$found++;
} if(!empty($block['innerBlocks'])) { $walk($block['innerBlocks']); } } };
$walk($blocks);if($found!==1) { WP_CLI::error('Expected one native terms block.'); }
// wp_update_post unslashes input: protect Gutenberg JSON's unicode/quote escapes.
wp_update_post(['ID'=>wc_get_page_id('checkout'),'post_content'=>wp_slash(serialize_blocks($blocks))]);
echo "Native terms links and checkbox saved with intact Gutenberg JSON.\n";
