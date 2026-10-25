param(
    [string] $OutputDirectory = (Join-Path (Join-Path $PSScriptRoot '..') 'dist'),
    [string] $ExpectedVersion = ''
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$mainFile = Join-Path $pluginRoot 'auvrenth-health-monitor-connector.php'
$header = [System.IO.File]::ReadAllText($mainFile)
$versionMatch = [regex]::Match($header, '(?m)^\s*\*\s*Version:\s*([0-9.]+)')
if (-not $versionMatch.Success) { throw 'Connector version header is missing.' }
if ($ExpectedVersion -and $ExpectedVersion -ne $versionMatch.Groups[1].Value) {
    throw "Tag version $ExpectedVersion does not match plugin version $($versionMatch.Groups[1].Value)."
}

$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
[System.IO.Directory]::CreateDirectory($outputRoot) | Out-Null
$archivePath = Join-Path $outputRoot 'auvrenth-health-monitor-connector.zip'
if (Test-Path -LiteralPath $archivePath) { Remove-Item -LiteralPath $archivePath }
$names = @('auvrenth-health-monitor-connector.php', 'uninstall.php', 'readme.txt', 'README.md')
$archive = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($name in $names) {
        $source = Join-Path $pluginRoot $name
        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "Missing release file: $source" }
        $entryName = 'auvrenth-health-monitor-connector/' + $name
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $source, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}

Write-Output $archivePath
