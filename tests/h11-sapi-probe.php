<?php
/** Temporary H11 HTTP SAPI probe. Copy into the isolated lab webroot; delete after QA. Not distributed. */
require_once __DIR__.'/wp-load.php';
if (wp_get_environment_type()!=='local'||get_option('commerce_h11_installation')!=='h11-production-simulation'||!preg_match('/^localhost:818[45]$/D',$_SERVER['HTTP_HOST']??'')) { http_response_code(404); exit; }
nocache_headers(); header('Content-Type: application/json; charset=UTF-8'); header('X-Robots-Tag: noindex, nofollow, noarchive');
$bytes=static function($setting){$value=trim(ini_get($setting));$n=(float)$value;$unit=strtolower(substr($value,-1));return (int)($n*match($unit){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1});};
$code=[ABSPATH.'wp-config.php',get_theme_file_path('functions.php'),WP_PLUGIN_DIR.'/commerce-core/commerce-core.php',WP_PLUGIN_DIR.'/woocommerce/woocommerce.php'];
$uploads=wp_upload_dir();
$writable=false;foreach($code as $file){$writable=$writable||!is_file($file)||is_writable($file);}
$data=['php_sapi'=>PHP_SAPI,'php_version'=>PHP_VERSION,'display_errors'=>filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN),'log_errors'=>filter_var(ini_get('log_errors'),FILTER_VALIDATE_BOOLEAN),'memory_limit_bytes'=>$bytes('memory_limit'),'upload_max_filesize_bytes'=>$bytes('upload_max_filesize'),'post_max_size_bytes'=>$bytes('post_max_size'),'max_execution_time'=>(int)ini_get('max_execution_time'),'opcache_enabled'=>filter_var(ini_get('opcache.enable'),FILTER_VALIDATE_BOOLEAN),'config_and_code_files_readonly'=>!$writable,'uploads_writable'=>is_dir($uploads['basedir'])&&is_writable($uploads['basedir']),'code_directories_readonly'=>!is_writable(get_stylesheet_directory())&&!is_writable(WP_PLUGIN_DIR.'/commerce-core')&&!is_writable(WP_PLUGIN_DIR.'/woocommerce'),'debug_display_off'=>!defined('WP_DEBUG_DISPLAY')||WP_DEBUG_DISPLAY===false,'file_editor_disabled'=>defined('DISALLOW_FILE_EDIT')&&DISALLOW_FILE_EDIT];
echo wp_json_encode($data,JSON_UNESCAPED_SLASHES)."\n";
