# URL base de la API y endpoints
$baseUrl = "http://10.35.2.14/api"
$employeesEndpoint = "$baseUrl/employees?per_page=150"
$permissionsEndpoint = "$baseUrl/permissions"

# Función para obtener un empleado por username
function Get-EmployeeByUsername {
    param(
        [Parameter(Mandatory = $true)]
        [string]$username
    )
    try {
        $response = Invoke-RestMethod -Uri $employeesEndpoint -Method Get
        $employee = $response.data | Where-Object { $_.user -eq $username }
        if (-not $employee) {
            Write-Host "Usuario '$username' no encontrado." -ForegroundColor Yellow
            return $null
        }
        return $employee
    }
    catch {
        Write-Error "Error al obtener empleados: $_"
    }
}

# Función para crear un permiso para un día dado (utiliza el username para obtener datos del empleado)
function Create-PermissionForDay {
    param(
        [Parameter(Mandatory = $true)]
        [string]$username,
        [Parameter(Mandatory = $true)]
        [string]$date, # Formato YYYY-MM-DD
        [Parameter(Mandatory = $true)]
        [string]$reason,
        [Parameter(Mandatory = $true)]
        [string]$type,
        [string]$start_time = $null,
        [string]$end_time = $null,
        [string]$approved_by = $null
    )
    $employee = Get-EmployeeByUsername -username $username
    if ($employee -eq $null) {
        Write-Host "No se pudo crear permiso para '$username' en $date, usuario no encontrado." -ForegroundColor Red
        return
    }
    # Convertir checker_uid a cadena para cumplir con la validación
    $checker_uid = [string]$employee.checker_uid
    $body = @{
        employee_id = $employee.employee_id
        checker_uid = $checker_uid
        date        = $date
        reason      = $reason
        type        = $type
        start_time  = $start_time
        end_time    = $end_time
        approved_by = $approved_by
    } | ConvertTo-Json
    try {
        $response = Invoke-RestMethod -Uri $permissionsEndpoint -Method Post -ContentType "application/json" -Body $body
        Write-Host "Permiso creado para '$username' en $date." -ForegroundColor Green
    }
    catch {
        Write-Error "Error al crear el permiso para '$username' en $date : $_"
    }
}

# Función para crear permisos diarios en un rango (solo lunes a viernes)
function Create-DailyPermissionRange {
    param(
        [Parameter(Mandatory = $true)]
        [string]$username,
        [Parameter(Mandatory = $true)]
        [string]$startDate, # Formato: YYYY-MM-DD
        [Parameter(Mandatory = $true)]
        [string]$endDate, # Formato: YYYY-MM-DD
        [Parameter(Mandatory = $true)]
        [string]$reason,
        [Parameter(Mandatory = $true)]
        [string]$type,
        [string]$start_time = $null,
        [string]$end_time = $null,
        [string]$approved_by = $null
    )
    try {
        $currentDate = [DateTime]::Parse($startDate)
        $endDateObj = [DateTime]::Parse($endDate)
    }
    catch {
        Write-Host "Formato de fecha incorrecto. Use YYYY-MM-DD." -ForegroundColor Red
        return
    }

    while ($currentDate -le $endDateObj) {
        # Solo si es de lunes a viernes (excluye sábado y domingo)
        if ($currentDate.DayOfWeek -ne 'Saturday' -and $currentDate.DayOfWeek -ne 'Sunday') {
            $dateStr = $currentDate.ToString("yyyy-MM-dd")
            Create-PermissionForDay -username $username -date $dateStr -reason $reason -type $type -start_time $start_time -end_time $end_time -approved_by $approved_by
        }
        else {
            Write-Host "Saltando $($currentDate.ToString('yyyy-MM-dd')) por ser fin de semana."
        }
        $currentDate = $currentDate.AddDays(1)
    }
}

# Función para crear permisos en bulk leyendo un archivo CSV
function Create-BulkPermissions {
    param(
        [Parameter(Mandatory = $true)]
        [string]$csvPath
    )
    if (-Not (Test-Path $csvPath)) {
        Write-Host "El archivo CSV no existe en la ruta especificada." -ForegroundColor Red
        return
    }
    try {
        $data = Import-Csv -Path $csvPath
    }
    catch {
        Write-Host "Error al leer el CSV: $_" -ForegroundColor Red
        return
    }
    foreach ($row in $data) {
        # Se asume que el CSV tiene las columnas: username, date, reason, type, start_time, end_time, approved_by (opcional)
        $username = $row.username
        $date = $row.date
        $reason = $row.reason
        $type = $row.type
        $start_time = $row.start_time
        $end_time = $row.end_time
        $approved_by = $row.approved_by
        Create-PermissionForDay -username $username -date $date -reason $reason -type $type -start_time $start_time -end_time $end_time -approved_by $approved_by
    }
}

# Menú interactivo para seleccionar la funcionalidad
function Show-Menu {
    Write-Host ""
    Write-Host "Seleccione una opción:"
    Write-Host "1. Crear permiso diario para un rango de fechas (lunes a viernes)"
    Write-Host "2. Crear permisos en bulk desde CSV (usuario y fecha)"
    Write-Host "3. Salir"
}

do {
    Show-Menu
    $option = Read-Host "Ingrese el número de opción"
    switch ($option) {
        "1" {
            $username = Read-Host "Ingrese el username (ej: oscar.badillo)"
            $startDate = Read-Host "Ingrese la fecha de inicio (YYYY-MM-DD)"
            $endDate = Read-Host "Ingrese la fecha de fin (YYYY-MM-DD)"
            $reason = Read-Host "Ingrese el motivo del permiso"
            $type = Read-Host "Ingrese el tipo (personal, médico, vacaciones, capacitación, otro)"
            $start_time = Read-Host "Ingrese hora de inicio (opcional, HH:mm) o presione Enter para omitir"
            $end_time = Read-Host "Ingrese hora de fin (opcional, HH:mm) o presione Enter para omitir"
            $approved_by = Read-Host "Ingrese aprobado por (opcional) o presione Enter para omitir"
            Create-DailyPermissionRange -username $username -startDate $startDate -endDate $endDate -reason $reason -type $type -start_time $start_time -end_time $end_time -approved_by $approved_by
        }
        "2" {
            $csvPath = Read-Host "Ingrese la ruta del archivo CSV"
            Create-BulkPermissions -csvPath $csvPath
        }
        "3" {
            Write-Host "Saliendo..."
        }
        default {
            Write-Host "Opción inválida, intente de nuevo."
        }
    }
} while ($option -ne "3")
