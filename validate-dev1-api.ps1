$ErrorActionPreference = 'Stop'

$projectRoot = 'C:\Users\Dell\Desktop\multi_tanancy\backend_job_portal'
Set-Location $projectRoot

Write-Host "==> Step 1: Running Dev 1 Laravel tests" -ForegroundColor Cyan
php artisan test --filter='PartnerManagementTest|JobModerationTest|UserManagementTest|AuditLogTest|EmailVerificationTest'
if ($LASTEXITCODE -ne 0) {
    throw "Dev 1 Laravel tests failed."
}

Write-Host "" 
Write-Host "==> Step 2: Starting Laravel server on http://127.0.0.1:8000" -ForegroundColor Cyan
$serverProcess = Start-Process -FilePath 'php' -ArgumentList 'artisan','serve','--host=127.0.0.1','--port=8000' -PassThru -WorkingDirectory $projectRoot

Start-Sleep -Seconds 3

$baseUrl = 'http://127.0.0.1:8000'

function Invoke-JsonRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string]$Body = $null,
        [string]$CookieJar = 'cookies.txt',
        [string]$CookieHeader = $null,
        [switch]$UseCookies
    )

    $args = @('-sS', '-D', '-','-o', '-','-H','Accept: application/json')

    if ($Method) {
        $args += @('-X', $Method)
    }

    if ($Body) {
        $args += @('-H','Content-Type: application/json', '--data', $Body)
    }

    if ($UseCookies) {
        $args += @('-b', $CookieJar, '-c', $CookieJar)
    }

    if ($CookieHeader) {
        $args += @('-H', "Cookie: $CookieHeader")
    }

    $args += $Url

    $output = & curl.exe @args 2>$null
    return $output
}

Write-Host "" 
Write-Host "==> Step 3: Smoke test auth routes" -ForegroundColor Cyan

Write-Host "Registering candidate..."
$registerResponse = Invoke-JsonRequest -Method 'POST' -Url "$baseUrl/api/v1/auth/register" -Body '{"name":"Test Candidate","email":"candidate@example.com","password":"Password@123","password_confirmation":"Password@123"}' -UseCookies
Write-Host $registerResponse

Write-Host "Checking /auth/me..."
$meResponse = Invoke-JsonRequest -Method 'GET' -Url "$baseUrl/api/v1/auth/me" -UseCookies
Write-Host $meResponse

Write-Host "Logging out..."
$logoutResponse = Invoke-JsonRequest -Method 'POST' -Url "$baseUrl/api/v1/auth/logout" -UseCookies
Write-Host $logoutResponse

Write-Host "" 
Write-Host "==> Step 4: Smoke test admin routes" -ForegroundColor Cyan

Write-Host "Login as super admin..."
$loginResponse = Invoke-JsonRequest -Method 'POST' -Url "$baseUrl/api/v1/auth/login" -Body '{"email":"superadmin@example.com","password":"Password@123"}' -UseCookies
Write-Host $loginResponse

Write-Host "GET /api/v1/admin/partners"
$partnersResponse = Invoke-JsonRequest -Method 'GET' -Url "$baseUrl/api/v1/admin/partners" -UseCookies
Write-Host $partnersResponse

Write-Host "GET /api/v1/admin/users"
$usersResponse = Invoke-JsonRequest -Method 'GET' -Url "$baseUrl/api/v1/admin/users" -UseCookies
Write-Host $usersResponse

Write-Host "GET /api/v1/admin/jobs"
$jobsResponse = Invoke-JsonRequest -Method 'GET' -Url "$baseUrl/api/v1/admin/jobs" -UseCookies
Write-Host $jobsResponse

Write-Host "" 
Write-Host "Validation finished successfully." -ForegroundColor Green
Write-Host "If any steps fail, inspect the response above to fix the route or auth issue." -ForegroundColor Yellow

Write-Host "" 
Write-Host "Tip: stop the server with:" -ForegroundColor Yellow
Write-Host "Stop-Process -Id $($serverProcess.Id)" -ForegroundColor Yellow
