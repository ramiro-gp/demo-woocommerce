[CmdletBinding()]
param(
  [ValidatePattern('^[a-z][a-z0-9-]{0,29}$')][string]$Instance = 'local'
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Run H2 verification only from the canonical C:\Codigo\demo-woocommerce repository.' }
$dev = Join-Path $repoRoot 'scripts/dev.ps1'
$privateRoot = Join-Path $repoRoot ".local/$Instance"
$reportPath = Join-Path $privateRoot 'h2-http.json'
if (-not (Test-Path -LiteralPath (Join-Path $privateRoot '.env') -PathType Leaf)) { throw 'Initialize and start the existing local instance before H2 verification.' }
$checks = New-Object 'System.Collections.Generic.List[object]'
$stage = 'read_fixture_map'
$cartToken = $null
$baseUrl = $null
$apiBase = $null
$draftId = 0
$report = [ordered]@{
  verifiedAtUtc = [DateTime]::UtcNow.ToString('o')
  instance = $Instance
  result = 'running'
  checks = @()
  scope = 'Local Store API guest cart and checkout preview; no payment submission or gateway activation.'
}

function Assert-Check([bool]$Condition, [string]$Message) {
  if (-not $Condition) { throw $Message }
}
function Pass([string]$Name, [object]$Evidence) {
  $checks.Add([ordered]@{ name = $Name; result = 'passed'; evidence = $Evidence })
  Write-Host "PASS: $Name"
}
function Save-Report {
  $report.checks = @($checks.ToArray())
  [IO.File]::WriteAllText($reportPath, ($report | ConvertTo-Json -Depth 8) + "`n", (New-Object Text.UTF8Encoding($false)))
}
function Read-Fixtures {
  # This read uses WC product/order APIs only and emits no credentials or order key.
  $php = @'
if (wp_get_environment_type() !== 'local' || !defined('WC_VERSION')) { WP_CLI::error('Local WooCommerce required.'); }
$out = ['url' => home_url(), 'rest_url' => get_rest_url(null, 'wc/store/v1'), 'woocommerce' => WC_VERSION, 'hpos' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'products' => []];
foreach (['H2-SIMPLE', 'H2-VARIABLE', 'H2-VARIABLE-S-NEGRO', 'H2-VARIABLE-M-NEGRO'] as $sku) {
  $p = wc_get_product(wc_get_product_id_by_sku($sku));
  if (!$p || $p->get_meta('_demo_h2_fixture') !== 'demo-woocommerce-h2-v1') { WP_CLI::error('H2 fixture missing or unowned.'); }
  $out['products'][$sku] = ['id' => $p->get_id(), 'stock' => $p->get_stock_quantity(), 'stock_status' => $p->get_stock_status()];
}
$statuses = array_keys(wc_get_order_statuses());
$statuses[] = 'wc-checkout-draft';
$orders = wc_get_orders(['type' => 'shop_order', 'limit' => 1, 'return' => 'ids', 'paginate' => true, 'status' => $statuses]);
$out['order_count'] = $orders->total;
$out['available_payment_methods'] = array_keys(WC()->payment_gateways()->get_available_payment_gateways());
echo wp_json_encode($out);
'@
  $raw = @(& $dev wp -Instance $Instance -WpArgs @('eval', $php, '--quiet')) -join [Environment]::NewLine
  try { return ($raw | ConvertFrom-Json) }
  catch { throw 'WP-CLI did not return the expected H2 fixture JSON.' }
}
function Invoke-StoreApi {
  param([ValidateSet('GET', 'POST')][string]$Method, [string]$Path, [object]$Body = $null, [switch]$Fresh)
  # Restrict every request to the public local Store API. Never POST checkout/pay.
  Assert-Check ($Path -match '^/(cart(?:/(?:add-item|update-customer|select-shipping-rate))?|checkout)$') 'Unexpected Store API endpoint.'
  Assert-Check ($Path -ne '/checkout' -or $Method -eq 'GET') 'Payment submission is outside local H2 verification.'
  # get_rest_url() supports H1's plain permalinks via index.php?rest_route=.
  $request = [Net.HttpWebRequest]::Create($apiBase + $Path)
  $request.Method = $Method
  $request.Accept = 'application/json'
  $request.UserAgent = 'H2-local-verification'
  $request.AllowAutoRedirect = $false
  $request.Timeout = 60000
  $request.ReadWriteTimeout = 60000
  $request.Proxy = $null
  if (-not $Fresh) {
    Assert-Check ([bool]$cartToken) 'A cart token is required for this request.'
    $request.Headers.Add('Cart-Token', $cartToken)
  }
  if ($null -ne $Body) {
    $bytes = [Text.Encoding]::UTF8.GetBytes(($Body | ConvertTo-Json -Depth 8 -Compress))
    $request.ContentType = 'application/json; charset=utf-8'
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
  }
  $response = $null
  try { $response = $request.GetResponse() }
  catch {
    $response = $_.Exception.Response
    if (-not $response -and $_.Exception.InnerException) { $response = $_.Exception.InnerException.Response }
    if (-not $response) { throw "Local Store API transport failed at $Path." }
  }
  try {
    $status = [int]$response.StatusCode
    $responseToken = [string]$response.Headers['Cart-Token']
    $reader = New-Object IO.StreamReader($response.GetResponseStream())
    try { $raw = $reader.ReadToEnd() } finally { $reader.Dispose() }
    try { $data = $raw | ConvertFrom-Json }
    catch { throw "Local Store API returned invalid JSON at $Path (HTTP $status)." }
    # Do not print/serialize this object: it may contain tokens, addresses and keys.
    return [pscustomobject]@{ StatusCode = $status; Data = $data; CartToken = $responseToken }
  }
  finally { $response.Dispose() }
}
function Assert-Success([object]$Response, [string]$Label) {
  Assert-Check ($Response.StatusCode -eq 200 -or $Response.StatusCode -eq 201) "$Label failed with HTTP $($Response.StatusCode)."
}
function Assert-Cart([object]$Cart, [int]$SimpleId, [int]$VariationId) {
  $items = @($Cart.items)
  Assert-Check ($items.Count -eq 2 -and [int]$Cart.items_count -eq 2) 'Cart must contain exactly two units in two lines.'
  foreach ($id in @($SimpleId, $VariationId)) {
    $line = @($items | Where-Object { [int]$_.id -eq $id })
    Assert-Check ($line.Count -eq 1 -and [int]$line[0].quantity -eq 1) 'Expected fixture quantity differs in the cart.'
  }
}

try {
  $before = Read-Fixtures
  Assert-Check ($before.woocommerce -eq '11.2.1') 'This verification records the native checkout behavior of WooCommerce 11.2.1.'
  $report.woocommerce = $before.woocommerce
  $siteUri = [Uri]$before.url
  Assert-Check ($siteUri.Scheme -eq 'http' -and $siteUri.Host -ceq 'localhost' -and $siteUri.AbsolutePath -eq '/' -and -not $siteUri.Query -and -not $siteUri.UserInfo) 'H2 HTTP verification requires the local localhost site root.'
  $configuredPort = @([IO.File]::ReadAllLines((Join-Path $privateRoot '.env')) | Where-Object { $_ -match '^LOCAL_WP_PORT=[0-9]+$' })
  Assert-Check ($configuredPort.Count -eq 1 -and $siteUri.Port -eq [int]($configuredPort[0].Split('=')[1])) 'WordPress URL does not match this local instance port.'
  $baseUrl = $siteUri.GetLeftPart([UriPartial]::Authority)
  $apiUri = [Uri]$before.rest_url
  Assert-Check ($apiUri.GetLeftPart([UriPartial]::Authority) -ceq $baseUrl -and -not $apiUri.UserInfo -and ([string]$before.rest_url -match '(?:/wp-json/wc/store/v1/?|/index\.php\?rest_route=/wc/store/v1/?)$')) 'WordPress REST base is not a supported local Store API route.'
  $apiBase = ([string]$before.rest_url).TrimEnd('/')
  $report.apiTransport = $(if ($apiUri.Query) { 'native rest_route for plain permalinks' } else { 'wp-json' })
  Assert-Check ([bool]$before.hpos) 'Enable HPOS before H2 HTTP verification.'
  $simple = $before.products.'H2-SIMPLE'
  $variableS = $before.products.'H2-VARIABLE-S-NEGRO'
  $variableM = $before.products.'H2-VARIABLE-M-NEGRO'
  Assert-Check ([int]$simple.stock -gt 0 -and [int]$variableS.stock -gt 0 -and [int]$variableM.stock -eq 0) 'H2 requires simple/S stock and an exhausted M variation; existing stock is never reset by this verification.'

  $stage = 'fresh_guest_cart'
  $initial = Invoke-StoreApi -Method GET -Path '/cart' -Fresh
  Assert-Success $initial 'Fresh cart'
  Assert-Check ([int]$initial.Data.items_count -eq 0 -and [bool]$initial.CartToken) 'Fresh guest cart or Cart-Token header is missing.'
  $cartToken = $initial.CartToken
  Pass 'Fresh guest Store API cart' @{ items = 0; tokenReceived = $true; authenticated = $false }

  $stage = 'add_simple_and_variation'
  $response = Invoke-StoreApi -Method POST -Path '/cart/add-item' -Body @{ id = [int]$simple.id; quantity = 1 }
  Assert-Success $response 'Simple add'
  $response = Invoke-StoreApi -Method POST -Path '/cart/add-item' -Body @{ id = [int]$variableS.id; quantity = 1; variation = @(@{ attribute = 'pa_talle'; value = 's' }, @{ attribute = 'pa_color'; value = 'negro' }) }
  Assert-Success $response 'Variation S add'
  Assert-Cart $response.Data ([int]$simple.id) ([int]$variableS.id)
  Pass 'Simple and variation S added' @{ lines = 2; units = 2; currency = $response.Data.totals.currency_code; itemsMinor = $response.Data.totals.total_items }

  $stage = 'cart_persistence_and_isolation'
  $persisted = Invoke-StoreApi -Method GET -Path '/cart'
  Assert-Success $persisted 'Persisted cart'
  Assert-Cart $persisted.Data ([int]$simple.id) ([int]$variableS.id)
  $separate = Invoke-StoreApi -Method GET -Path '/cart' -Fresh
  Assert-Success $separate 'Independent guest cart'
  Assert-Check ([int]$separate.Data.items_count -eq 0 -and [bool]$separate.CartToken -and $separate.CartToken -cne $cartToken) 'Independent guest request unexpectedly shared the cart.'
  Pass 'Cart persists by token and remains isolated from a new guest' @{ persistedUnits = 2; separateGuestUnits = 0 }

  $stage = 'stock_rejections'
  $empty = Invoke-StoreApi -Method POST -Path '/cart/add-item' -Body @{ id = [int]$variableM.id; quantity = 1; variation = @(@{ attribute = 'pa_talle'; value = 'm' }, @{ attribute = 'pa_color'; value = 'negro' }) }
  Assert-Check ($empty.StatusCode -eq 400 -and $empty.Data.code -eq 'woocommerce_rest_product_out_of_stock') 'Exhausted variation was not rejected with the expected stock error.'
  Pass 'Exhausted M variation rejected' @{ httpStatus = $empty.StatusCode; code = $empty.Data.code }
  $excess = Invoke-StoreApi -Method POST -Path '/cart/add-item' -Body @{ id = [int]$simple.id; quantity = ([int]$simple.stock + 1) }
  Assert-Check ($excess.StatusCode -eq 400 -and $excess.Data.code -eq 'woocommerce_rest_product_partially_out_of_stock') 'Quantity over stock was not rejected with the expected stock error.'
  Pass 'Quantity exceeding stock rejected' @{ httpStatus = $excess.StatusCode; code = $excess.Data.code }
  $response = Invoke-StoreApi -Method GET -Path '/cart'
  Assert-Success $response 'Cart after rejected adds'
  Assert-Cart $response.Data ([int]$simple.id) ([int]$variableS.id)

  $stage = 'shipping_customer_and_rates'
  # Clearly fictional buyer fixture, never store/legal identity or real contact data.
  $buyerAddress = @{ first_name = 'Prueba'; last_name = 'H2'; address_1 = 'Direccion ficticia de prueba 100'; city = 'Ciudad Autonoma de Buenos Aires'; state = 'C'; postcode = '1000'; country = 'AR' }
  $billingAddress = $buyerAddress.Clone()
  $billingAddress.email = 'h2-buyer@example.test'
  $response = Invoke-StoreApi -Method POST -Path '/cart/update-customer' -Body @{ billing_address = $billingAddress; shipping_address = $buyerAddress }
  Assert-Success $response 'Guest buyer update'
  $packages = @($response.Data.shipping_rates)
  Assert-Check ($packages.Count -eq 1) 'Expected one Argentina shipping package.'
  $rates = @($packages[0].shipping_rates)
  $flat = @($rates | Where-Object { $_.method_id -eq 'flat_rate' })
  $pickup = @($rates | Where-Object { $_.method_id -eq 'local_pickup' })
  Assert-Check ($flat.Count -eq 1 -and $pickup.Count -eq 1 -and [int]$flat[0].price -eq 50000 -and [int]$pickup[0].price -eq 0) 'Native Argentina shipping methods or fixture rates differ.'
  Pass 'Native Argentina shipping rates available' @{ currency = 'ARS'; flatRateMinor = [int]$flat[0].price; localPickupMinor = [int]$pickup[0].price }
  foreach ($rate in @($flat[0], $pickup[0])) {
    $response = Invoke-StoreApi -Method POST -Path '/cart/select-shipping-rate' -Body @{ package_id = [int]$packages[0].package_id; rate_id = [string]$rate.rate_id }
    Assert-Success $response 'Shipping selection'
    $selectedRates = @($response.Data.shipping_rates[0].shipping_rates | Where-Object { $_.selected })
    Assert-Check ($selectedRates.Count -eq 1 -and $selectedRates[0].rate_id -eq $rate.rate_id -and [int]$response.Data.totals.total_shipping -eq [int]$rate.price) 'Shipping selection did not update the cart totals.'
    Pass ('Native shipping selection: ' + $rate.method_id) @{ shippingMinor = [int]$response.Data.totals.total_shipping; totalMinor = [int]$response.Data.totals.total_price }
  }

  $stage = 'guest_checkout_preview'
  $checkout = Invoke-StoreApi -Method GET -Path '/checkout'
  Assert-Success $checkout 'Guest checkout GET'
  $draftId = [int]$checkout.Data.order_id
  $previewMethod = [string]$checkout.Data.payment_method
  $report.checkoutObserved = [ordered]@{ orderId = $draftId; status = [string]$checkout.Data.status; customerId = [int]$checkout.Data.customer_id; paymentMethod = $previewMethod }
  # WC 11.2.1 deliberately defers order creation until POST place-order. GET of
  # a fresh session returns a checkout-draft preview with order_id=0, no orphan.
  Assert-Check ($draftId -eq 0 -and $checkout.Data.status -eq 'checkout-draft' -and [int]$checkout.Data.customer_id -eq 0) 'Checkout did not return the expected unpaid native guest preview.'
  # WooCommerce may preselect the available gateway without creating an order
  # or submitting a payment. H2 permits only the official Checkout Pro gateway.
  Assert-Check (-not $previewMethod -or ($previewMethod -ceq 'woo-mercado-pago-basic' -and @($before.available_payment_methods) -ccontains $previewMethod)) 'Checkout preselected an unexpected or unavailable payment method.'
  $checkoutAgain = Invoke-StoreApi -Method GET -Path '/checkout'
  Assert-Success $checkoutAgain 'Repeated checkout GET'
  Assert-Check ([int]$checkoutAgain.Data.order_id -eq 0 -and $checkoutAgain.Data.status -eq 'checkout-draft' -and [int]$checkoutAgain.Data.customer_id -eq 0 -and [string]$checkoutAgain.Data.payment_method -ceq $previewMethod) 'Repeated GET changed the guest preview or unexpectedly persisted a checkout order.'
  Pass 'Native guest checkout preview defers order creation' @{ orderId = 0; status = 'checkout-draft'; customerId = 0; paymentMethod = $previewMethod; repeatedGetCreatesNoOrder = $true; paymentSubmitted = $false }

  $stage = 'stock_and_hpos_after_checkout'
  $after = Read-Fixtures
  Assert-Check ([bool]$after.hpos -and [int]$after.order_count -eq [int]$before.order_count) 'HPOS changed or fresh checkout GET unexpectedly persisted an order.'
  $stockSummary = [ordered]@{}
  foreach ($sku in @('H2-SIMPLE', 'H2-VARIABLE-S-NEGRO', 'H2-VARIABLE-M-NEGRO')) {
    Assert-Check ($before.products.$sku.stock -eq $after.products.$sku.stock) 'Cart/draft verification unexpectedly changed product stock.'
    $stockSummary[$sku] = [int]$after.products.$sku.stock
  }
  Pass 'HPOS, no new order and unchanged stock verified through WC APIs' @{ hpos = $true; ordersCreated = 0; stock = $stockSummary }
  $report.result = 'passed'
  $report.checkoutOrderPersisted = $false
  Save-Report
  Write-Host "H2 local HTTP checks passed. Private summary: .local/$Instance/h2-http.json"
}
catch {
  $report.result = 'failed'
  $report.failedStage = $stage
  if ($draftId -gt 0) { $report.draftOrderId = $draftId }
  Save-Report
  # Raw responses, headers, buyer addresses and keys must never reach console/logs.
  throw "H2 local HTTP verification failed at '$stage'. Private summary: .local/$Instance/h2-http.json. $($_.Exception.Message)"
}
finally {
  $cartToken = $null
  $initial = $null
  $separate = $null
  $checkout = $null
  $checkoutAgain = $null
}
