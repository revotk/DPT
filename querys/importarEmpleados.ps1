# Cargar los registros desde el CSV
$registros = Get-Content "C:\deso\usuarios_depppp.csv" | ConvertFrom-Csv

# URL de la API
$apiUrl = "http://localhost/api/employees"

# Procesar cada registro que no tenga estado "Baja"
$registros | Where-Object { $_.status -notlike 'Baja' } | ForEach-Object {
    # Crear objeto con los datos del empleado
    $empleado = @{
        user        = if ([string]::IsNullOrEmpty($_.user)) { "S/D" } else { $_.user }
        rfc         = if ([string]::IsNullOrEmpty($_.rfc)) { "S/D" } else { $_.rfc }
        phone       = if ([string]::IsNullOrEmpty($_.phone)) { "S/D" } else { $_.phone }
        position    = if ([string]::IsNullOrEmpty($_.position)) { "S/D" } else { $_.position }
        adscription = if ([string]::IsNullOrEmpty($_.assignment)) { "S/D" } else { $_.assignment }
        entry_time  = if ([string]::IsNullOrEmpty($_.entry)) { "S/D" } else { $_.entry }
        exit_time   = if ([string]::IsNullOrEmpty($_.exit)) { "S/D" } else { $_.exit }
        status      = if ([string]::IsNullOrEmpty($_.status) -or $_.status -eq "Desconocido" -or $_.status -eq "Prueba") { "S/D" } else { $_.status }
        fullname    = if ([string]::IsNullOrEmpty($_.fullname)) { "S/D" } else { $_.fullname }
        curp        = if ([string]::IsNullOrEmpty($_.curp)) { "S/D" } else { $_.curp }
        name        = if ([string]::IsNullOrEmpty($_.name)) { "S/D" } else { $_.name }
        employee_id = if ([string]::IsNullOrEmpty($_.id) -or -not [double]::TryParse($_.id, [ref]$null)) { "0" } else { $_.id }
        lastname    = if ([string]::IsNullOrEmpty($_.lastname)) { "S/D" } else { $_.lastname }
    }

    # Asignamos checker_uid y checker_id correctamente
    if ($_ | Get-Member -Name "checadorID") {
        $empleado.checker_id = if ([string]::IsNullOrEmpty($_.checadorID)) { "S/D" } else { $_.checadorID }
    }
    elseif ($_ | Get-Member -Name "checadorid") {
        $empleado.checker_id = if ([string]::IsNullOrEmpty($_.checadorid)) { "S/D" } else { $_.checadorid }
    }
    else {
        $empleado.checker_id = 1
    }

    if ($_ | Get-Member -Name "checadorUID") {
        $empleado.checker_uid = if ([string]::IsNullOrEmpty($_.checadorUID)) { "1" } else { $_.checadorUID }
    }
    elseif ($_ | Get-Member -Name "Checadas") {
        $empleado.checker_uid = if ([string]::IsNullOrEmpty($_.Checadas)) { "1" } else { $_.Checadas }
    }
    else {
        $empleado.checker_uid = 0
    }

    # Asegúrate de que employee_id use el valor del CSV y no sea 0
    if ($_.id -and [double]::TryParse($_.id, [ref]$null)) {
        $empleado.employee_id = $_.id
    }

    Write-Host "Procesando: $($empleado.name)" -ForegroundColor Cyan
    Write-Host "  employee_id: $($empleado.employee_id)" -ForegroundColor Yellow
    Write-Host "  checker_uid: $($empleado.checker_uid)" -ForegroundColor Yellow
    Write-Host "  checker_id: $($empleado.checker_id)" -ForegroundColor Yellow

    # Convertir el objeto a JSON
    $jsonBody = $empleado | ConvertTo-Json

    # Enviar la solicitud POST a la API con más información de respuesta
    try {
        # Usar -Verbose para obtener más información de la respuesta
        $response = Invoke-RestMethod -Uri $apiUrl -Method Post -Body $jsonBody -ContentType "application/json" -ErrorAction Stop

        if ($response -and $response.id) {
            Write-Host "Empleado creado con ID: $($response.id)" -ForegroundColor Green
        }
        else {
            Write-Host "Empleado creado pero no se recibió un ID válido en la respuesta" -ForegroundColor Yellow
            Write-Host "Respuesta: $($response | ConvertTo-Json)" -ForegroundColor Yellow
        }
    }
    catch {
        $statusCode = $_.Exception.Response.StatusCode.value__

        Write-Host "Error al crear el empleado (HTTP $statusCode)" -ForegroundColor Red

        try {
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $reader.BaseStream.Position = 0
            $reader.DiscardBufferedData()
            $responseBody = $reader.ReadToEnd()
            Write-Host "Detalles: $responseBody" -ForegroundColor Red
        }
        catch {
            Write-Host "No se pudo obtener más información sobre el error: $_" -ForegroundColor Red
        }
    }

    # Pausa breve para no sobrecargar la API
    Start-Sleep -Milliseconds 100
}

Write-Host "Proceso completado." -ForegroundColor Green
