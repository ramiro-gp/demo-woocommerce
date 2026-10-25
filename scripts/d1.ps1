#requires -Version 5.1
[CmdletBinding()]
param(
  [ValidateSet('install','wp','mode-on','mode-off','staging-on','local-on','audit','stop','inspect')][string]$Action='inspect',
  [ValidateSet('trama','origen')][string]$Brand='trama',
  [string[]]$WpArgs=@()
)
$ErrorActionPreference='Stop'
if(Test-Path variable:PSNativeCommandUseErrorActionPreference){$PSNativeCommandUseErrorActionPreference=$false}
$root=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot)); Set-Location -LiteralPath $root
$runtime=Join-Path $root ".local/d1/$Brand"; $envFile=Join-Path $runtime '.env'
$project="commerce-d1-$Brand"; $port=if($Brand -eq 'trama'){8190}else{8191}
function Compose([string[]]$Arguments){
  & docker compose --project-directory $root --project-name $project --env-file $envFile -f compose.d1.yaml @Arguments
  if($LASTEXITCODE -ne 0){throw 'D1 failed. Preserve this isolated lab and inspect before retrying.'}
}
function Wp([string[]]$Arguments){Compose (@('run','--rm','-T','--no-deps','cli','wp')+$Arguments)}
function Protect-Code {
  # Fixed paths inside this newly created D1 container only; uploads stay writable.
  Compose @('exec','-T','--user','0','web','sh','-c','chown 0:33 wp-content wp-content/themes wp-content/plugins wp-config.php; chmod 755 wp-content wp-content/themes wp-content/plugins; chmod 440 wp-config.php; chown -R 0:33 wp-content/themes/commerce-base wp-content/plugins; find wp-content/themes/commerce-base wp-content/plugins -type d -exec chmod 755 {} +; find wp-content/themes/commerce-base wp-content/plugins -type f -exec chmod 644 {} +')
}
function Set-Mode([int]$Mode,[string]$EnvironmentType=''){
  if(-not $EnvironmentType){
    $d1SavedEnvironment=Select-String -LiteralPath $envFile -Pattern '^D1_ENV_TYPE=(local|staging)$'
    $EnvironmentType=if($d1SavedEnvironment){$d1SavedEnvironment.Matches[0].Groups[1].Value}else{'local'}
  }
  [IO.File]::WriteAllText($envFile,"D1_BRAND=$Brand`nD1_PORT=$port`nD1_MODE=$Mode`nD1_ENV_TYPE=$EnvironmentType`n")
  Compose @('up','-d','--no-deps','web')
}
if($Action -eq 'install'){
  if(Test-Path -LiteralPath $envFile){throw 'D1 instance already exists. No reseeding or resetting is supported.'}
  $existing=@(& docker volume ls --filter "label=com.docker.compose.project=$project" --format '{{.Name}}')
  if($LASTEXITCODE -ne 0 -or $existing.Count){throw 'D1 requires an unused project and new volumes.'}
  $listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$port)
  try{$listener.Start()}finally{$listener.Stop()}
  foreach($dir in @('secrets','tools','private')){New-Item -ItemType Directory -Path "$runtime/$dir" -Force | Out-Null}
  $rng=[Security.Cryptography.RandomNumberGenerator]::Create()
  try{foreach($name in @('db','root','admin')){$bytes=New-Object byte[] 36;$rng.GetBytes($bytes);[IO.File]::WriteAllText("$runtime/secrets/$name.txt",[Convert]::ToBase64String($bytes))}}finally{$rng.Dispose()}
  [IO.File]::WriteAllText($envFile,"D1_BRAND=$Brand`nD1_PORT=$port`nD1_MODE=0`n")
  # Adapt only trusted, versioned content seed guards into private D1 copies.
  # Originals stay byte-for-byte unchanged and can never target another runtime.
  if($Brand -eq 'trama'){
    foreach($file in @('h4-demo-content.php','h5-catalog.php','h5-1-lookbook.php')){
      $source=[IO.File]::ReadAllText((Join-Path $root "docker/tools/$file"))
      $source=$source.Replace('http://localhost:8080',"http://localhost:$port")
      if($file -eq 'h5-catalog.php'){
        $start=$source.IndexOf("    `$backup = '/backups/'")
        $end=$source.IndexOf("    if (get_option('woocommerce_currency')",$start)
        if($start -lt 0 -or $end -le $start){throw 'Unexpected H5 source; review guard adaptation.'}
        $guard="    if (get_option('commerce_d1_installation') !== 'd1-public-demo-lab' || wc_get_orders(['limit'=>1]) || get_users(['role'=>'customer','number'=>1])) { WP_CLI::error('Only a clean claimed D1 lab is permitted.'); }`n"
        $source=$source.Substring(0,$start)+$guard+$source.Substring($end)
      }
      $guard="`nif (!defined('WP_CLI') || !WP_CLI || get_option('commerce_d1_installation') !== 'd1-public-demo-lab' || home_url() !== 'http://localhost:$port') { exit(1); }`n"
      $source=$source.Replace('<?php',('<?php'+$guard))
      [IO.File]::WriteAllText("$runtime/tools/$file",$source)
    }
  }
  Compose @('up','-d','--wait','--wait-timeout','180','db','web')
  Compose @('run','--rm','-T','--no-deps','cli','sh','-c','wp core install --url="$D1_URL" --title="D1 DEMO" --admin_user=d1_operator --admin_email=operator@demo.example.test --skip-email --prompt=admin_password < /run/secrets/admin > /tmp/d1-install.txt 2>&1 || { echo "Install failed; inspect private container log"; exit 1; }')
  Wp @('plugin','install','woocommerce','--version=11.2.1','--activate')
  Wp @('plugin','verify-checksums','woocommerce','--strict')
  Wp @('language','core','install','es_AR','--activate')
  Wp @('language','plugin','install','woocommerce','es_AR')
  $manifest=Get-Content '.local/h11-release/1.0.0/manifest.json' -Raw | ConvertFrom-Json
  $expected=@('commerce-base','commerce-core','commerce-communications','commerce-order-integrity','commerce-account-navigation','commerce-lookbook')
  if($manifest.distribution -ne 'commerce-clean' -or $manifest.version -ne '1.0.0' -or $manifest.demoContent -ne $false -or $manifest.credentials -ne $false -or @($manifest.packages).Count -ne 6 -or @(Compare-Object ($manifest.packages.slug | Sort-Object -Unique) ($expected | Sort-Object)).Count){throw 'Approved H11 manifest required.'}
  foreach($p in $manifest.packages){
    if($p.version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+$' -or $p.zip -cne "$($p.slug)-$($p.version).zip" -or $p.sha256 -cnotmatch '^[a-f0-9]{64}$'){throw 'Invalid approved package metadata.'}
    $zip=".local/h11-release/1.0.0/$($p.zip)"
    if((Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant() -ne $p.sha256){throw 'Approved ZIP checksum mismatch.'}
    $type=if($p.slug -eq 'commerce-base'){'theme'}else{'plugin'}
    Wp @($type,'install',"/workspace/$zip")
  }
  Wp @('theme','activate','commerce-base')
  Wp @('plugin','activate','commerce-core','commerce-lookbook','commerce-account-navigation','commerce-order-integrity','commerce-communications')
  # H11 deliberately excludes brand media; add only versioned editorial assets.
  Compose @('exec','-T','web','mkdir','-p','/var/www/html/wp-content/themes/commerce-base/assets/images')
  $imageFiles=@(& git ls-files -- commerce-base/assets/images)
  if($LASTEXITCODE -ne 0 -or -not $imageFiles.Count){throw 'Versioned editorial assets required.'}
  foreach($file in $imageFiles){
    if($file -notmatch '^commerce-base/assets/images/[A-Za-z0-9_.-]+\.(webp|svg)$' -or (Get-Item -LiteralPath $file -Force).Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Unsupported editorial asset path.'}
    & docker cp $file "${project}-web-1:/var/www/html/wp-content/themes/commerce-base/assets/images/"
    if($LASTEXITCODE -ne 0){throw 'Editorial asset copy failed.'}
  }
  if($Brand -eq 'origen'){
    Compose @('exec','-T','web','mkdir','-p','/var/www/html/wp-content/themes/commerce-base/styles')
    & docker cp './commerce-base/styles/origen-casa.json' "${project}-web-1:/var/www/html/wp-content/themes/commerce-base/styles/origen-casa.json"
    if($LASTEXITCODE -ne 0){throw 'ORIGEN fixture style copy failed.'}
  }
  Wp @('eval-file','/workspace/deployment/demo-lab/baseline.php')
  Wp @('rewrite','structure','/%postname%/','--hard')
  if($Brand -eq 'trama'){
    Wp @('eval-file','/opt/d1-tools/h4-demo-content.php','seed',"http://localhost:$port/")
    Wp @('eval-file','/opt/d1-tools/h5-catalog.php','seed')
    Wp @('eval-file','/opt/d1-tools/h5-1-lookbook.php','seed')
  }else{
    Wp @('option','update','commerce_h10_installation','origen-casa')
    Wp @('eval-file','/opt/local-tools/h10-store.php','seed')
  }
  Wp @('eval-file','/workspace/deployment/demo-public/setup.php')
  Wp @('rewrite','flush','--hard')
  Protect-Code
  Set-Mode 1
  Wp @('eval-file','/workspace/tests/d1-audit.php')
  return
}
if(-not(Test-Path -LiteralPath $envFile)){throw 'Install a new D1 lab first.'}
switch($Action){
  'wp'{if(-not $WpArgs.Count){throw 'Provide -WpArgs'};Wp $WpArgs}
  'audit'{Wp @('eval-file','/workspace/tests/d1-audit.php')}
  'mode-on'{Set-Mode 1}
  'mode-off'{Set-Mode 0}
  'staging-on'{Set-Mode 1 'staging'}
  'local-on'{Set-Mode 1 'local'}
  'stop'{Compose @('stop')}
  'inspect'{Compose @('ps')}
}
