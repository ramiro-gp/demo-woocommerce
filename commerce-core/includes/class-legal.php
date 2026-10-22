<?php
/** Consumer rights module. No public order lookup and no financial/order writes. */
namespace CommerceCore;
defined('ABSPATH') || exit;

final class Legal {
    const OPTION = 'commerce_core_legal';
    const CAP = 'manage_woocommerce';
    private static $result = null;
    private static $errors = [];
    private static $cookie = '';

    public static function register() {
        add_action('init', [__CLASS__, 'install'], 1);
        add_action('template_redirect', [__CLASS__, 'request'], 1);
        add_shortcode('commerce_legal_form', [__CLASS__, 'form']);
        add_shortcode('commerce_legal_identity', [__CLASS__, 'identity']);
        add_shortcode('commerce_legal_navigation', [__CLASS__, 'navigation']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_commerce_legal_manage', [__CLASS__, 'manage']);
        add_action('admin_post_commerce_legal_settings', [__CLASS__, 'save_settings']);
        add_filter('wp_privacy_personal_data_exporters', [__CLASS__, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [__CLASS__, 'erasers']);
        add_filter('rest_prepare_page', static function ($response, $post) {
            return wp_get_environment_type() !== 'local' && get_post_meta($post->ID, '_commerce_legal_demo', true) ? new \WP_Error('not_available', 'Contenido de demostración no disponible.', ['status'=>404]) : $response;
        }, 10, 2);
        add_action('woocommerce_review_order_before_submit', [__CLASS__, 'rights_notice']);
        add_action('woocommerce_email_after_order_table', static function ($order, $admin, $plain) { if (!$admin) { self::rights_notice($plain); } }, 20, 3);
    }

    public static function config() { return (array) get_option(self::OPTION, []); }
    public static function table($kind = 'requests') { global $wpdb; return $wpdb->prefix . 'commerce_legal_' . $kind; }
    public static function install() {
        if (get_option('commerce_legal_schema') === '1') { return; }
        if (!is_admin() && !(defined('WP_CLI') && WP_CLI)) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::table() . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            code varchar(48) NOT NULL,
            token_hash varchar(64) NOT NULL,
            payload_hash varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            name varchar(120) NOT NULL,
            email varchar(254) NOT NULL,
            reference varchar(100) NOT NULL DEFAULT '',
            message text NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'received',
            order_id bigint unsigned NOT NULL DEFAULT 0,
            consumer_mail varchar(12) NOT NULL DEFAULT 'pending',
            admin_mail varchar(12) NOT NULL DEFAULT 'pending',
            mail_attempt_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            UNIQUE KEY token_hash (token_hash),
            KEY email (email(191))
        ) ENGINE=InnoDB $charset;");
        dbDelta('CREATE TABLE ' . self::table('events') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            request_id bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            actor_id bigint unsigned NOT NULL DEFAULT 0,
            kind varchar(32) NOT NULL,
            detail text NOT NULL,
            PRIMARY KEY  (id),
            KEY request_id (request_id)
        ) ENGINE=InnoDB $charset;");
        dbDelta('CREATE TABLE ' . self::table('limits') . " (
            bucket varchar(64) NOT NULL,
            hits int unsigned NOT NULL DEFAULT 1,
            expires_at bigint unsigned NOT NULL,
            PRIMARY KEY  (bucket),
            KEY expires_at (expires_at)
        ) ENGINE=InnoDB $charset;");
        foreach (['requests','events','limits'] as $kind) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::table($kind))) !== self::table($kind)) { return; }
        }
        update_option('commerce_legal_schema', '1', false);
    }
    public static function url($key) {
        $id = absint(self::config()['pages'][$key] ?? 0);
        if (!$id || get_post_status($id) !== 'publish' || (wp_get_environment_type() !== 'local' && get_post_meta($id, '_commerce_legal_demo', true))) { return ''; }
        return get_permalink($id);
    }
    public static function is_page() { $ids=array_values(array_filter(self::config()['pages'] ?? [])); return $ids && is_page($ids); }
    public static function navigation() {
        $out = '<nav class="cc-legal-nav" aria-label="Información legal">';
        foreach (['privacy'=>'Privacidad','terms'=>'Términos y condiciones','shipping'=>'Envíos','returns'=>'Cambios y devoluciones','withdrawal'=>'Derecho de arrepentimiento'] as $key=>$label) {
            if ($url = self::url($key)) { $out .= '<a href="'.esc_url($url).'"'.(get_queried_object_id() === (int)(self::config()['pages'][$key] ?? 0) ? ' aria-current="page"' : '').'>'.esc_html($label).'</a>'; }
        }
        return $out . '</nav>';
    }
    public static function identity() {
        $c = self::config(); $out='<section class="cc-identity" aria-label="Responsable y atención al consumidor"><h2>Responsable y atención</h2><dl>';
        foreach (self::fields() as $key=>$label) {
            if (in_array($key, ['fiscal_url','fiscal_image'],true)) { continue; }
            $out .= '<div><dt>'.esc_html($label).'</dt><dd>'.esc_html($c[$key] ?? 'Pendiente de completar por el titular').'</dd></div>';
        }
        return $out.'</dl>'.self::fiscal().'</section>';
    }
    public static function fiscal() {
        $c=self::config();
        if (!empty($c['fiscal_url']) && !empty($c['fiscal_image'])) {
            return '<a class="cc-fiscal" href="'.esc_url($c['fiscal_url']).'" target="_blank" rel="noopener noreferrer"><img src="'.esc_url($c['fiscal_image']).'" width="80" alt="Formulario 960/D — Data Fiscal del comercio (abre en otra pestaña)" loading="lazy"></a>';
        }
        return wp_get_environment_type() === 'local' ? '<p class="cc-demo">DEMO · Data Fiscal ARCA sin configurar. No hay un QR fiscal real.</p>' : '';
    }
    public static function rights_notice($plain = false) {
        $url=self::url('withdrawal'); if (!$url) { return; }
        $text='Compras a distancia: podés comunicar tu arrepentimiento dentro del plazo legal general de 10 días corridos desde la entrega o celebración del contrato, lo último que ocurra. La devolución no tiene costo para vos. Consultá el cómputo, las excepciones y el formulario de solicitud.';
        if ($plain === true) { echo "\nDERECHO DE ARREPENTIMIENTO\n".$text."\n".esc_url_raw($url)."\n"; }
        else { echo '<p class="cc-rights-notice"><strong>Derecho de arrepentimiento.</strong> '.esc_html($text).' <a href="'.esc_url($url).'">BOTÓN DE ARREPENTIMIENTO</a></p>'; }
    }
    public static function request() {
        if (!self::is_page()) { return; }
        if (wp_get_environment_type() !== 'local' && get_post_meta(get_queried_object_id(), '_commerce_legal_demo', true)) { wp_die('Contenido DEMO pendiente de adaptación y revisión.', 'Contenido no disponible', ['response'=>503]); }
        if (get_queried_object_id() !== absint(self::config()['pages']['withdrawal'] ?? 0)) { return; }
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, noarchive');
        self::$cookie = (string)($_COOKIE['cc_legal_browser'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', self::$cookie)) {
            self::$cookie=bin2hex(random_bytes(32));
            setcookie('cc_legal_browser', self::$cookie, ['expires'=>0,'path'=>COOKIEPATH ?: '/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>16384) { self::$errors[]='El envío es demasiado grande. El mensaje admite hasta 2000 caracteres.'; status_header(413); return; }
        $body = wp_unslash($_POST);
        if (!wp_verify_nonce(is_string($body['_wpnonce'] ?? null) ? $body['_wpnonce'] : '', 'cc_legal_submit') || !self::valid_token($body['cc_token'] ?? '')) { self::$errors[]='La sesión del formulario venció. Recargá la página y volvé a enviarlo.'; status_header(403); return; }
        $result = self::submit($body, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (is_wp_error($result)) { self::$errors=$result->get_error_messages(); status_header($result->get_error_code() === 'rate' ? 429 : 400); }
        else { self::$result=$result; }
    }
    private static function token() {
        $value=time().':'.bin2hex(random_bytes(24));
        return $value.':'.hash_hmac('sha256',$value.':'.self::$cookie,wp_salt('nonce'));
    }
    private static function valid_token($token) {
        if (!is_string($token) || !preg_match('/^(\d{10}):([a-f0-9]{48}):([a-f0-9]{64})$/D',$token,$m)) { return false; }
        return (int)$m[1] <= time()+60 && time()-(int)$m[1] <= DAY_IN_SECONDS && hash_equals(hash_hmac('sha256',$m[1].':'.$m[2].':'.self::$cookie,wp_salt('nonce')),$m[3]);
    }
    private static function limit($key, $max) {
        global $wpdb; $table=self::table('limits'); $hour=(int)floor(time()/HOUR_IN_SECONDS);
        $bucket=hash_hmac('sha256',$hour.':'.$key,wp_salt('auth'));
        $wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1",$bucket,time()+HOUR_IN_SECONDS));
        $hits=$wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s",$bucket));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at < %d",time()));
        return $hits !== null && (int)$hits <= $max;
    }
    /** Called only after transport CSRF validation; also usable by local integration QA. */
    public static function submit($body, $ip) {
        global $wpdb;
        foreach (['cc_name','cc_email','cc_reference','cc_message','cc_token','cc_website'] as $key) {
            if (isset($body[$key]) && !is_string($body[$key])) { return new \WP_Error('input','Revisá los datos del formulario.'); }
        }
        if (!empty($body['cc_website'])) { return new \WP_Error('spam','No se pudo enviar. Dejá vacío el campo de control e intentá nuevamente.'); }
        $raw_email=trim($body['cc_email'] ?? '');
        $data=['name'=>sanitize_text_field($body['cc_name'] ?? ''),'email'=>sanitize_email($raw_email),'reference'=>sanitize_text_field($body['cc_reference'] ?? ''),'message'=>sanitize_textarea_field($body['cc_message'] ?? '')];
        $errors=new \WP_Error();
        if ($data['name']==='' || mb_strlen($data['name'])>120) { $errors->add('name','Ingresá tu nombre (hasta 120 caracteres).'); }
        if (!is_email($raw_email) || $raw_email !== $data['email'] || strlen($raw_email)>254) { $errors->add('email','Ingresá un email de contacto válido.'); }
        if (mb_strlen($data['reference'])>100 || mb_strlen($data['message'])>2000) { $errors->add('length','La referencia admite 100 caracteres y el mensaje 2000.'); }
        if (wp_get_environment_type()==='local' && !preg_match('/@[^@]+\.test$/i',$data['email'])) { $errors->add('demo','En esta DEMO usá un email ficticio terminado en .test.'); }
        if ($errors->has_errors()) { return $errors; }
        $token=hash('sha256',$body['cc_token'] ?? ''); $payload=hash_hmac('sha256',wp_json_encode($data),wp_salt('auth')); $table=self::table();
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token_hash=%s",$token));
        if ($existing) { return hash_equals($existing->payload_hash,$payload) ? $existing : new \WP_Error('duplicate','Este envío ya fue recibido. Recargá la página para iniciar una solicitud diferente.'); }
        if (!self::limit('ip:'.$ip,30) || !self::limit('email:'.strtolower($data['email']),6)) { return new \WP_Error('rate','Alcanzaste el límite temporal de envíos. Conservá tu constancia si ya enviaste la solicitud. Podés usar el canal de atención publicado o volver a intentar en una hora.'); }
        $data+=['code'=>'ARR-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(10))),'token_hash'=>$token,'payload_hash'=>$payload,'created_at'=>current_time('mysql',true)];
        $wpdb->query('START TRANSACTION');
        // Expected concurrent retries must not log a duplicate-key query containing PII.
        $ok=$wpdb->query($wpdb->prepare("INSERT INTO $table (name,email,reference,message,code,token_hash,payload_hash,created_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",$data['name'],$data['email'],$data['reference'],$data['message'],$data['code'],$data['token_hash'],$data['payload_hash'],$data['created_at'])); $id=(int)$wpdb->insert_id;
        if ($ok!==1) { $wpdb->query('ROLLBACK'); $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token_hash=%s",$token)); return $existing && hash_equals($existing->payload_hash,$payload) ? $existing : new \WP_Error('storage','No pudimos registrar la solicitud. No hay constancia emitida: intentá nuevamente o usá el canal de atención publicado.'); }
        if (!self::event($id,'received','Solicitud recibida; pendiente de gestión.',0)) { $wpdb->query('ROLLBACK'); return new \WP_Error('storage','No pudimos registrar la solicitud. Intentá nuevamente.'); }
        $wpdb->query('COMMIT');
        self::notify($id);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
    }
    public static function event($id,$kind,$detail,$actor=null) {
        global $wpdb;
        return $wpdb->insert(self::table('events'),['request_id'=>$id,'created_at'=>current_time('mysql',true),'actor_id'=>$actor ?? get_current_user_id(),'kind'=>$kind,'detail'=>$detail]);
    }
    public static function notify($id) {
        global $wpdb; $table=self::table(); $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id)); if (!$row) { return; }
        foreach (['consumer','admin'] as $kind) {
            $column=$kind.'_mail';
            if (!$wpdb->query($wpdb->prepare("UPDATE $table SET $column='sending',mail_attempt_at=%s WHERE id=%d AND $column IN ('pending','failed')",current_time('mysql',true),$id))) { continue; }
            $recipient=$kind==='consumer' ? $row->email : (self::config()['notification_email'] ?? get_option('admin_email'));
            $subject=($kind==='consumer'?'Constancia de recepción ':'Nueva solicitud de arrepentimiento ').$row->code;
            $text="Código: $row->code\nFecha de recepción (UTC): $row->created_at\nEstado: recibida, pendiente de gestión.\n\nRecibir esta solicitud no significa que esté aprobada, que el pedido esté cancelado ni que se haya realizado un reembolso. Conservá este código. No respondas con documentos ni datos bancarios.\n";
            if ($kind==='admin') { $text.="\nRevisá el caso en la administración autorizada de WordPress. Verificá identidad y pedido por medios razonables antes de operar.\n"; }
            elseif (!empty(self::config()['email'])) { $text.="\nAtención del comercio: ".self::config()['email']."\n"; }
            $html=WC()->mailer()->wrap_message($subject,'<p>'.nl2br(esc_html($text)).'</p>');
            $alt=static function ($mailer) use ($text) { $mailer->AltBody=$text; };
            add_action('phpmailer_init',$alt,100);
            $safe=is_email($recipient) && (wp_get_environment_type()!=='local' || preg_match('/@[^@]+\.test$/i',$recipient));
            try { $sent=$safe && wp_mail($recipient,$subject,$html,['Content-Type: text/html; charset=UTF-8']); } catch (\Throwable $e) { $sent=false; }
            finally { remove_action('phpmailer_init',$alt,100); }
            $wpdb->update($table,[$column=>$sent?'sent':'failed'],['id'=>$id]);
            self::event($id,'mail_'.$kind,$sent?'Entregado al transporte de correo; no prueba lectura.':'Falló el intento; registro conservado para reintento.',0);
        }
    }
    public static function form() {
        if (self::$result) {
            return '<section class="cc-receipt" role="status" aria-labelledby="cc-receipt-title"><h2 id="cc-receipt-title">Recibimos tu solicitud</h2><p>Guardá esta constancia.</p><p class="cc-receipt-code">'.esc_html(self::$result->code).'</p><p>Fecha de recepción (UTC): '.esc_html(self::$result->created_at).'</p><p><strong>Estado: recibida, pendiente de gestión.</strong> No significa que esté aprobada, que tu pedido esté cancelado ni que el dinero haya sido devuelto.</p><p>'.(self::$result->consumer_mail==='sent'?'Enviamos una copia al email indicado. Si no la encontrás, conservá este código.':'No pudimos enviar la copia por email. Tu solicitud quedó registrada y esta constancia sigue siendo válida.').'</p><button type="button" class="cc-print" onclick="window.print()">Imprimir o guardar constancia</button></section>';
        }
        $value=static function($key) { return isset($_POST[$key]) && is_string($_POST[$key]) ? esc_attr(wp_unslash($_POST[$key])) : ''; };
        ob_start();
        if (self::$errors) { echo '<div class="cc-errors" role="alert" tabindex="-1"><h2>Revisá el envío</h2><ul>'; foreach(self::$errors as $error) { echo '<li>'.esc_html($error).'</li>'; } echo '</ul></div>'; }
        ?>
        <form class="cc-withdrawal-form" method="post" action="<?php echo esc_url(self::url('withdrawal')); ?>" aria-describedby="cc-form-help">
          <h2>Solicitar arrepentimiento</h2>
          <p id="cc-form-help">No necesitás una cuenta ni explicar el motivo. Nombre y email son obligatorios para poder gestionar tu solicitud. El número de pedido y el mensaje son opcionales.</p>
          <?php if(wp_get_environment_type()==='local') { echo '<p class="cc-demo">DEMO local: usá sólo un email ficticio terminado en .test. No ingreses datos personales reales.</p>'; } ?>
          <label for="cc-name">Nombre y apellido</label><input id="cc-name" name="cc_name" autocomplete="name" maxlength="120" required value="<?php echo $value('cc_name'); ?>">
          <label for="cc-email">Email de contacto</label><input id="cc-email" name="cc_email" type="email" autocomplete="email" maxlength="254" required value="<?php echo $value('cc_email'); ?>">
          <label for="cc-reference">Referencia o número de pedido (opcional)</label><input id="cc-reference" name="cc_reference" maxlength="100" value="<?php echo $value('cc_reference'); ?>" aria-describedby="cc-reference-help"><p id="cc-reference-help">Si no lo tenés, dejalo vacío. No se muestran datos de compras desde este formulario.</p>
          <label for="cc-message">Mensaje (opcional)</label><textarea id="cc-message" name="cc_message" rows="4" maxlength="2000" aria-describedby="cc-message-help"><?php echo esc_textarea(isset($_POST['cc_message']) && is_string($_POST['cc_message']) ? wp_unslash($_POST['cc_message']) : ''); ?></textarea><p id="cc-message-help">Podés indicar qué productos incluye tu solicitud. No envíes DNI, documentos ni datos bancarios.</p>
          <div class="cc-honeypot" aria-hidden="true"><label for="cc-website">Dejá este campo vacío</label><input id="cc-website" name="cc_website" tabindex="-1" autocomplete="off"></div>
          <?php wp_nonce_field('cc_legal_submit'); ?><input type="hidden" name="cc_token" value="<?php echo esc_attr(self::token()); ?>">
          <p>Usamos los datos para recibir y gestionar este trámite. <?php if($url=self::url('privacy')) { echo '<a href="'.esc_url($url).'">Consultá la política de privacidad</a>.'; } ?></p>
          <button type="submit">Enviar solicitud de arrepentimiento</button>
          <p>Al enviar, vas a recibir un código. La recepción no cancela automáticamente el pedido ni realiza un reembolso.</p>
        </form>
        <?php return ob_get_clean();
    }
    public static function menu() {
        add_submenu_page('woocommerce','Solicitudes de arrepentimiento','Arrepentimiento',self::CAP,'commerce-legal',[__CLASS__,'admin']);
        add_options_page('Identidad y legales','Identidad y legales','manage_options','commerce-legal-settings',[__CLASS__,'settings']);
    }
    public static function states() { return ['received'=>'Recibida','review'=>'En gestión','awaiting'=>'Esperando información','resolved'=>'Gestión cerrada']; }
    public static function admin() {
        if (!current_user_can(self::CAP)) { wp_die('Acceso denegado.','Acceso denegado',['response'=>403]); }
        global $wpdb; $table=self::table(); $id=absint($_GET['case'] ?? 0);
        echo '<div class="wrap"><h1>Solicitudes de arrepentimiento</h1><p>La recepción no modifica pedidos ni pagos. Verificá la identidad por un medio habitual antes de revelar datos o realizar operaciones. Revisá las solicitudes cada día; el código se emite en el momento del envío.</p>';
        if ($id) {
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id)); if(!$row) { echo '<p>Solicitud no encontrada.</p></div>';return; }
            echo '<h2>'.esc_html($row->code).'</h2><dl>';
            foreach(['created_at'=>'Fecha UTC','name'=>'Nombre informado','email'=>'Email informado (no verificado)','reference'=>'Referencia informada (no verificada)','message'=>'Mensaje','consumer_mail'=>'Correo consumidor','admin_mail'=>'Correo administrador'] as $key=>$label) { echo '<dt><strong>'.esc_html($label).'</strong></dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere">'.esc_html($row->$key).'</dd>'; } echo '</dl>';
            if($row->order_id && ($order=wc_get_order($row->order_id))) { echo '<p>Pedido vinculado tras verificación: <a href="'.esc_url($order->get_edit_order_url()).'">#'.esc_html($order->get_order_number()).'</a></p>'; }
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('cc_legal_manage_'.$id);
            echo '<input type="hidden" name="action" value="commerce_legal_manage"><input type="hidden" name="case" value="'.esc_attr($id).'"><p><label>Estado de gestión <select name="status">';
            foreach(self::states() as $value=>$label) { echo '<option value="'.esc_attr($value).'" '.selected($row->status,$value,false).'>'.esc_html($label).'</option>'; }
            echo '</select></label></p><p><label>ID interno del pedido verificado <input name="order_id" type="number" min="0" value="'.esc_attr($row->order_id).'"></label></p><p><label><input type="checkbox" name="verified" value="1"> Verifiqué identidad y relación con el pedido por un medio habitual. Detallo el procedimiento en la nota, sin documentos ni secretos.</label></p><p><label>Nota interna (obligatoria para registrar gestión)<br><textarea name="note" rows="4" cols="65" maxlength="2000" required></textarea></label></p><p><button class="button button-primary" name="operation" value="update">Guardar gestión</button> <button class="button" name="operation" value="retry" formnovalidate>Reintentar correos pendientes o fallidos</button></p><p>Una entrega al transporte no prueba recepción en bandeja. Si un envío quedó en “sending” por una interrupción, verificá el transporte antes de recuperarlo con soporte; no reenvíes a ciegas.</p></form><h2>Trazabilidad y notas internas</h2><ol>';
            foreach($wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE request_id=%d ORDER BY id DESC',$id)) as $event) { echo '<li><strong>'.esc_html($event->created_at.' UTC · usuario '.$event->actor_id.' · '.$event->kind).'</strong><p style="white-space:pre-wrap">'.esc_html($event->detail).'</p></li>'; } echo '</ol>';
        } else {
            $page=max(1,absint($_GET['paged'] ?? 1)); $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT 30 OFFSET %d",($page-1)*30));
            echo '<table class="widefat striped"><thead><tr><th>Código</th><th>Recepción UTC</th><th>Estado</th><th>Correo consumidor / admin</th></tr></thead><tbody>';
            foreach($rows as $r) { echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page=commerce-legal&case='.$r->id)).'">'.esc_html($r->code).'</a></td><td>'.esc_html($r->created_at).'</td><td>'.esc_html(self::states()[$r->status] ?? $r->status).'</td><td>'.esc_html($r->consumer_mail.' / '.$r->admin_mail).'</td></tr>'; } echo '</tbody></table><p>';
            if($page>1) { echo '<a href="'.esc_url(admin_url('admin.php?page=commerce-legal&paged='.($page-1))).'">Más recientes</a> '; }
            if(count($rows)===30) { echo '<a href="'.esc_url(admin_url('admin.php?page=commerce-legal&paged='.($page+1))).'">Anteriores</a>'; } echo '</p>';
        } echo '</div>';
    }
    public static function manage() {
        if (!current_user_can(self::CAP)) { wp_die('Acceso denegado.','Acceso denegado',['response'=>403]); }
        global $wpdb; $id=absint($_POST['case'] ?? 0); check_admin_referer('cc_legal_manage_'.$id); $table=self::table(); $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
        if(!$row) { wp_die('Solicitud no encontrada.'); }
        if(($_POST['operation'] ?? '')==='retry') { self::event($id,'retry','Reintento manual de correos pendientes o fallidos.'); self::notify($id); }
        else {
            $state=sanitize_key($_POST['status'] ?? ''); $note=sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')); $order=absint($_POST['order_id'] ?? 0);
            if(!isset(self::states()[$state]) || !$note || mb_strlen($note)>2000) { wp_die('Estado o nota inválidos.'); }
            if($order!== (int)$row->order_id && $order && (empty($_POST['verified']) || !wc_get_order($order))) { wp_die('Verificá identidad y pedido, y registrá cómo lo hiciste.'); }
            $wpdb->query('START TRANSACTION');
            $locked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$id));
            $ok=$wpdb->update($table,['status'=>$state,'order_id'=>$order],['id'=>$id]);
            $event=self::event($id,'management',"Estado: $locked->status → $state. Pedido: $locked->order_id → $order.\n".$note);
            if($ok===false || !$event) { $wpdb->query('ROLLBACK');wp_die('No se guardó la gestión. Intentá nuevamente.'); } $wpdb->query('COMMIT');
        }
        wp_safe_redirect(admin_url('admin.php?page=commerce-legal&case='.$id)); exit;
    }
    public static function fields() { return ['trade_name'=>'Nombre comercial','legal_name'=>'Razón social / titular','cuit'=>'CUIT','address'=>'Domicilio legal','website'=>'Sitio del comercio','email'=>'Email de atención y privacidad','channel'=>'Teléfono o canal alternativo','area'=>'Área responsable de atención','hours'=>'Días y horarios de atención','fiscal_url'=>'URL oficial Data Fiscal del titular','fiscal_image'=>'URL del logo oficial Data Fiscal']; }
    public static function fiscal_url($url,$image=false) {
        $u=wp_parse_url($url); if(!$u || ($u['scheme'] ?? '')!=='https' || isset($u['user']) || isset($u['pass']) || isset($u['port'])) { return ''; }
        $host=strtolower($u['host'] ?? '');
        if(!in_array($host,['serviciosweb.afip.gob.ar','serviciosweb.arca.gob.ar','www.afip.gob.ar','www.arca.gob.ar','arca.gob.ar'],true)) { return ''; }
        if($image ? !preg_match('~^/images/f960/DATAWEB\.jpg$~i',$u['path'] ?? '') : !preg_match('~^/clavefiscal/qr/response\.aspx$~i',$u['path'] ?? '')) { return ''; }
        return esc_url_raw($url,['https']);
    }
    public static function settings() {
        if(!current_user_can('manage_options')) { wp_die('Acceso denegado.'); }
        $c=self::config();echo '<div class="wrap"><h1>Identidad y legales</h1><p>Una sola fuente para identidad, footer y páginas. Pendientes no equivalen a cumplimiento. El titular debe aportar datos auténticos y revisar contenido, horarios, retención y Data Fiscal antes de producción. Las páginas se editan desde Páginas.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cc_legal_settings');echo '<input type="hidden" name="action" value="commerce_legal_settings"><table class="form-table">';
        foreach(self::fields()+['notification_email'=>'Email interno de notificaciones'] as $key=>$label) { echo '<tr><th><label for="cc-setting-'.esc_attr($key).'">'.esc_html($label).'</label></th><td><input class="regular-text" id="cc-setting-'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($c[$key] ?? '').'" maxlength="500"></td></tr>'; }
        echo '</table><h2>Páginas configuradas</h2>';
        foreach(['privacy'=>'Privacidad','terms'=>'Términos','shipping'=>'Envíos','returns'=>'Cambios y devoluciones','withdrawal'=>'Arrepentimiento'] as $key=>$label) { echo '<p><label>'.esc_html($label).' ';wp_dropdown_pages(['name'=>'pages['.$key.']','selected'=>$c['pages'][$key] ?? 0,'show_option_none'=>'Seleccionar página publicada','option_none_value'=>0]);echo '</label></p>'; }
        echo '<p>Privacidad y Términos también se configuran nativamente en Ajustes → Privacidad y WooCommerce → Ajustes → Avanzado. Esta pantalla los sincroniza al guardar. No pegues HTML ni scripts fiscales; sólo las dos URL oficiales obtenidas por el titular. Sin ambas no se muestra un logo fiscal.</p>';submit_button('Guardar identidad y páginas');echo '</form></div>';
    }
    public static function save_settings() {
        if(!current_user_can('manage_options')) { wp_die('Acceso denegado.','Acceso denegado',['response'=>403]); }check_admin_referer('cc_legal_settings');$c=self::config();
        foreach(self::fields()+['notification_email'=>''] as $key=>$label) {
            $raw=wp_unslash($_POST[$key] ?? ''); if(!is_string($raw) || strlen($raw)>500) { wp_die('Campo inválido.'); }
            if(in_array($key,['fiscal_url','fiscal_image'],true)) { $v=$raw ? self::fiscal_url($raw,$key==='fiscal_image') : '';if($raw && !$v) { wp_die('Usá sólo las URL oficiales HTTPS del código Data Fiscal obtenido por el titular.'); } }
            elseif(in_array($key,['email','notification_email'],true)) { $v=sanitize_email($raw);if($raw && (!is_email($raw) || $raw!==$v)) { wp_die('Email inválido.'); } }
            else { $v=sanitize_text_field($raw); }
            if($v!=='') { $c[$key]=$v; } else { unset($c[$key]); }
        }
        foreach(['privacy','terms','shipping','returns','withdrawal'] as $key) { $id=absint($_POST['pages'][$key] ?? 0);if($id && get_post_status($id)!=='publish') { wp_die('Seleccioná páginas publicadas.'); }$c['pages'][$key]=$id; }
        update_option(self::OPTION,$c,false); update_option('wp_page_for_privacy_policy',$c['pages']['privacy']);update_option('woocommerce_terms_page_id',$c['pages']['terms']);
        wp_safe_redirect(admin_url('options-general.php?page=commerce-legal-settings'));exit;
    }
    public static function exporters($list) { $list['commerce-legal']=['exporter_friendly_name'=>'Solicitudes de arrepentimiento','callback'=>[__CLASS__,'export']];return $list; }
    public static function export($email,$page=1) {
        global $wpdb; $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table().' WHERE email=%s ORDER BY id LIMIT 50 OFFSET %d',$email,(max(1,(int)$page)-1)*50));$data=[];
        foreach($rows as $row) { $fields=[];foreach(['code'=>'Código','created_at'=>'Recepción UTC','name'=>'Nombre','email'=>'Email','reference'=>'Referencia informada','message'=>'Mensaje','status'=>'Estado de gestión'] as $key=>$label) { $fields[]=['name'=>$label,'value'=>$row->$key]; }$data[]=['group_id'=>'commerce-legal','group_label'=>'Solicitudes de arrepentimiento','item_id'=>'commerce-legal-'.$row->id,'data'=>$fields]; }
        return ['data'=>$data,'done'=>count($rows)<50];
    }
    public static function erasers($list) { $list['commerce-legal']=['eraser_friendly_name'=>'Solicitudes de arrepentimiento (revisión de conservación)','callback'=>[__CLASS__,'erase']];return $list; }
    public static function erase($email,$page=1) { global $wpdb;$found=(bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table().' WHERE email=%s LIMIT 1',$email));return ['items_removed'=>false,'items_retained'=>$found,'messages'=>$found?['Las solicitudes requieren revisión de conservación por obligaciones legales y defensa de reclamos. Evaluar supresión o disociación del caso y sus notas con personal autorizado; no se borran automáticamente.']:[],'done'=>true]; }
}
