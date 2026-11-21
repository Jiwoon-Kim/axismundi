# Multi-account / multi-identity fixture for the Mail GUI, on the local Stalwart only.
#   1. Group `team@example.org` (no credentials; members see it as a separate JMAP Account)
#   2. alice joins it through her memberGroupIds
#   3. alias `alice.work@example.org` on alice (Stalwart derives an Identity from it)
# Idempotent: existing group, membership and alias are left as they are.
# Then reads alice's own Session to show what she is actually offered. Never prints secrets.
param(
    [string]$SessionUrl = 'https://localhost:18881/.well-known/jmap',
    [string]$AdminUser = 'admin',
    [string]$AliceUser = 'alice',
    [string]$GroupName = 'team',
    [string]$AliasName = 'alice.work'
)
$ErrorActionPreference = 'Stop'
if (([Uri]$SessionUrl).Host -notin @('localhost', '127.0.0.1', '::1')) { throw 'Loopback Session only.' }

function New-BasicAuth([string]$User, [string]$EnvName) {
    # Dev convenience: a secret already in this process's environment, else prompt.
    $plain = [Environment]::GetEnvironmentVariable($EnvName)
    if (-not $plain) {
        $secret = Read-Host -AsSecureString "Stalwart password for '$User'"
        $plain = [System.Net.NetworkCredential]::new('', $secret).Password
    }
    @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("${User}:$plain")) }
}
function Invoke-Jmap($Auth, [string]$ApiUrl, [string[]]$Using, $Calls) {
    $body = @{ using = $Using; methodCalls = $Calls } | ConvertTo-Json -Depth 12
    $r = Invoke-RestMethod -Uri $ApiUrl -Method Post -Headers $Auth -ContentType 'application/json' -Body $body
    foreach ($c in $r.methodResponses) { if ($c[0] -eq 'error') { throw "JMAP error: $($c[1] | ConvertTo-Json -Compress)" } }
    # Leading comma: keep a single response wrapped, or PowerShell unrolls it.
    , $r.methodResponses
}
$admin = New-BasicAuth $AdminUser 'AXJ_SECRET'
$session = Invoke-RestMethod -Uri $SessionUrl -Headers $admin -PreserveAuthorizationOnRedirect
$api = $session.apiUrl
$mgmt = @('urn:ietf:params:jmap:core', 'urn:stalwart:jmap')

$all = (Invoke-Jmap $admin $api $mgmt @(, @('x:Account/get', @{ ids = $null }, 'a')))[0][1].list
$alice = $all | Where-Object { $_.name -eq $AliceUser -and $_.'@type' -eq 'User' }
if (-not $alice) { throw "No user '$AliceUser'." }
$domainId = $alice.domainId

$group = $all | Where-Object { $_.name -eq $GroupName -and $_.'@type' -eq 'Group' }
if ($group) {
    Write-Host "group exists: $GroupName id=$($group.id)"
} else {
    $create = @{ create = @{ g = @{ '@type' = 'Group'; name = $GroupName; domainId = $domainId; description = 'Axismundi probe team'; roles = @{ '@type' = 'Default' }; permissions = @{ '@type' = 'Inherit' } } } }
    $res = (Invoke-Jmap $admin $api $mgmt @(, @('x:Account/set', $create, 's')))[0][1]
    if (-not $res.created.g) { throw "Group not created: $($res.notCreated | ConvertTo-Json -Compress)" }
    $group = $res.created.g
    Write-Host "group created: $GroupName id=$($group.id)"
}

$patch = @{}
if (-not ($alice.memberGroupIds.PSObject.Properties.Name -contains $group.id)) { $patch["memberGroupIds/$($group.id)"] = $true }
$aliases = @($alice.aliases.PSObject.Properties | ForEach-Object { $_.Value })
if (-not ($aliases | Where-Object { $_.name -eq $AliasName })) { $patch["aliases/$($aliases.Count)"] = @{ name = $AliasName; domainId = $domainId } }
if ($patch.Count -gt 0) {
    $res = (Invoke-Jmap $admin $api $mgmt @(, @('x:Account/set', @{ update = @{ $alice.id = $patch } }, 'u')))[0][1]
    if ($res.notUpdated) { throw "alice not updated: $($res.notUpdated | ConvertTo-Json -Compress)" }
    Write-Host "alice updated: $(($patch.Keys) -join ', ')"
} else {
    Write-Host 'alice already has membership and alias'
}

Write-Host '== alice Session'
$aliceAuth = New-BasicAuth $AliceUser 'AXJ_ALICE_SECRET'
$s = Invoke-RestMethod -Uri $SessionUrl -Headers $aliceAuth -PreserveAuthorizationOnRedirect
if (-not $s.username) { throw "Unauthenticated Session for $AliceUser." }
Write-Host "username: $($s.username)"
Write-Host "primary mail account: $($s.primaryAccounts.'urn:ietf:params:jmap:mail')"
foreach ($p in $s.accounts.PSObject.Properties) {
    $a = $p.Value
    Write-Host "account $($p.Name): name=$($a.name) isPersonal=$($a.isPersonal) isReadOnly=$($a.isReadOnly) caps=$(($a.accountCapabilities.PSObject.Properties.Name) -join ',')"
    $caps = $a.accountCapabilities.PSObject.Properties.Name
    $calls = @(, @('Mailbox/get', @{ accountId = $p.Name; properties = @('name', 'role') }, 'm'))
    $using = @('urn:ietf:params:jmap:core', 'urn:ietf:params:jmap:mail')
    if ($caps -contains 'urn:ietf:params:jmap:submission') {
        $calls += , @('Identity/get', @{ accountId = $p.Name }, 'i')
        $using += 'urn:ietf:params:jmap:submission'
    }
    foreach ($c in (Invoke-Jmap $aliceAuth $s.apiUrl $using $calls)) {
        if ($c[0] -eq 'Mailbox/get') { Write-Host "  mailboxes: $(($c[1].list | ForEach-Object { "$($_.name)[$($_.role)]" }) -join ', ')" }
        if ($c[0] -eq 'Identity/get') { foreach ($i in $c[1].list) { Write-Host "  identity $($i.id): $($i.name) <$($i.email)> mayDelete=$($i.mayDelete)" } }
    }
}
