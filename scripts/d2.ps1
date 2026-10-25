#requires -Version 5.1
[CmdletBinding()]
param([ValidateSet('install','start','wp','inspect','stop')][string]$Action='inspect',[string[]]$WpArgs=@())
$ErrorActionPreference='Stop'
if(Test-Path variable:PSNativeCommandUseErrorActionPreference){$PSNativeCommandUseErrorActionPreference=$false}
$root=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot));Set-Location -LiteralPath $root
$runtime=Join-Path $root '.local/d2';$envFile=Join-Path $runtime '.env';$project='commerce-d2-bulk'
function Compose([string[]]$Arguments){& docker compose --project-directory $root --project-name $project --env-file $envFile -f compose.d2.yaml @Arguments;if($LASTEXITCODE -ne 0){throw 'D2 operation failed. Existing state preserved.'}}
function Wp([string[]]$Arguments){Compose (@('run','--rm','-T','--no-deps','cli','wp')+$Arguments)}
if($Action -eq 'install'){
  if(Test-Path -LiteralPath $envFile){throw 'New-instance install only; existing lab preserved.'}
  $existing=@(& docker volume ls --filter "label=com.docker.compose.project=$project" --format '{{.Name}}');if($LASTEXITCODE -ne 0 -or $existing.Count){throw 'Unused project required.'}
  foreach($p in @(8196,8197)){$listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$p);try{$listener.Start()}finally{$listener.Stop()}}
  foreach($dir in @('secrets','private','import')){New-Item -ItemType Directory -Path "$runtime/$dir" -Force | Out-Null}
  $rng=[Security.Cryptography.RandomNumberGenerator]::Create();try{foreach($name in @('db','root','admin','manager','customer')){$bytes=New-Object byte[] 36;$rng.GetBytes($bytes);[IO.File]::WriteAllText("$runtime/secrets/$name.txt",[Convert]::ToBase64String($bytes))}}finally{$rng.Dispose()}
  [IO.File]::WriteAllText($envFile,"D2_PORT=8196`nD2_MAIL_PORT=8197`n")
  Compose @('up','-d','--wait','--wait-timeout','180','db','web','mail')
  # Code, translations and public images only. No database, users, orders or private uploads.
  & docker run --rm --network none --user 0 --entrypoint sh -v 'commerce-d1-trama_wp:/source:ro' -v 'commerce-d2-bulk_wp:/target' wordpress:cli-2.12.0-php8.4 -c 'set -e; for item in themes/commerce-base plugins/woocommerce plugins/commerce-core plugins/commerce-communications plugins/commerce-order-integrity plugins/commerce-account-navigation plugins/commerce-lookbook languages; do cp -a /source/wp-content/$item /target/wp-content/$(dirname "$item")/; done; mkdir -p /target/wp-content/uploads/d2; find /source/wp-content/uploads -type f -name "*.webp" -print -quit | xargs -I IMG cp IMG /target/wp-content/uploads/d2/sample.webp; chown -R 33:33 /target/wp-content/uploads /target/wp-content/languages'
  if($LASTEXITCODE -ne 0){throw 'Public source file copy failed; no database fallback.'}
  Compose @('run','--rm','-T','--no-deps','cli','sh','-c','wp core install --url=http://localhost:8196 --title="D2 laboratorio CSV" --admin_user=d2_admin --admin_email=admin@bulk.example.test --admin_password="$(cat /run/secrets/admin)" --skip-email')
  Wp @('eval-file','/workspace/tests/d2-lab.php','initialize')
  Wp @('rewrite','flush','--hard')
  Write-Output 'D2 ready on loopback 8196. Mailpit 8197. Private access under .local/d2/private.'
  return
}
if(-not(Test-Path -LiteralPath $envFile)){throw 'Install a new D2 lab first.'}
switch($Action){'start'{Compose @('up','-d','--wait','db','web','mail')};'wp'{if(-not $WpArgs.Count){throw 'Provide WpArgs'};Wp $WpArgs};'inspect'{Compose @('ps')};'stop'{Compose @('stop')}}
