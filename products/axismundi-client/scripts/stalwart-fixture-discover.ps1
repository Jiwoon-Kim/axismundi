# Read-only discovery for the multi-account / multi-identity Stalwart fixture.
# Prints principal and domain metadata only; never prints secrets or credentials.
param(
    [string]$SessionUrl = 'https://localhost:18881/.well-known/jmap',
    [string]$AdminUser = 'admin'
)
$ErrorActionPreference = 'Stop'
$secret = Read-Host -AsSecureString "Stalwart password for '$AdminUser'"
$plain = [System.Net.NetworkCredential]::new('', $secret).Password
$auth = @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("${AdminUser}:$plain")) }
$plain = $null

$session = Invoke-RestMethod -Uri $SessionUrl -Headers $auth -PreserveAuthorizationOnRedirect
Write-Host "apiUrl: $($session.apiUrl)"
Write-Host "session capabilities: $(($session.capabilities.PSObject.Properties.Name) -join ', ')"

$body = @{
    using = @('urn:ietf:params:jmap:core', 'urn:stalwart:jmap')
    methodCalls = @(
        , @('x:Domain/get', @{ ids = $null }, 'd')
        , @('x:Account/get', @{ ids = $null }, 'a')
    )
} | ConvertTo-Json -Depth 10
$response = Invoke-RestMethod -Uri $session.apiUrl -Method Post -Headers $auth -ContentType 'application/json' -Body $body

foreach ($call in $response.methodResponses) {
    $name, $args, $tag = $call
    Write-Host "== $name ($tag)"
    if ($name -eq 'error') { $args | ConvertTo-Json -Depth 5 | Write-Host; continue }
    foreach ($item in $args.list) {
        # Drop anything credential-shaped before printing.
        $copy = $item | Select-Object * -ExcludeProperty credentials, secrets, secret, password, otpAuth, appPasswords, apiKeys
        $copy | ConvertTo-Json -Depth 6 -Compress | Write-Host
    }
}
