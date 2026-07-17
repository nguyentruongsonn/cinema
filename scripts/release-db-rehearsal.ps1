param(
    [string] $DatabaseName
)

$ErrorActionPreference = 'Stop'

function Get-DotEnvValue {
    param([string] $Name)

    $line = Get-Content .env | Where-Object { $_ -match "^$Name=" } | Select-Object -First 1

    if (-not $line) {
        return $null
    }

    return ($line -replace "^$Name=", '').Trim('"').Trim("'")
}

function Invoke-PhpDatabaseCommand {
    param(
        [string] $Database,
        [string] $Action
    )

    if ($Database -notmatch '^[A-Za-z0-9_]+$') {
        throw "Unsafe database name: $Database"
    }

    $env:REHEARSAL_DB_HOST = Get-DotEnvValue 'DB_HOST'
    $env:REHEARSAL_DB_PORT = Get-DotEnvValue 'DB_PORT'
    $env:REHEARSAL_DB_USER = Get-DotEnvValue 'DB_USERNAME'
    $env:REHEARSAL_DB_PASS = Get-DotEnvValue 'DB_PASSWORD'
    $env:REHEARSAL_DB_NAME = $Database
    $env:REHEARSAL_DB_ACTION = $Action

    @'
<?php
$host = getenv('REHEARSAL_DB_HOST') ?: '127.0.0.1';
$port = getenv('REHEARSAL_DB_PORT') ?: '3306';
$user = getenv('REHEARSAL_DB_USER') ?: 'root';
$pass = getenv('REHEARSAL_DB_PASS') ?: '';
$database = getenv('REHEARSAL_DB_NAME');
$action = getenv('REHEARSAL_DB_ACTION');

if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Unsafe database name\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$quoted = "`" . str_replace("`", "``", $database) . "`";

if ($action === 'create') {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Created rehearsal database {$database}\n";
    exit(0);
}

if ($action === 'drop') {
    $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
    echo "Dropped rehearsal database {$database}\n";
    exit(0);
}

fwrite(STDERR, "Unknown database action\n");
exit(1);
'@ | php
}

$baseDatabase = Get-DotEnvValue 'DB_DATABASE'

if (-not $baseDatabase) {
    throw 'DB_DATABASE is missing from .env'
}

$safeBaseName = ($baseDatabase -replace '[^A-Za-z0-9_]', '_')
$timestamp = Get-Date -Format 'yyyyMMddHHmmss'
$tempDatabase = if ($DatabaseName) { $DatabaseName } else { "${safeBaseName}_release_rehearsal_${timestamp}" }

Write-Host "Starting migration/rollback rehearsal on temporary database: $tempDatabase"

$previousDatabase = [Environment]::GetEnvironmentVariable('DB_DATABASE', 'Process')
$previousAppEnv = [Environment]::GetEnvironmentVariable('APP_ENV', 'Process')

try {
    Invoke-PhpDatabaseCommand -Database $tempDatabase -Action create

    $env:DB_DATABASE = $tempDatabase
    $env:APP_ENV = 'local'

    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'config:clear failed' }

    & php artisan migrate:fresh --seed --force
    if ($LASTEXITCODE -ne 0) { throw 'migrate:fresh --seed failed' }

    & php artisan migrate:rollback --force
    if ($LASTEXITCODE -ne 0) { throw 'migrate:rollback failed' }

    Write-Host 'Migration/rollback rehearsal passed.'
} finally {
    if ($null -eq $previousDatabase) {
        Remove-Item Env:\DB_DATABASE -ErrorAction SilentlyContinue
    } else {
        $env:DB_DATABASE = $previousDatabase
    }

    if ($null -eq $previousAppEnv) {
        Remove-Item Env:\APP_ENV -ErrorAction SilentlyContinue
    } else {
        $env:APP_ENV = $previousAppEnv
    }

    Invoke-PhpDatabaseCommand -Database $tempDatabase -Action drop
}
