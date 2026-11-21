# Proves each fixture From actually sends, on the local Stalwart only, to admin@example.org only.
#   alice's own Account  as alice.work@example.org
#   the team Account     as team@example.org
# Idempotent: a message already in that Account's Sent (same subject) is not sent again.
# Secrets come from AXJ_SECRET (admin) and AXJ_ALICE_SECRET, or a prompt. Never printed.
param(
    [string]$SessionUrl = 'https://localhost:18881/.well-known/jmap',
    [string]$AliceUser = 'alice',
    [string]$AdminUser = 'admin',
    [string]$Recipient = 'admin@example.org'
)
$ErrorActionPreference = 'Stop'
if (([Uri]$SessionUrl).Host -notin @('localhost', '127.0.0.1', '::1')) { throw 'Loopback Session only.' }
if ($Recipient -notlike '*@example.org') { throw 'Local fixture recipients only.' }

function New-BasicAuth([string]$User, [string]$EnvName) {
    $plain = [Environment]::GetEnvironmentVariable($EnvName)
    if (-not $plain) { $plain = [System.Net.NetworkCredential]::new('', (Read-Host -AsSecureString "Stalwart password for '$User'")).Password }
    @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("${User}:$plain")) }
}
function Invoke-Jmap($Auth, [string]$ApiUrl, [string[]]$Using, $Calls) {
    $body = @{ using = $Using; methodCalls = $Calls } | ConvertTo-Json -Depth 12
    $r = Invoke-RestMethod -Uri $ApiUrl -Method Post -Headers $Auth -ContentType 'application/json' -Body $body
    foreach ($c in $r.methodResponses) { if ($c[0] -eq 'error') { throw "JMAP error: $($c[1] | ConvertTo-Json -Compress)" } }
    , $r.methodResponses
}
$using = @('urn:ietf:params:jmap:core', 'urn:ietf:params:jmap:mail', 'urn:ietf:params:jmap:submission')

$auth = New-BasicAuth $AliceUser 'AXJ_ALICE_SECRET'
$s = Invoke-RestMethod -Uri $SessionUrl -Headers $auth -PreserveAuthorizationOnRedirect
if (-not $s.username) { throw "Unauthenticated Session for $AliceUser." }

$plan = @(
    @{ personal = $true;  from = 'alice.work@example.org' },
    @{ personal = $false; from = 'team@example.org' }
)
$subjects = @()
foreach ($p in $plan) {
    $accountId = ($s.accounts.PSObject.Properties | Where-Object { [bool]$_.Value.isPersonal -eq $p.personal } | Select-Object -First 1).Name
    if (-not $accountId) { throw "No account with isPersonal=$($p.personal)." }
    $subject = "[Axismundi probe v2] From $($p.from) via account $(if ($p.personal) { 'personal' } else { 'team' })"
    $subjects += $subject
    $r = Invoke-Jmap $auth $s.apiUrl $using @(
        , @('Identity/get', @{ accountId = $accountId }, 'i')
        , @('Mailbox/get', @{ accountId = $accountId; properties = @('role') }, 'm')
        , @('Email/query', @{ accountId = $accountId; filter = @{ subject = $subject } }, 'q')
    )
    $identity = $r[0][1].list | Where-Object { $_.email -eq $p.from } | Select-Object -First 1
    if (-not $identity) { throw "No identity $($p.from) in account $accountId." }
    $drafts = ($r[1][1].list | Where-Object { $_.role -eq 'drafts' }).id
    $sent = ($r[1][1].list | Where-Object { $_.role -eq 'sent' }).id
    if ($r[2][1].ids.Count -gt 0) { Write-Host "skip (already present): $subject"; continue }

    $email = @{
        mailboxIds = @{ $drafts = $true }; keywords = @{ '$draft' = $true; '$seen' = $true }
        from = @(@{ name = $identity.name; email = $p.from }); to = @(@{ email = $Recipient })
        subject = $subject
        bodyValues = @{ t = @{ value = "Fixture: second From / second Account send check.`n" } }
        textBody = @(@{ partId = 't'; type = 'text/plain' })
    }
    $r = Invoke-Jmap $auth $s.apiUrl $using @(
        , @('Email/set', @{ accountId = $accountId; create = @{ e = $email } }, 'e')
        , @('EmailSubmission/set', @{
            accountId = $accountId
            create = @{ s = @{ identityId = $identity.id; emailId = '#e' } }
            onSuccessUpdateEmail = @{ '#s' = @{ "mailboxIds/$drafts" = $null; "mailboxIds/$sent" = $true; 'keywords/$draft' = $null } }
        }, 's')
    )
    if (-not $r[0][1].created.e) { throw "Email not created: $($r[0][1].notCreated | ConvertTo-Json -Compress)" }
    if (-not $r[1][1].created.s) { throw "Submission failed: $($r[1][1].notCreated | ConvertTo-Json -Compress)" }
    Write-Host "sent: account $accountId identity $($identity.id) <$($p.from)> email $($r[0][1].created.e.id) submission $($r[1][1].created.s.id)"
}

Start-Sleep -Seconds 3
$admin = New-BasicAuth $AdminUser 'AXJ_SECRET'
$as = Invoke-RestMethod -Uri $SessionUrl -Headers $admin -PreserveAuthorizationOnRedirect
$aid = $as.primaryAccounts.'urn:ietf:params:jmap:mail'
foreach ($subject in $subjects) {
    $r = Invoke-Jmap $admin $as.apiUrl $using @(
        , @('Email/query', @{ accountId = $aid; filter = @{ subject = $subject } }, 'q')
        , @('Email/get', @{ accountId = $aid; '#ids' = @{ resultOf = 'q'; name = 'Email/query'; path = '/ids' }; properties = @('from', 'subject') }, 'g')
    )
    $got = $r[1][1].list
    Write-Host "recipient has $($got.Count): $subject $(($got | ForEach-Object { "<$($_.from[0].email)>" }) -join ' ')"
}
