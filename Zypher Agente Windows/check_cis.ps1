$tempFile = Join-Path $env:TEMP "secpol_cis.cfg"
secedit /export /cfg $tempFile | Out-Null
$content = Get-Content $tempFile
Remove-Item $tempFile -Force -ErrorAction SilentlyContinue

function Get-SecValue($name) {
    $line = $content | Where-Object { $_ -match "^$name\s*=" } | Select-Object -First 1
    if ($line) {
        return [int](($line -split '=')[1].Trim())
    }
    return $null
}

$results = @()

$passwordHistory = Get-SecValue "PasswordHistorySize"
$results += @{
    id_cis = 26000
    estado = if ($passwordHistory -ge 24) { "completado" } else { "no completado" }
}

$minimumPasswordAge = Get-SecValue "MinimumPasswordAge"
$results += @{
    id_cis = 26002
    estado = if ($minimumPasswordAge -ge 1) { "completado" } else { "no completado" }
}

$minimumPasswordLength = Get-SecValue "MinimumPasswordLength"
$results += @{
    id_cis = 26003
    estado = if ($minimumPasswordLength -ge 14) { "completado" } else { "no completado" }
}

$results | ConvertTo-Json -Compress