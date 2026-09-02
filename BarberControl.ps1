[CmdletBinding()]
param(
    [switch] $NoBrowser
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
$Php = Join-Path $ProjectRoot 'runtime\php\php.exe'
$Artisan = Join-Path $ProjectRoot 'artisan'
$PublicDirectory = Join-Path $ProjectRoot 'public'
$Router = Join-Path $ProjectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$LogDirectory = Join-Path $ProjectRoot 'storage\logs'
$StateDirectory = Join-Path $ProjectRoot 'storage\framework'
$StateFile = Join-Path $StateDirectory 'barbercontrol-launcher.json'
$Port = 8088
$LocalUrl = "http://127.0.0.1:$Port"

function Write-Heading([string] $Text) {
    Write-Host ''
    Write-Host $Text -ForegroundColor DarkYellow
    Write-Host ('=' * $Text.Length) -ForegroundColor DarkYellow
}

function Test-ProcessRunning([int] $ProcessId) {
    return $null -ne (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue)
}

function Stop-ManagedProcesses($State) {
    $processIds = @($State.webPid, $State.schedulerPid, $State.queuePid) |
        Where-Object { $_ -and [int] $_ -gt 0 } |
        Select-Object -Unique

    foreach ($processId in $processIds) {
        if (Test-ProcessRunning ([int] $processId)) {
            Stop-Process -Id ([int] $processId) -Force -ErrorAction SilentlyContinue
        }
    }

    Start-Sleep -Milliseconds 500

    if (Test-Path -LiteralPath $StateFile) {
        Remove-Item -LiteralPath $StateFile -Force
    }
}

function Get-LauncherState {
    if (-not (Test-Path -LiteralPath $StateFile)) {
        return $null
    }

    try {
        return Get-Content -LiteralPath $StateFile -Raw | ConvertFrom-Json
    } catch {
        Remove-Item -LiteralPath $StateFile -Force -ErrorAction SilentlyContinue
        return $null
    }
}

function Test-PortInUse([int] $PortNumber) {
    $listeners = netstat -ano -p tcp | Select-String -Pattern (":$PortNumber\s+.*LISTENING")
    return $null -ne $listeners
}

function Get-LanAddress {
    try {
        foreach ($networkInterface in [System.Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces()) {
            if ($networkInterface.OperationalStatus -ne [System.Net.NetworkInformation.OperationalStatus]::Up) {
                continue
            }

            if ($networkInterface.NetworkInterfaceType -eq [System.Net.NetworkInformation.NetworkInterfaceType]::Loopback) {
                continue
            }

            $properties = $networkInterface.GetIPProperties()
            if ($properties.GatewayAddresses.Count -eq 0) {
                continue
            }

            $address = $properties.UnicastAddresses |
                Where-Object {
                    $_.Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork -and
                    $_.Address.IPAddressToString -notlike '169.254.*'
                } |
                Select-Object -First 1

            if ($address) {
                return $address.Address.IPAddressToString
            }
        }
    } catch {
        # La direccion de red es informativa; no impide iniciar la aplicacion.
    }

    return $null
}

function Start-HiddenPhp([string[]] $Arguments, [string] $WorkingDirectory, [string] $LogName) {
    return Start-Process -FilePath $Php `
        -ArgumentList $Arguments `
        -WorkingDirectory $WorkingDirectory `
        -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $LogDirectory "$LogName.log") `
        -RedirectStandardError (Join-Path $LogDirectory "$LogName-error.log") `
        -PassThru
}

Write-Heading 'BarberControl'

$state = Get-LauncherState
if ($state) {
    $managedIsRunning = @($state.webPid, $state.schedulerPid, $state.queuePid) |
        Where-Object { $_ -and (Test-ProcessRunning ([int] $_)) } |
        Select-Object -First 1

    if ($managedIsRunning) {
        Write-Host 'Deteniendo servidor y tareas automaticas...'
        Stop-ManagedProcesses $state
        Write-Host 'BarberControl se detuvo correctamente.' -ForegroundColor Green
        exit 0
    }

    Remove-Item -LiteralPath $StateFile -Force -ErrorAction SilentlyContinue
}

$requiredFiles = @(
    $Php,
    $Artisan,
    (Join-Path $ProjectRoot '.env'),
    (Join-Path $ProjectRoot 'vendor\autoload.php'),
    (Join-Path $PublicDirectory 'index.php'),
    $Router
)

$missingFiles = $requiredFiles | Where-Object { -not (Test-Path -LiteralPath $_) }
if ($missingFiles) {
    Write-Host 'La instalacion esta incompleta. Faltan estos archivos:' -ForegroundColor Red
    $missingFiles | ForEach-Object { Write-Host " - $_" }
    exit 1
}

if (Test-PortInUse $Port) {
    Write-Host "El puerto $Port ya esta ocupado por otro programa." -ForegroundColor Red
    Write-Host 'Cierra el servidor anterior y vuelve a presionar este boton.'
    exit 1
}

New-Item -ItemType Directory -Path $LogDirectory -Force | Out-Null
New-Item -ItemType Directory -Path $StateDirectory -Force | Out-Null

Write-Host 'Iniciando servidor, recordatorios y cola de mensajes...'

$startedProcesses = @()

try {
    $web = Start-HiddenPhp @('-S', "0.0.0.0:$Port", '..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php') $PublicDirectory 'barbercontrol-web'
    $startedProcesses += $web

    $scheduler = Start-HiddenPhp @('artisan', 'schedule:work') $ProjectRoot 'barbercontrol-scheduler'
    $startedProcesses += $scheduler

    $queue = Start-HiddenPhp @('artisan', 'queue:work', '--sleep=3', '--tries=3', '--timeout=90') $ProjectRoot 'barbercontrol-queue'
    $startedProcesses += $queue

    $newState = [ordered]@{
        webPid = $web.Id
        schedulerPid = $scheduler.Id
        queuePid = $queue.Id
        port = $Port
        startedAt = (Get-Date).ToString('o')
        projectRoot = $ProjectRoot
    }

    $newState | ConvertTo-Json | Set-Content -LiteralPath $StateFile -Encoding UTF8

    $isReady = $false
    for ($attempt = 1; $attempt -le 12; $attempt++) {
        Start-Sleep -Milliseconds 500

        if (-not (Test-ProcessRunning $web.Id)) {
            break
        }

        try {
            $response = Invoke-WebRequest -Uri "$LocalUrl/up" -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) {
                $isReady = $true
                break
            }
        } catch {
            # Laravel aun esta iniciando.
        }
    }

    if (-not $isReady) {
        throw "El servidor no respondio. Revisa $LogDirectory\barbercontrol-web-error.log"
    }

    $lanAddress = Get-LanAddress

    Write-Host ''
    Write-Host 'BarberControl esta funcionando.' -ForegroundColor Green
    Write-Host "Esta computadora: $LocalUrl"
    if ($lanAddress) {
        Write-Host "Red local:        http://${lanAddress}:$Port"
    }
    Write-Host ''
    Write-Host 'Para detenerlo, vuelve a abrir BarberControl.cmd.' -ForegroundColor Yellow

    if (-not $NoBrowser) {
        Start-Process $LocalUrl
    }
    exit 0
} catch {
    foreach ($process in $startedProcesses) {
        if ($process -and (Test-ProcessRunning $process.Id)) {
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        }
    }

    Remove-Item -LiteralPath $StateFile -Force -ErrorAction SilentlyContinue
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}
