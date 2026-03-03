$ErrorActionPreference = "SilentlyContinue"

# URL local (ajusta si tu XAMPP corre en otro puerto)
$url = "http://127.0.0.1/core_scope/scope_sync.php?mode=incremental&size=200&max_pages=10&days=7"

# Timeouts cortos para no colgarse
$timeoutSec = 60

try {
  $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec $timeoutSec
  $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
  $out = "$stamp | HTTP $($r.StatusCode) | $($r.Content)"

  Add-Content -Path "C:\core_scope\logs\scope_sync.log" -Value $out
} catch {
  $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
  Add-Content -Path "C:\core_scope\logs\scope_sync.log" -Value "$stamp | ERROR | $($_.Exception.Message)"
}