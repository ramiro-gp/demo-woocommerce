#requires -Version 5.1
[CmdletBinding()]
param([ValidateSet('install','wp','inspect','stop')][string]$Action='inspect',[ValidateSet('trama','origen')][string]$Brand='trama',[string[]]$WpArgs=@())
$ErrorActionPreference='Stop'
if(Test-Path variable:PSNativeCommandUseErrorActionPreference){$PSNativeCommandUseErrorActionPreference=$false}
$root=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot));Set-Location -LiteralPath $root
$runtime=Join-Path $root ".local/d1-seller/$Brand";$envFile=Join-Path $runtime '.env';$project="commerce-d1-seller-$Brand"
$port=if($Brand -eq 'trama'){8192}else{8193};$mailPort=if($Brand -eq 'trama'){8194}else{8195}
function Compose([string[]]$Arguments){& docker compose --project-directory $root --project-name $project --env-file $envFile -f compose.d1-seller.yaml @Arguments;if($LASTEXITCODE -ne 0){throw 'Seller lab failed; preserve state and inspect. No automatic deletion or retry.'}}
function Wp([string[]]$Arguments){Compose (@('run','--rm','-T','--no-deps','cli','wp')+$Arguments)}
if($Action -eq 'install'){
  if(Test-Path -LiteralPath $envFile){throw 'Existing seller lab preserved; install is new-instance only.'}
  $existing=@(& docker volume ls --filter "label=com.docker.compose.project=$project" --format '{{.Name}}');if($LASTEXITCODE -ne 0 -or $existing.Count){throw 'Unused seller project/volumes required.'}
  foreach($p in @($port,$mailPort)){$listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$p);try{$listener.Start()}finally{$listener.Stop()}}
  foreach($dir in @('secrets','private','import')){New-Item -ItemType Directory -Path "$runtime/$dir" -Force | Out-Null}
  $rng=[Security.Cryptography.RandomNumberGenerator]::Create();try{foreach($name in @('db','root','admin','manager','customer')){$bytes=New-Object byte[] 36;$rng.GetBytes($bytes);[IO.File]::WriteAllText("$runtime/secrets/$name.txt",[Convert]::ToBase64String($bytes))}}finally{$rng.Dispose()}
  [IO.File]::WriteAllText($envFile,"SELLER_BRAND=$Brand`nSELLER_PORT=$port`nSELLER_MAIL_PORT=$mailPort`n")
  # Only a clean D1 fixture source is allowed. Never attach 8080/8082 historical data.
  & docker compose --project-directory $root --project-name "commerce-d1-$Brand" --env-file ".local/d1/$Brand/.env" -f compose.d1.yaml up -d --wait db web
  if($LASTEXITCODE -ne 0){throw 'Existing public D1 fixture source unavailable; no restriction is removed.'}
  & .\scripts\d1.ps1 -Action audit -Brand $Brand | Out-Null
  $sourceProject="commerce-d1-$Brand";$sourceVolume="${sourceProject}_wp";$targetVolume="${project}_wp"
  Compose @('up','-d','--wait','--wait-timeout','180','db','web','mail')
  & docker run --rm --network none --user 0 --entrypoint sh -v "${sourceVolume}:/source:ro" -v "${targetVolume}:/target" wordpress:cli-2.12.0-php8.4 -c 'set -e; for item in themes/commerce-base plugins/woocommerce plugins/commerce-core plugins/commerce-communications plugins/commerce-order-integrity plugins/commerce-account-navigation plugins/commerce-lookbook languages; do cp -a /source/wp-content/$item /target/wp-content/$(dirname "$item")/; done; tar -C /source/wp-content --exclude=uploads/wc-logs --exclude=uploads/woocommerce_uploads -cf - uploads | tar -C /target/wp-content -xf -; chown -R 33:33 /target/wp-content/uploads /target/wp-content/languages'
  if($LASTEXITCODE -ne 0){throw 'Read-only D1 source copy failed.'}
  $sourceDbSecret=Join-Path $root ".local/d1/$Brand/secrets/db.txt";$importDir=Join-Path $runtime 'import'
  & docker run --rm --user 33:33 --network "${sourceProject}_default" --entrypoint wp -v "${sourceVolume}:/var/www/html:ro" -v "${importDir}:/export" -v "${sourceDbSecret}:/run/secrets/db:ro" -e WORDPRESS_DB_HOST=db -e "WORDPRESS_DB_NAME=d1_$Brand" -e WORDPRESS_DB_USER=d1 -e WORDPRESS_DB_PASSWORD_FILE=/run/secrets/db wordpress:cli-2.12.0-php8.4 db export /export/source.sql --single-transaction --skip-ssl
  if($LASTEXITCODE -ne 0){throw 'Clean D1 SQL export failed.'}
  # Explicit fixed database name via native client; avoids WP-CLI SQL-mode TLS probing.
  Compose @('run','--rm','-T','--no-deps','cli','sh','-c',"MYSQL_PWD=`"`$(cat /run/secrets/db)`" mariadb --skip-ssl --host=db --user=seller seller_$Brand < /seller-import/source.sql")
  $sourcePort=if($Brand -eq 'trama'){8190}else{8191}
  Wp @('search-replace',"http://localhost:$sourcePort","http://localhost:$port",'--all-tables-with-prefix','--skip-columns=guid','--precise')
  Wp @('eval-file','/workspace/deployment/seller-lab/initialize.php')
  Wp @('rewrite','flush','--hard')
  Write-Output 'Seller lab ready. Credentials are only in ignored .local/d1-seller/<brand>/private/access.json.'
  return
}
if(-not(Test-Path -LiteralPath $envFile)){throw 'Install a new seller lab first.'}
switch($Action){'wp'{if(-not $WpArgs.Count){throw 'Provide WpArgs'};Wp $WpArgs};'inspect'{Compose @('ps')};'stop'{Compose @('stop')}}
