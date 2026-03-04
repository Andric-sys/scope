$ErrorActionPreference = "Stop"

$base = "http://127.0.0.1"
$query = "mode=incremental&size=200&max_pages=10&days=7&throttle_ms=120"
$urlCandidates = @(
  "$base/scope/scope/core_scope/scope_sync.php?$query",
  "$base/scope/core_scope/scope_sync.php?$query",
  "$base/core_scope/scope_sync.php?$query"
)

$logDir = "C:\core_scope\logs"
$logPath = Join-Path $logDir "scope_sync.log"
$timeoutSec = 60

if (-not (Test-Path $logDir)) {
  New-Item -Path $logDir -ItemType Directory -Force | Out-Null
}

function Write-Log {
  param([string]$Message)
  $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
  Add-Content -Path $logPath -Value "$stamp | $Message"
}

$selectedUrl = $null
foreach ($candidate in $urlCandidates) {
  try {
    $probe = Invoke-WebRequest -Uri $candidate -UseBasicParsing -TimeoutSec 20 -Headers @{ Accept = 'application/json' }
    if ($probe.StatusCode -ne 404) {
      $selectedUrl = $candidate
      break
    }
  } catch {
    $resp = $_.Exception.Response
    if ($null -ne $resp) {
      $statusCode = [int]$resp.StatusCode
      if ($statusCode -ne 404) {
        $selectedUrl = $candidate
        break
      }
    }
  }
}

if (-not $selectedUrl) {
  Write-Log "ERROR | No se encontró una URL válida de scope_sync.php (todas devolvieron 404)."
  exit 1
}

try {
  $r = Invoke-WebRequest -Uri $selectedUrl -UseBasicParsing -TimeoutSec $timeoutSec -MaximumRedirection 0 -Headers @{ Accept = 'application/json' }
  Write-Log "HTTP $($r.StatusCode) | URL=$selectedUrl | BODY=$($r.Content)"
} catch {
  $resp = $_.Exception.Response
  if ($null -ne $resp) {
    $statusCode = [int]$resp.StatusCode
    $location = $resp.Headers['Location']
    $body = ''
    try {
      $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
      $body = $reader.ReadToEnd()
      $reader.Dispose()
    } catch {}

    if ($statusCode -eq 401) {
      Write-Log "HTTP 401 | URL=$selectedUrl | No autenticado en CGL. BODY=$body"
    } elseif ($statusCode -eq 302) {
      Write-Log "HTTP 302 | URL=$selectedUrl | Redirigido a $location (probable sesión no iniciada)."
    } else {
      Write-Log "HTTP $statusCode | URL=$selectedUrl | BODY=$body"
    }
  } else {
    Write-Log "ERROR | URL=$selectedUrl | $($_.Exception.Message)"
  }
}