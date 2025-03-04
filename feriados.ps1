# Script PowerShell para crear días festivos específicos en el sistema
# Configuración de la API
$apiBaseUrl = "http://localhost/api"  # Cambiar según la URL de tu API
$holidaysEndpoint = "$apiBaseUrl/holidays"

# Función para crear un día festivo
function Create-Holiday {
    param (
        [string]$date,
        [string]$description,
        [bool]$isRecurring = $true
    )

    $body = @{
        date         = $date
        description  = $description
        is_recurring = $isRecurring
    } | ConvertTo-Json

    try {
        $response = Invoke-RestMethod -Uri $holidaysEndpoint -Method Post -Body $body -ContentType "application/json"
        Write-Host "✓ Creado: $description ($date)" -ForegroundColor Green
        return $response
    }
    catch {
        Write-Host "✗ Error al crear '$description': $($_.Exception.Message)" -ForegroundColor Red
        if ($_.ErrorDetails) {
            Write-Host "  Detalles: $($_.ErrorDetails.Message)" -ForegroundColor Red
        }
    }
}

# Verificar si la API está disponible
try {
    Invoke-RestMethod -Uri $apiBaseUrl -Method Get -TimeoutSec 5
    Write-Host "Conexión a la API establecida correctamente" -ForegroundColor Green
}
catch {
    Write-Host "⚠️ No se puede conectar a la API en $apiBaseUrl" -ForegroundColor Yellow
    Write-Host "Verifica que la API esté en ejecución y que la URL sea correcta." -ForegroundColor Yellow
    $customUrl = Read-Host "¿Quieres especificar una URL diferente? (S/N)"

    if ($customUrl -eq "S" -or $customUrl -eq "s") {
        $apiBaseUrl = Read-Host "Ingresa la URL base de la API (ejemplo: http://localhost:8000/api)"
        $holidaysEndpoint = "$apiBaseUrl/holidays"
    }
    else {
        Write-Host "Continuando con la URL actual, pero podrían fallar las operaciones." -ForegroundColor Yellow
    }
}

# Lista de días festivos a crear
$holidays = @(
    @{date = "2025-01-01"; description = "Año Nuevo" },
    @{date = "2025-02-03"; description = "Día de la Constitución Mexicana" },
    @{date = "2025-03-17"; description = "Natalicio de Benito Juárez" },
    @{date = "2025-05-01"; description = "Día del Trabajo" },
    @{date = "2025-09-16"; description = "Día de la Independencia de México" },
    @{date = "2025-11-17"; description = "Aniversario de la Revolución Mexicana" },
    @{date = "2025-12-25"; description = "Navidad" }
)

# Crear los días festivos
Write-Host "Creando días festivos recurrentes..." -ForegroundColor Cyan
foreach ($holiday in $holidays) {
    Create-Holiday -date $holiday.date -description $holiday.description -isRecurring $true
}

Write-Host "`nProceso completado. Se han creado todos los días festivos solicitados." -ForegroundColor Cyan
Write-Host "Estos días festivos se repetirán automáticamente cada año." -ForegroundColor Cyan

# Esperar a que el usuario presione una tecla para salir
Read-Host "`nPresiona Enter para salir"
