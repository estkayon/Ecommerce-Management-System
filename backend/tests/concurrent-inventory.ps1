
param(
    [Parameter(Mandatory = $true)]
    [string]$Token,

    [Parameter(Mandatory = $true)]
    [int]$ProductId
)

$baseUrl = "http://127.0.0.1:8000/api/admin/products/$ProductId"
$headers = @{
    Authorization = "Bearer $Token"
    Accept = "application/json"
}

# Submit two stock reduction requests concurrently.
$jobs = 1..2 | ForEach-Object {
    Start-ThreadJob -ScriptBlock {
        param($url, $headers)

        $body = @{
            quantity_change = -20
            reason = "Concurrency test"
        } | ConvertTo-Json

        try {
            $response = Invoke-RestMethod `
                -Uri "$url/inventory/adjust" `
                -Method Post `
                -Headers $headers `
                -ContentType "application/json" `
                -Body $body

            [pscustomobject]@{
                Status = 200
                Message = $response.message
            }
        }
        catch {
            [pscustomobject]@{
                Status = [int]$_.Exception.Response.StatusCode
                Message = $_.Exception.Message
            }
        }
    } -ArgumentList $baseUrl, $headers
}

$jobs | Wait-Job | Receive-Job
$jobs | Remove-Job

# Fetch the final stock quantity.
$result = Invoke-RestMethod -Uri "$baseUrl/inventory" -Headers $headers
Write-Output "Final stock: $($result.data.inventory.quantity)"
