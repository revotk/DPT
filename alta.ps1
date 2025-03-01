# Definir la URL de la API
$apiUrl = "http://localhost:8000/api/zteko"

# Definir los datos del dispositivo a crear
$deviceData = @{
    ip          = "10.35.2.120"
    port        = 4565
    description = "Checador DPT"
} | ConvertTo-Json

# Configurar los headers para la petición
$headers = @{
    "Content-Type" = "application/json"
    "Accept"       = "application/json"
}

try {
    # Realizar la petición POST para crear el dispositivo
    $response = Invoke-RestMethod -Uri $apiUrl -Method Post -Body $deviceData -Headers $headers

    # Mostrar la respuesta
    Write-Host "Dispositivo creado exitosamente:"
    $response | ConvertTo-Json -Depth 4
}
catch {
    # Manejar errores
    Write-Host "Error al crear el dispositivo:"
    Write-Host "Código de estado: $($_.Exception.Response.StatusCode.value__)"
    Write-Host "Mensaje completo: $_"
}
