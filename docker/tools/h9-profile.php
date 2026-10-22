<?php
/** Disposable clone MU instrumentation. Aggregate query counts/timing only, no SQL or data. */
defined('ABSPATH') || exit;
if(wp_get_environment_type()!=='local' || untrailingslashit(home_url())!=='http://localhost:8199' || !isset($_GET['h9_profile'])){return;}
defined('SAVEQUERIES') || define('SAVEQUERIES',true);
add_action('shutdown',static function(){global $wpdb;$duplicates=[];$seconds=0;$own=[];
file_put_contents('/tmp/h9-web-runtime.json',wp_json_encode(['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'apache'=>function_exists('apache_get_version')?apache_get_version():null]));
foreach($wpdb->queries as $q){$hash=hash('sha256',$q[0]);$duplicates[$hash]=($duplicates[$hash]??0)+1;$seconds+=$q[1];if(preg_match('/commerce_base_[a-z_]+|CommerceCore\\\\[A-Za-z_]+/',$q[2],$m)){$own[$m[0]]=($own[$m[0]]??0)+1;}}
$name=is_front_page()?'home':(function_exists('is_shop')&&is_shop()?'catalog':(function_exists('is_product')&&is_product()?'product':'legal'));
file_put_contents('/tmp/h9-query-profile.jsonl',wp_json_encode(['page'=>$name,'queries_after_mu'=>count($wpdb->queries),'sql_ms'=>round($seconds*1000,2),'exact_repeated_queries'=>array_sum(array_map(static fn($n)=>max(0,$n-1),$duplicates)),'own_callers'=>$own])."\n",FILE_APPEND);
});
