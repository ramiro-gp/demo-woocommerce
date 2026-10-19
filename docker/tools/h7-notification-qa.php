<?php
/** Isolated native receiver simulation. No HTTP receiver is registered here. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
// No credentials, provider calls, refunds or historical-order mutations.
add_filter('pre_http_request', static fn() => new WP_Error('h71_qa_offline', 'Internal notification simulation is offline.'), PHP_INT_MAX);
$state = (array) get_option('trama_h71_concurrency_qa', []);
if (!$state) { WP_CLI::error('Prepare the isolated concurrency fixtures first.'); }
$mp = $GLOBALS['mercadopago'];
$gateway = WC()->payment_gateways()->payment_gateways()['woo-mercado-pago-basic'];
$checks = []; $observations = [];
$check = static function ($name, $passed) use (&$checks) { $checks[$name] = (bool) $passed; if (!$passed) { throw new RuntimeException($name); } };
$make = static function () use ($state) {
    $order = wc_create_order(['status' => 'pending', 'created_via' => 'h71-notification-simulation']);
    $order->set_billing_email('h71.notifications@example.test');
    $order->set_billing_first_name('Prueba'); $order->set_billing_last_name('Notificaciones');
    $order->set_payment_method('woo-mercado-pago-basic');
    $order->update_meta_data('_h71_notification_simulation', $state['run']);
    $order->add_product(wc_get_product($state['products']['B']), 1);
    $order->calculate_totals(); $order->save();
    return wc_get_order($order->get_id());
};
$fingerprint = static function ($id) {
    $order = wc_get_order($id);
    return wp_json_encode([$order->get_status(), $order->get_payment_method(), $order->get_transaction_id(), $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : null, $order->get_total(), array_map(static fn($item) => $item->get_data(), $order->get_items()), array_map(static fn($meta) => $meta->get_data(), $order->get_meta_data()), array_map(static fn($note) => [$note->id, $note->content], wc_get_order_notes(['order_id' => $id])), (bool) $order->get_data_store()->get_stock_reduced($id)]);
};
class H71NotificationResponse extends RuntimeException { public $status; public function __construct($status) { $this->status = $status; parent::__construct('Isolated response'); } }
class H71OfflineSdk extends MercadoPago\PP\Sdk\Sdk {
    public $payload; public $reads = 0;
    public function __construct($payload) { $this->payload = $payload; }
    public function getNotificationInstance() {
        return new class($this) {
            private $sdk;
            public function __construct($sdk) { $this->sdk = $sdk; }
            public function read($parameters) {
                if (($parameters['id'] ?? '') !== 'M-900000000001') { throw new RuntimeException('Unexpected simulation resource.'); }
                ++$this->sdk->reads;
                return new class($this->sdk->payload) { private $payload; public function __construct($payload) { $this->payload=$payload; } public function toArray() { return $this->payload; } };
            }
        };
    }
}
class H71OfflineCore extends MercadoPago\Woocommerce\Notification\CoreNotification {
    public $sdk; public $body = '{"notification_id":"M-900000000001"}';
    protected function getInput(): string { return $this->body; }
    public function getSdkInstance(): MercadoPago\PP\Sdk\Sdk { return $this->sdk; }
    public function setResponse(int $status, string $message): void { throw new H71NotificationResponse($status); }
}
$created = [];
try {
    $first = $make(); $second = $make(); $created = [$first->get_id(), $second->get_id()];
    $secondBefore = $fingerprint($second->get_id());
    $stockBefore = wc_get_product($state['products']['B'])->get_stock_quantity();
    $receiver = new H71OfflineCore($gateway, $mp->logs, $mp->orderStatus, $mp->sellerConfig, $mp->storeConfig);
    $receiver->sdk = new H71OfflineSdk(['id' => '900000000002', 'external_reference' => 'WC-' . $first->get_id(), 'status' => 'approved', 'payments_details' => [['id' => '900000000002', 'payment_type_id' => 'account_money', 'total_amount' => 52000, 'paid_amount' => 52000, 'date_created' => '2026-10-18T00:00:00Z']]]);
    $receiver->handleReceivedNotification([]);
    $first = wc_get_order($first->get_id());
    $check('late_approval_targets_original_order_only', $first->has_status('processing') && $first->get_date_paid() && $secondBefore === $fingerprint($second->get_id()));
    $check('approval_reduces_stock_once', wc_get_product($state['products']['B'])->get_stock_quantity() === $stockBefore - 1);
    $check('native_mp_id_associated', $first->get_meta('_Mercado_Pago_Payment_IDs') === '900000000002');
    $approvedBefore = $fingerprint($first->get_id());
    // Identical bytes through the native parser and handler, with SDK transport
    // deliberately replaced by a fixed internal resource. NOT external replay.
    usleep(1100000); // Cross a timestamp boundary: same-second calls hide the native fallback-date defect.
    $receiver->handleReceivedNotification([]); $receiver->handleReceivedNotification([]);
    $normalize = static fn($value) => preg_replace('/\[Date [^\]]+\]/', '[Date]', $value);
    $check('identical_approved_simulations_preserve_financial_fields', $normalize($approvedBefore) === $normalize($fingerprint($first->get_id())));
    $observations['undated_resource_changes_diagnostic_date_on_replay'] = $approvedBefore !== $fingerprint($first->get_id());
    $check('duplicate_approval_no_second_stock_reduction', wc_get_product($state['products']['B'])->get_stock_quantity() === $stockBefore - 1);
    $check('resource_read_for_every_delivery', $receiver->sdk->reads === 3);
    $receiver->sdk->payload['status'] = 'pending';
    $notesBeforeStale = count(wc_get_order_notes(['order_id' => $first->get_id()]));
    $receiver->handleReceivedNotification([]);
    $first = wc_get_order($first->get_id());
    $check('stale_pending_does_not_downgrade_processing', $first->has_status('processing') && $first->get_date_paid());
    $observations['stale_pending_can_add_native_note'] = count(wc_get_order_notes(['order_id' => $first->get_id()])) > $notesBeforeStale;
    $check('stale_pending_keeps_stock_reduced_once', wc_get_product($state['products']['B'])->get_stock_quantity() === $stockBefore - 1);
    $check('other_order_unchanged_after_all_deliveries', $secondBefore === $fingerprint($second->get_id()));
    $receiver->body = '{"notification_id":"invalid"}'; $readsBefore = $receiver->sdk->reads;
    try { $receiver->handleReceivedNotification([]); $check('malformed_id_rejected', false); }
    catch (H71NotificationResponse $response) { $check('malformed_id_rejected_before_sdk', $response->status === 400 && $readsBefore === $receiver->sdk->reads); }

    // Probe the documented final-state limitation using only another own order.
    $cancelled = $make(); $created[] = $cancelled->get_id(); $cancelled->update_status('cancelled', 'Internal ordering probe; no provider payment.');
    $receiver->sdk->payload['external_reference'] = 'WC-' . $cancelled->get_id();
    $receiver->body = '{"notification_id":"M-900000000001"}';
    $receiver->handleReceivedNotification([]);
    $observations['stale_pending_after_cancelled_native_status'] = wc_get_order($cancelled->get_id())->get_status();
    $check('cancelled_temporal_limitation_reproduced', $observations['stale_pending_after_cancelled_native_status'] === 'pending');
} catch (Throwable $error) {
    $checks['completed_without_exception'] = false;
    $observations['failure_type'] = get_class($error);
    $observations['failure_line'] = $error->getLine();
} finally {
    // Preserve every QA order. Release only stock from these newly created
    // offline simulations using native cancellation, never direct stock writes.
    foreach ($created as $id) {
        $order = wc_get_order($id);
        if ($order && $order->get_meta('_h71_notification_simulation') === $state['run']) { $order->update_status('cancelled', 'End of isolated H7.1 notification simulation. No payment/refund executed.'); }
    }
}
echo wp_json_encode(['result' => in_array(false, $checks, true) ? 'failed' : 'passed', 'test_kind' => 'Isolated native Core parser/handler with offline SDK transport; not external replay, not authenticated API evidence', 'checks' => $checks, 'observations' => $observations, 'own_order_ids' => $created, 'provider_calls' => 0, 'orders_deleted' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if (in_array(false, $checks, true)) { exit(1); }
