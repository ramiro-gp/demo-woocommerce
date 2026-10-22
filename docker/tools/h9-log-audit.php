<?php
/** Aggregate-only log inspection; execute with PHP CLI inside the local web container. */
if(PHP_SAPI!=='cli' || !in_array(getenv('LOCAL_SITE_URL'),['http://localhost:8080','http://localhost:8199'],true)){exit(1);}
$groups=['php_debug'=>is_file('/tmp/wordpress-debug.log')?['/tmp/wordpress-debug.log']:[],'woocommerce_files'=>glob('/var/www/html/wp-content/uploads/wc-logs/*.log')?:[]];$report=[];
foreach($groups as $name=>$files){$row=['files'=>count($files),'bytes'=>0,'email_occurrences'=>0,'non_test_email_occurrences'=>0,'credential_token_patterns'=>0,'order_key_patterns'=>0,'address_field_markers'=>0];
foreach($files as $file){$body=file_get_contents($file);$row['bytes']+=strlen($body);preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',$body,$emails);$row['email_occurrences']+=count($emails[0]);$row['non_test_email_occurrences']+=count(array_filter($emails[0],static fn($email)=>!preg_match('/\.test$/i',$email)));$row['credential_token_patterns']+=preg_match_all('/(?:APP_USR-|TEST-)[A-Za-z0-9\-]{25,}/',$body);$row['order_key_patterns']+=preg_match_all('/wc_order_[A-Za-z0-9]+/',$body);$row['address_field_markers']+=preg_match_all('/billing_address|shipping_address|address_1/',$body);}
$report[$name]=$row;}
echo json_encode(['scope'=>'Main local log files; aggregate pattern counts, not proof of every data category; no content or identifiers exported','logs'=>$report],JSON_PRETTY_PRINT)."\n";
