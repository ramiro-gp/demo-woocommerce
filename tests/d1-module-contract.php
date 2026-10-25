<?php
/** Fast module contract tests, no WordPress install or network. Run each case in its own PHP process. */
$case=$argv[1]??'off';
if(!in_array($case,['off','active','production','missing-marker','wrong-home','wrong-host','staging-metadata'],true)){fwrite(STDERR,"Unknown contract case.\n");exit(2);}
define('ABSPATH',__DIR__.'/');
if($case!=='off'){define('COMMERCE_DEMO_PUBLIC',true);define('COMMERCE_DEMO_ALLOWED_HOST','localhost');}
$hooks=[];$removed=[];
function add_action($name,$callback,$priority=10,$args=1){global $hooks;$hooks[]=['type'=>'action','name'=>$name,'callback'=>$callback,'priority'=>$priority,'args'=>$args];}
function add_filter($name,$callback,$priority=10,$args=1){global $hooks;$hooks[]=['type'=>'filter','name'=>$name,'callback'=>$callback,'priority'=>$priority,'args'=>$args];}
function remove_action($name,$callback,$priority=10){global $removed;$removed[]=$name;}
function wp_get_environment_type(){global $case;return $case==='production'?'production':($case==='staging-metadata'?'staging':'local');}
function get_option($name,$default=false){global $case;if($name==='commerce_core_legal'){return ['pages'=>['privacy'=>101,'terms'=>102]];}return $name==='commerce_demo_public_installation'&&$case!=='missing-marker'?'public-demo-v1':$default;}
function get_post_meta($id,$key,$single=false){return $key==='_commerce_public_demo_reviewed'&&in_array($id,[101,103],true)?'1':'';}
function absint($value){return abs((int)$value);}
function home_url(){global $case;return $case==='wrong-home'?'http://elsewhere.invalid':'http://localhost:8190';}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
$_SERVER['HTTP_HOST']=$case==='wrong-host'?'elsewhere.invalid':'localhost:8190';
require __DIR__.'/../deployment/demo-public/commerce-demo-public.php';
$checks=[];$check=static function($name,$value)use(&$checks){$checks[$name]=(bool)$value;};
if($case==='off'){
    $check('absent_flag_is_disabled',!\CommerceDemoPublic\enabled());
    $check('disabled_mode_registers_zero_hooks',count($hooks)===0&&count($removed)===0);
}else{
    $check('explicit_true_flag_enabled',\CommerceDemoPublic\enabled());
    $check('three_part_opt_in_and_exact_host',\CommerceDemoPublic\configuration_valid()===in_array($case,['active','staging-metadata'],true));
    $names=array_column($hooks,'name');
    foreach(['pre_wp_mail','pre_http_request','woocommerce_before_order_object_save','rest_pre_dispatch','wp_sitemaps_enabled'] as $name){$check('enforcement_hook_'.$name,in_array($name,$names,true));}
    if($case==='staging-metadata'){
        $metadata=array_values(array_filter($hooks,static fn($hook)=>$hook['name']==='get_post_metadata'))[0]['callback'];
        $check('configured_reviewed_page_marker_hidden_only_in_runtime',$metadata(null,101,'_commerce_legal_demo',true)==='');
        $check('configured_reviewed_multiple_values_hidden_only_in_runtime',$metadata(null,101,'_commerce_legal_demo',false)===[]);
        $check('configured_unreviewed_page_marker_preserved',$metadata(null,102,'_commerce_legal_demo',true)===null);
        $check('unconfigured_reviewed_page_marker_preserved',$metadata(null,103,'_commerce_legal_demo',true)===null);
        $check('unrelated_metadata_preserved',$metadata('original',101,'unrelated',true)==='original');
    }
    if($case==='active'){
        $allow=[['/wc/store/v1/products','GET'],['/wc/store/v1/products/123','GET'],['/wc/store/v1/cart','GET'],['/wc/store/v1/cart/add-item','POST'],['/wc/store/v1/cart/update-item','POST'],['/wc/store/v1/cart/remove-item','POST'],['/wp/v2/pages','GET']];
        foreach($allow as [$route,$method]){$check('allow_'.$method.'_'.$route,\CommerceDemoPublic\rest_allowed($route,$method));}
        $deny=[['/wc/store/v1/checkout','GET'],['/wc/store/v1/checkout','POST'],['/wc/store/v1/checkout/123','PUT'],['/wc/store/v1/order/123','GET'],['/wc/store/v1/cart/update-customer','POST'],['/wc/store/v1/cart/add-item','DELETE'],['/wc/v3/orders','POST'],['/wc/v3/customers','POST'],['/wp/v2/users','GET'],['/wp/v2/media','POST'],['/wc/store/v2/checkout','POST'],['/new-plugin/orders','POST']];
        foreach($deny as [$route,$method]){$check('deny_'.$method.'_'.$route,!\CommerceDemoPublic\rest_allowed($route,$method));}
        $safe=['requests'=>[['path'=>'/wc/store/v1/cart/add-item','method'=>'POST','body'=>['id'=>1,'quantity'=>1]]]];
        $check('safe_cart_batch_allowed',\CommerceDemoPublic\rest_allowed('/wc/store/v1/batch','POST',$safe));
        foreach([
            ['requests'=>[['path'=>'/wc/store/v1/checkout','method'=>'POST']]],
            ['requests'=>[['path'=>'/wc/store/v1/checkout','method'=>'GET']]],
            ['requests'=>[['path'=>'/wc/store/v1/cart/add-item?redirect=checkout','method'=>'POST']]],
            ['requests'=>[['path'=>'https://localhost/wc/store/v1/cart/add-item','method'=>'POST']]],
            ['requests'=>[['path'=>'/wc/store/v1/batch','method'=>'POST','body'=>$safe]]],
            ['requests'=>[['path'=>'/wc/store/v1/cart/add-item','method'=>'POST'],['path'=>'/wc/store/v1/checkout','method'=>'POST']]]
        ] as $i=>$body){$check('unsafe_batch_'.$i.'_blocked',!\CommerceDemoPublic\rest_allowed('/wc/store/v1/batch','POST',$body));}
    }
}
$report=['result'=>in_array(false,$checks,true)?'failed':'passed','case'=>$case,'module_sha256'=>hash_file('sha256',__DIR__.'/../deployment/demo-public/commerce-demo-public.php'),'checks'=>$checks,'network'=>false,'wordpress_integration'=>false];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if(in_array(false,$checks,true)){exit(1);}
