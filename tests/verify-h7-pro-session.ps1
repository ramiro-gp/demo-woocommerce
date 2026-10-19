#requires -Version 7.0
[CmdletBinding()]
param([Parameter(Mandatory=$true)][ValidatePattern('^TESTUSER[0-9]+$')][string]$ExpectedTestSeller)
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot
$privateRoot = Join-Path $repoRoot '.local/local/h7-1/concurrency'
$null=New-Item -ItemType Directory -Force -Path $privateRoot
$state = Get-Content .local/local/h7-1/https/state.json -Raw | ConvertFrom-Json
if ($state.status -ne 'running' -or $state.publicUrl -notmatch '^https://[a-z0-9-]+\.trycloudflare\.com$') { throw 'Start verified H7 HTTPS first.' }
$checks = [ordered]@{}; $completed = $false
function Qa([string[]]$Arguments) {
  $raw = @(& "$repoRoot/scripts/dev.ps1" wp -WpArgs (@('eval-file','/opt/local-tools/h7-concurrency-qa.php') + $Arguments + @('--quiet'))) -join "`n"
  if ($LASTEXITCODE -ne 0) { throw 'Owned QA operation failed.' }
  return ($raw | ConvertFrom-Json)
}
function Check([string]$Name, [bool]$Passed) { $checks[$Name]=$Passed; if (!$Passed) { throw "H7 Pro check failed: $Name" } }
function Store([string]$Method, $Body = $null) {
  $parameters = @{Uri=($state.publicUrl + '/?rest_route=/wc/store/v1/checkout');Method=$Method;Headers=@{'Cart-Token'=$session.cart_token};SkipHttpErrorCheck=$true}
  if ($null -ne $Body) { $parameters.ContentType='application/json'; $parameters.Body=($Body | ConvertTo-Json -Compress -Depth 8) }
  $response=Invoke-WebRequest @parameters
  [IO.File]::WriteAllText((Join-Path $privateRoot 'pro-last-response.json'), $response.Content)
  return @{status=[int]$response.StatusCode;data=($response.Content | ConvertFrom-Json)}
}
try {
  $compose=@('compose','--project-name','demo-woocommerce-local','--env-file','.local/local/.env','--env-file','.local/local/h7-1/https/public.env','-f','compose.yaml','-f','compose.h7-https.yaml')
  $preflightRaw=@(& docker @compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-seller-check.php verify-linked-test-seller $ExpectedTestSeller --quiet) -join "`n"
  if ($LASTEXITCODE -ne 0 -or !($preflightRaw | ConvertFrom-Json).safe_to_start_test_checkout) { throw 'Verified expected fictional seller required.' }
  Check 'expected_test_seller_verified_before_preference' $true
  $initialCount=(Qa @('census')).count
  $initialStock=Qa @('stock')
  $session=Qa @('session','fresh')
  [IO.File]::WriteAllText((Join-Path $privateRoot 'pro-session.json'), ($session | ConvertTo-Json -Depth 6))
  $address=@{first_name='Prueba';last_name='Concurrencia';address_1='Calle Ficticia 123';address_2='';company='';city='Buenos Aires';state='C';postcode='1001';country='AR';phone='1123456789'}
  $billing=$address.Clone();$billing.email='h71.concurrency@example.test'
  $payload=@{billing_address=$billing;shipping_address=$address;payment_method='woo-mercado-pago-basic';customer_note='Prueba de preferencia H7.1; sin enviar pago.'}
  $first=Store 'POST' $payload
  Check 'native_pro_preference_created' ($first.status -eq 200 -and $first.data.payment_result.payment_status -eq 'success' -and $first.data.payment_method -eq 'woo-mercado-pago-basic')
  Check 'official_https_redirect' ($first.data.payment_result.redirect_url -match '^https://(?:www\.)?mercadopago\.(?:com|com\.ar)/')
  [IO.File]::WriteAllText((Join-Path $privateRoot 'pro-handoff.json'), ($first.data | ConvertTo-Json -Depth 10))
  $null=Qa @('adopt-pro',[string]$first.data.order_id)
  $retry=Store 'POST' $payload
  Check 'same_gateway_post_retry_preserves_native_order_id' ($retry.status -eq 200 -and $retry.data.order_id -eq $first.data.order_id)
  $null=Qa @('checkpoint-pro-retry',[string]$first.data.order_id)
  Check 'same_gateway_retry_preserves_order_and_payment_fields' $true
  $billing.first_name='Otra compra'
  $second=Store 'POST' $payload
  Check 'stale_tab_different_buyer_starts_new_pro_order' ($second.status -eq 200 -and $second.data.order_id -ne $first.data.order_id)
  $null=Qa @('adopt-pro',[string]$second.data.order_id)
  $null=Qa @('assert',[string]$first.data.order_id)
  Check 'first_pro_order_unchanged_after_second_pro_checkout' $true
  for($i=0;$i -lt 2;$i++) { $read=Store 'GET'; Check ('pro_checkout_refresh_' + $i) ($read.status -eq 200 -and $read.data.order_id -eq 0) }
  $patch=Store 'PATCH' @{payment_method='bacs'}
  Check 'bacs_selection_after_real_handoff_is_session_only' ($patch.status -eq 200 -and $patch.data.order_id -eq 0)
  $payload.payment_method='bacs'
  $bacs=Store 'POST' $payload
  Check 'native_bacs_is_third_distinct_purchase' ($bacs.status -eq 200 -and $bacs.data.order_id -ne $first.data.order_id -and $bacs.data.order_id -ne $second.data.order_id -and $bacs.data.status -eq 'on-hold')
  $null=Qa @('adopt',[string]$bacs.data.order_id)
  $null=Qa @('assert',[string]$first.data.order_id);$null=Qa @('assert',[string]$second.data.order_id)
  Check 'both_real_preference_orders_remain_unchanged' $true
  Check 'exactly_three_intentional_orders' (((Qa @('census')).count - $initialCount) -eq 3)
  $finalStock=Qa @('stock')
  Check 'only_bacs_reduces_physical_stock_once' ($finalStock.A -eq ($initialStock.A - 1) -and $finalStock.B -eq $initialStock.B)
  $completed=$true
} finally {
  $report=@{result=$(if($completed){'passed'}else{'failed'});checks=$checks;test_kind='Real official Pro preferences/test seller and native BACS over HTTPS; no payment submitted';order_ids=@($first.data.order_id,$second.data.order_id,$bacs.data.order_id);checked_at_utc=[DateTime]::UtcNow.ToString('o')}
  [IO.File]::WriteAllText((Join-Path $privateRoot 'pro-report.json'),($report | ConvertTo-Json -Depth 10))
}
$report | ConvertTo-Json -Depth 10
