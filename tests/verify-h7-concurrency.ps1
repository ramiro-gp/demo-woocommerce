#requires -Version 7.0
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot
$privateRoot = Join-Path $repoRoot '.local/local/h7-1/concurrency'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
$checks = [ordered]@{}
$sessions = [System.Collections.Generic.List[object]]::new()
$completed = $false
function Qa([string[]]$Arguments) {
  $raw = @(& "$repoRoot/scripts/dev.ps1" wp -WpArgs (@('eval-file','/opt/local-tools/h7-concurrency-qa.php') + $Arguments + @('--quiet'))) -join "`n"
  if ($LASTEXITCODE -ne 0) { throw 'Owned QA assertion failed; provider details withheld.' }
  return ($raw | ConvertFrom-Json)
}
function New-QaSession([string]$Status = 'pending') {
  $session = Qa @('session', $Status)
  $sessions.Add($session)
  # Tokens/keys stay in the private runtime, never in reports or console output.
  [IO.File]::WriteAllText((Join-Path $privateRoot 'sessions.json'), ($sessions | ConvertTo-Json -Depth 8))
  return $session
}
function Call-Store($Session, [string]$Path, [string]$Method = 'GET', $Body = $null) {
  $parameters = @{Uri=('http://localhost:8080/?rest_route=/wc/store/v1/' + $Path); Method=$Method; Headers=@{'Cart-Token'=$Session.cart_token}; SkipHttpErrorCheck=$true}
  if ($null -ne $Body) { $parameters.ContentType='application/json'; $parameters.Body=($Body | ConvertTo-Json -Depth 12 -Compress) }
  $response = Invoke-WebRequest @parameters
  if ([int]$response.StatusCode -ge 400) { [IO.File]::WriteAllText((Join-Path $privateRoot 'last-error.json'), $response.Content) }
  return @{status=[int]$response.StatusCode; data=($response.Content | ConvertFrom-Json)}
}
function Check([string]$Name, [bool]$Passed) {
  $checks[$Name] = $Passed
  if (!$Passed) { throw "H7 concurrency check failed: $Name" }
}
function Unchanged($Session, [string]$Name) {
  $result = Qa @('assert', [string]$Session.order_id)
  Check $Name ($result.result -eq 'passed' -and $result.changed_fields.Count -eq 0)
}
function Place-Bacs($Session) {
  $address = @{first_name='Prueba';last_name='Concurrencia';address_1='Calle Ficticia 123';address_2='';company='';city='Buenos Aires';state='C';postcode='1001';country='AR';phone='1123456789'}
  $billing = $address.Clone(); $billing.email='h71.concurrency@example.test'
  $result = Call-Store $Session 'checkout' 'POST' @{payment_method='bacs'; billing_address=$billing;shipping_address=$address;customer_note='Prueba interna H7.1; no transferir dinero.'}
  Check ('native_bacs_http_' + $Session.order_id) ($result.status -eq 200 -and $result.data.payment_result.payment_status -eq 'success')
  return $result.data
}
function Refresh-Receipt($Order) {
  # Order keys are used only in private HTTP requests, never logged here.
  $url = 'http://localhost:8080/?page_id=9&order-received=' + $Order.order_id + '&key=' + [uri]::EscapeDataString($Order.order_key)
  for ($i=0; $i -lt 2; $i++) { $null = Invoke-WebRequest -Uri $url }
}
try {
  $null = Qa @('prepare')
  $hydration = Qa @('hydration')
  Check 'native_html_hydration_preserves_pending_order' ($hydration.result -eq 'passed' -and $hydration.full_order_unchanged -and $hydration.native_hydration_order_id -eq 0)
  $initialCount = (Qa @('census')).count
  $initialStock = Qa @('stock')
  $a = New-QaSession
  for ($i=0; $i -lt 2; $i++) {
    $read = Call-Store $a 'checkout'
    Check ('checkout_get_refresh_' + $i) ($read.status -eq 200 -and $read.data.order_id -eq 0)
  }
  $patch = Call-Store $a 'checkout' 'PATCH' @{payment_method='bacs'}
  Check 'select_bacs_does_not_reuse_pending' ($patch.status -eq 200 -and $patch.data.order_id -eq 0)
  Unchanged $a 'pending_unchanged_after_get_patch_abandon_return'
  $bacs = Place-Bacs $a
  Check 'second_purchase_gets_different_order' ($bacs.order_id -ne $a.order_id)
  $adopt = Qa @('adopt', [string]$bacs.order_id)
  Check 'bacs_on_hold_not_paid_stock_once' ($adopt.status -eq 'on-hold' -and !$adopt.paid -and $adopt.stock_reduced)
  Refresh-Receipt $bacs
  $null = Qa @('assert', [string]$bacs.order_id)
  Unchanged $a 'pending_unchanged_after_second_purchase_and_return_refresh'

  $direct = New-QaSession
  $directBacs = Place-Bacs $direct
  Check 'stale_tab_direct_bacs_post_gets_new_order' ($directBacs.order_id -ne $direct.order_id)
  $null = Qa @('adopt', [string]$directBacs.order_id)
  Unchanged $direct 'direct_post_preserves_previous_order'

  $tabs = New-QaSession
  $client = [Net.Http.HttpClient]::new()
  try {
    $client.DefaultRequestHeaders.Add('Cart-Token', [string]$tabs.cart_token)
    $tasks = @(1..2 | ForEach-Object { $client.GetAsync('http://localhost:8080/?rest_route=/wc/store/v1/checkout') })
    $tabReads = @($tasks | ForEach-Object { $response=$_.GetAwaiter().GetResult(); try { @{status=[int]$response.StatusCode; data=($response.Content.ReadAsStringAsync().GetAwaiter().GetResult() | ConvertFrom-Json)} } finally { $response.Dispose() } })
    Check 'two_tabs_concurrent_gets_no_order_created' (@($tabReads | Where-Object { $_.status -ne 200 -or $_.data.order_id -ne 0 }).Count -eq 0)
  } finally { $client.Dispose() }
  $cart = Call-Store $tabs 'cart'
  $oldKey = $cart.data.items[0].key
  $null = Call-Store $tabs 'cart/add-item' 'POST' @{id=$tabs.products.B; quantity=1}
  $null = Call-Store $tabs 'cart/remove-item' 'POST' @{key=$oldKey}
  $tabBacs = Place-Bacs $tabs
  Check 'changed_cart_gets_new_order' ($tabBacs.order_id -ne $tabs.order_id)
  $null = Qa @('adopt', [string]$tabBacs.order_id)
  Unchanged $tabs 'two_tabs_cart_change_preserves_original_items_metadata'

  $independent1 = New-QaSession
  $independent2 = New-QaSession
  Check 'independent_guest_sessions' ($independent1.cart_token -ne $independent2.cart_token)
  $null = Call-Store $independent1 'checkout'
  $independentBacs = Place-Bacs $independent2
  Check 'independent_second_order' ($independentBacs.order_id -ne $independent1.order_id -and $independentBacs.order_id -ne $independent2.order_id)
  $null = Qa @('adopt', [string]$independentBacs.order_id)
  Unchanged $independent1 'other_session_cannot_mutate_first_order'
  Unchanged $independent2 'other_session_pending_preserved'

  $retry = New-QaSession
  $retryAddress = @{first_name='Prueba';last_name='Concurrencia';address_1='Calle Ficticia 123';city='Buenos Aires';state='C';postcode='1001';country='AR';email=$retry.email;phone='1123456789'}
  $invalid = Call-Store $retry ('checkout/' + $retry.order_id) 'POST' @{key='invalid'; billing_email=$retry.email; billing_address=$retryAddress;payment_method='bacs'}
  Check 'pay_existing_wrong_key_rejected' ($invalid.status -eq 401)
  Unchanged $retry 'unauthorized_retry_cannot_change_order'
  $valid = Call-Store $retry ('checkout/' + $retry.order_id) 'POST' @{key=$retry.order_key; billing_email=$retry.email; billing_address=$retryAddress;payment_method='bacs'}
  Check 'authorized_native_pay_existing_can_change_gateway' ($valid.status -eq 200 -and $valid.data.order_id -eq $retry.order_id -and $valid.data.payment_method -eq 'bacs' -and $valid.data.status -eq 'on-hold')
  $null = Qa @('checkpoint-bacs', [string]$retry.order_id)
  Refresh-Receipt $valid.data
  Unchanged $retry 'authorized_retry_return_refresh_no_second_stock_reduction'

  $failed = New-QaSession 'failed'
  $readFailed = Call-Store $failed 'checkout'
  Check 'failed_checkout_native_retry_retained' ($readFailed.status -eq 200 -and $readFailed.data.order_id -eq $failed.order_id)
  $failedBacs = Place-Bacs $failed
  Check 'failed_retry_does_not_create_duplicate' ($failedBacs.order_id -eq $failed.order_id)
  $null = Qa @('checkpoint-bacs', [string]$failed.order_id)
  Refresh-Receipt $failedBacs
  Unchanged $failed 'failed_retry_refresh_no_second_stock_reduction'
  Check 'exactly_eleven_deliberate_orders_no_extra_duplicates' (((Qa @('census')).count - $initialCount) -eq 11)
  $finalStock = Qa @('stock')
  Check 'physical_stock_exactly_five_A_and_one_B_no_double_reduction' ($finalStock.A -eq ($initialStock.A - 5) -and $finalStock.B -eq ($initialStock.B - 1))
  $completed = $true
} finally {
  $report = @{result= $(if ($completed -and !($checks.Values -contains $false)) {'passed'} else {'failed'}); checks=$checks; checked_at_utc=[DateTime]::UtcNow.ToString('o'); test_kind='Internal HTTP Store API with synthetic MP pending orders; native BACS, no MP payment created'; historical_orders_deleted=$false}
  [IO.File]::WriteAllText((Join-Path $privateRoot 'report.json'), ($report | ConvertTo-Json -Depth 10))
}
$report | ConvertTo-Json -Depth 10
