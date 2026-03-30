$ErrorActionPreference = 'Stop'

param(
    [string] $Repository = '',
    [string] $Username = '',
    [string] $ApiToken = '',
    [switch] $Force
)

function Get-GitHubCliPath {
    $gh = Get-Command gh -ErrorAction SilentlyContinue
    if ($gh) {
        return $gh.Source
    }

    $fallback = 'C:\Program Files\GitHub CLI\gh.exe'
    if (Test-Path $fallback) {
        return $fallback
    }

    throw 'GitHub CLI (gh) was not found. Install gh and authenticate before running this script.'
}

function Resolve-Repository {
    param([string] $CurrentValue)

    if (-not [string]::IsNullOrWhiteSpace($CurrentValue)) {
        return $CurrentValue.Trim()
    }

    $remote = git remote get-url origin 2>$null
    if ([string]::IsNullOrWhiteSpace($remote)) {
        throw 'Repository was not provided and origin remote could not be resolved.'
    }

    $remote = $remote.Trim()

    if ($remote -match 'github\.com[:/](?<repo>[^/]+/[^/.]+?)(?:\.git)?$') {
        return $Matches['repo']
    }

    throw "Unable to parse GitHub repository slug from remote: $remote"
}

function Resolve-Username {
    param(
        [string] $CurrentValue,
        [string] $RepositorySlug
    )

    if (-not [string]::IsNullOrWhiteSpace($CurrentValue)) {
        return $CurrentValue.Trim()
    }

    if (-not [string]::IsNullOrWhiteSpace($env:PACKAGIST_USERNAME)) {
        return $env:PACKAGIST_USERNAME.Trim()
    }

    return $RepositorySlug.Split('/')[0]
}

function Resolve-ApiToken {
    param([string] $CurrentValue)

    if (-not [string]::IsNullOrWhiteSpace($CurrentValue)) {
        return $CurrentValue.Trim()
    }

    if (-not [string]::IsNullOrWhiteSpace($env:PACKAGIST_API_TOKEN)) {
        return $env:PACKAGIST_API_TOKEN.Trim()
    }

    throw 'Packagist API token is required. Pass -ApiToken or set PACKAGIST_API_TOKEN.'
}

$gh = Get-GitHubCliPath
$Repository = Resolve-Repository -CurrentValue $Repository
$Username = Resolve-Username -CurrentValue $Username -RepositorySlug $Repository
$ApiToken = Resolve-ApiToken -CurrentValue $ApiToken
$payloadUrl = "https://packagist.org/api/github?username=$Username"

$hooks = & $gh api "repos/$Repository/hooks" | ConvertFrom-Json
$existing = $hooks | Where-Object { $_.config.url -eq $payloadUrl } | Select-Object -First 1

$body = @{
    active = $true
    events = @('push')
    config = @{
        url = $payloadUrl
        content_type = 'json'
        secret = $ApiToken
        insecure_ssl = '0'
    }
}

if (-not $existing) {
    $body.name = 'web'
}

$json = $body | ConvertTo-Json -Depth 6 -Compress

if ($existing -and -not $Force) {
    Write-Output "Packagist webhook already exists for $Repository"
    Write-Output 'Use -Force if you want to update the stored Packagist API token.'
    exit 0
}

if ($existing) {
    $result = $json | & $gh api "repos/$Repository/hooks/$($existing.id)" --method PATCH --input -
    $action = 'updated'
} else {
    $result = $json | & $gh api "repos/$Repository/hooks" --method POST --input -
    $action = 'created'
}

$hook = $result | ConvertFrom-Json
Write-Output "Packagist webhook $action for $Repository"
Write-Output "Payload URL: $payloadUrl"
Write-Output "Hook ID: $($hook.id)"
