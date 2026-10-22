<?php
/** Ephemeral local audit MU-plugin; never install in a client site. */
defined('ABSPATH') || exit;
if(wp_get_environment_type()!=='local' || getenv('LOCAL_SITE_URL')!=='http://localhost:8080') { return; }
// Keep this audit's abuse buckets separate; real limits and previous buckets remain intact.
if(preg_match('/^[a-f0-9]{12}$/D',$_SERVER['HTTP_X_H82_AUDIT_CLIENT'] ?? '')) { $_SERVER['REMOTE_ADDR']='h82-audit-'.$_SERVER['HTTP_X_H82_AUDIT_CLIENT']; }
$mode=$_SERVER['HTTP_X_H82_AUDIT_MODE'] ?? '';
if(!in_array($mode,['expired','missing','renderer','smtp','consumer','admin'],true)) { return; }
if($mode==='expired' || $mode==='missing') {
    add_action('template_redirect',static function() use($mode) {
        if($mode==='missing') { unset($_POST['cc_token']);return; }
        $value=(time()-DAY_IN_SECONDS-10).':'.str_repeat('a',48);$cookie=$_COOKIE['cc_legal_browser'] ?? '';
        $_POST['cc_token']=$value.':'.hash_hmac('sha256',$value.':'.$cookie,wp_salt('nonce'));
    },0);return;
}
if(!str_ends_with((string)($_POST['cc_email'] ?? ''),'@example.test') && !is_admin()) { return; }
if($mode==='renderer') { add_action('woocommerce_email_header',static function(){throw new RuntimeException('Synthetic renderer failure');},-1000); }
elseif($mode==='smtp') { add_action('phpmailer_init',static function($mailer){$mailer->Host='127.0.0.1';$mailer->Port=1;$mailer->Timeout=1;},1000); }
else { add_filter('pre_wp_mail',static function($result,$mail) use($mode) {
    $to=is_array($mail['to']) ? implode(',',$mail['to']) : $mail['to'];
    if($mode==='consumer' && str_starts_with($to,'h82.audit.')) { return false; }
    if($mode==='admin' && $to==='legal-admin@example.test') { return false; }
    return $result;
},PHP_INT_MAX,2); }
