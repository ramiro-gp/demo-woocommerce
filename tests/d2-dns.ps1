# Public read-only DNS checks. Internal hosting allocation must be checked separately.
$ErrorActionPreference='Stop';$queries=@()
$servers=@('1.1.1.1','aster.dns-parking.com','helios.dns-parking.com')
foreach($server in $servers){
  foreach($name in @('ramita.dev','trama-demo.ramita.dev','origen-demo.ramita.dev','d2-unallocated-probe-20261025.ramita.dev')){
    $types=if($name -eq 'ramita.dev'){@('NS')}else{@('A','AAAA','CNAME')}
    foreach($type in $types){try{
      $answer=Resolve-DnsName -Name $name -Type $type -Server $server -DnsOnly -ErrorAction Stop
      $queries+=@{server=$server;name=$name;type=$type;status='ANSWER';answer=@($answer|Select-Object Name,Type,IPAddress,NameHost,Strings,Data)}
    }catch{$queries+=@{server=$server;name=$name;type=$type;status=$_.Exception.Message}}}
  }
}
@{checkedAtUtc=[DateTime]::UtcNow.ToString('o');method='Read-only DNS: recursive resolver plus both public authoritative nameservers';accountWebsitesVerified=$false;accountDnsZoneVerified=$false;queries=$queries;interpretation='No public record does not prove an unused hosting name. Check websites, zone, wildcard and delegated subzones inside the account before creation.'}|ConvertTo-Json -Depth 8|Set-Content docs/evidence/d2/dns.json
# Windows Resolve-DnsName does not implement CAA. Use Node's native DNS resolver.
& node (Join-Path $PSScriptRoot 'd2-caa.cjs')
if($LASTEXITCODE -ne 0){throw 'CAA read failed; keep its status pending.'}
