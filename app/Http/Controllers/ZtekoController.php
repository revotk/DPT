<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rats\Zkteco\Lib\ZKTeco;
use App\Models\Zteko;

class ZtekoController extends Controller
{
    /**
     * Establece la conexión con el dispositivo ZKTeco.
     */
    private function connectToDevice($zteko)
    {
        $zk = new ZKTeco($zteko->ip, $zteko->port);
        return $zk->connect() ? $zk : null;
    }
    /**
     * Listar todos los dispositivos ZKTeco.
     */
    public function index()
    {
        $devices = Zteko::all();
        return response()->json($devices);
    }

    /**
     * Agregar un nuevo dispositivo ZKTeco.
     */
    public function store(Request $request)
    {
        $request->validate([
            'ip' => 'required|string|max:45',
            'port' => 'required|integer|min:1|max:65535',
            'description' => 'nullable|string|max:255',
        ]);

        // Intentar conectar con el dispositivo
        $zk = new ZKTeco($request->ip, $request->port);

        if (!$zk->connect()) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        // Obtener información del dispositivo
        $serialNumber = $zk->serialNumber();

        // Verificar si ya existe un dispositivo con este número de serie
        $existingDevice = Zteko::where('serial_number', $serialNumber)->first();
        if ($existingDevice) {
            $zk->disconnect();
            return response()->json(['error' => 'Este dispositivo ya está registrado'], 409);
        }

        // Crear el nuevo registro con la información del dispositivo
        $zteko = new Zteko();
        $zteko->ip = $request->ip;
        $zteko->port = $request->port;
        $zteko->description = $request->description;
        $zteko->device_version = $zk->version();
        $zteko->device_os_version = $zk->osVersion();
        $zteko->platform = $zk->platform();
        $zteko->firmware_version = $zk->fmVersion();
        $zteko->work_code = $zk->workCode();
        $zteko->serial_number = $serialNumber;
        $zteko->device_name = $zk->deviceName();
        $zteko->save();

        $zk->disconnect();

        return response()->json($zteko, 201);
    }

    /**
     * Mostrar los detalles de un dispositivo específico.
     */
    public function show($id)
    {
        $zteko = Zteko::findOrFail($id);
        return response()->json($zteko);
    }

    /**
     * Actualizar la información de un dispositivo.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'ip' => 'nullable|string|max:45',
            'port' => 'nullable|integer|min:1|max:65535',
            'description' => 'nullable|string|max:255',
        ]);

        $zteko = Zteko::findOrFail($id);

        // Actualizar IP y puerto si se proporcionaron
        $ip = $request->ip ?? $zteko->ip;
        $port = $request->port ?? $zteko->port;

        // Si la IP o el puerto cambiaron, intentar conectar con el dispositivo
        if ($ip !== $zteko->ip || $port !== $zteko->port) {
            $zk = new ZKTeco($ip, $port);

            if (!$zk->connect()) {
                return response()->json(['error' => 'No se pudo conectar al dispositivo con la nueva IP/puerto'], 500);
            }

            // Verificar que sea el mismo dispositivo comparando el número de serie
            $serialNumber = $zk->serialNumber();
            if ($serialNumber !== $zteko->serial_number) {
                $zk->disconnect();
                return response()->json(['error' => 'La nueva IP/puerto corresponde a un dispositivo diferente'], 400);
            }

            // Actualizar toda la información del dispositivo
            $zteko->ip = $ip;
            $zteko->port = $port;
            $zteko->device_version = $zk->version();
            $zteko->device_os_version = $zk->osVersion();
            $zteko->platform = $zk->platform();
            $zteko->firmware_version = $zk->fmVersion();
            $zteko->work_code = $zk->workCode();
            $zteko->device_name = $zk->deviceName();

            $zk->disconnect();
        }

        // Actualizar la descripción si se proporcionó
        if ($request->has('description')) {
            $zteko->description = $request->description;
        }

        $zteko->save();

        return response()->json($zteko);
    }

    /**
     * Eliminar un dispositivo.
     */
    public function destroy($id)
    {
        $zteko = Zteko::findOrFail($id);
        $zteko->delete();

        return response()->json(['message' => 'Dispositivo eliminado correctamente']);
    }

    /**
     * Refrescar la información del dispositivo con datos limpios.
     */
    public function refresh($id)
    {
        $zteko = Zteko::findOrFail($id);
        $zk = $this->connectToDevice($zteko);

        if (!$zk) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        // Obtener datos del dispositivo
        $deviceVersion = $zk->version();
        $osVersion = $zk->osVersion();
        $platform = $zk->platform();
        $firmwareVersion = $zk->fmVersion();
        $workCode = $zk->workCode();
        $serialNumber = $zk->serialNumber();  // Añadido para actualizar el número de serie
        $deviceName = $zk->deviceName();

        // Limpiar datos antes de guardar
        $zteko->device_version = rtrim($deviceVersion, "\0");
        $zteko->device_os_version = str_replace('~OS=', '', rtrim($osVersion, "\0"));
        $zteko->platform = str_replace('~Platform=', '', rtrim($platform, "\0"));
        $zteko->firmware_version = str_replace('~ZKFPVersion=', '', rtrim($firmwareVersion, "\0"));
        $zteko->work_code = str_replace('WorkCode=', '', rtrim($workCode, "\0"));  // Limpiar WorkCode=
        $zteko->serial_number = str_replace('~SerialNumber=', '', rtrim($serialNumber, "\0"));  // Limpieza del número de serie
        $zteko->device_name = str_replace('~DeviceName=', '', rtrim($deviceName, "\0"));

        $zteko->save();

        $zk->disconnect();

        return response()->json($zteko);
    }
    /**
     * Obtener la información del dispositivo (nombre, versión, firmware, etc.).
     */
    public function getDeviceInfo($id)
    {
        $zteko = Zteko::findOrFail($id);
        $zk = $this->connectToDevice($zteko);

        if (!$zk) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        $info = [
            'device_version' => $zk->version(),
            'device_os_version' => $zk->osVersion(),
            'platform' => $zk->platform(),
            'firmware_version' => $zk->fmVersion(),
            'work_code' => $zk->workCode(),
            'serial_number' => $zk->serialNumber(),
            'device_name' => $zk->deviceName(),
        ];

        $zk->disconnect();
        return response()->json($info);
    }

    /**
     * Obtener el log de asistencia del checador.
     */
    public function getAttendanceLogs($id)
{
    $zteko = Zteko::findOrFail($id);
    $zk = $this->connectToDevice($zteko);

    if (!$zk) {
        return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
    }

    // Obtener registros de asistencia
    $attendance = $zk->getAttendance();

    // Obtener usuarios para mapear IDs con UIDs
    $users = $zk->getUser();

    // Crear un mapa de ID a UID para búsqueda rápida
    $userMap = [];
    foreach ($users as $user) {
        $userMap[$user['userid']] = [
            'uid' => $user['uid'],
            'name' => $user['name']
        ];
    }

    // Transformar los registros al formato deseado
    $formattedAttendance = [];
    foreach ($attendance as $record) {
        // Verificar si el ID del usuario existe en el mapa
        if (isset($userMap[$record['id']])) {
            $formattedAttendance[] = [
                'device' => (int) $id,
                'uid' => $userMap[$record['id']]['uid'],
                'date' => $record['timestamp']
            ];
        } else {
            // Si no se encuentra el usuario, usar los datos originales pero con formato correcto
            $formattedAttendance[] = [
                'device' => (int) $id,
                'uid' => null, // No se encontró el UID correspondiente
                'date' => $record['timestamp']
            ];
        }
    }

    $zk->disconnect();

    return response()->json($formattedAttendance);
}

    /**
     * Obtener la lista de usuarios registrados en el dispositivo.
     */
    public function getUsers($id)
    {
        $zteko = Zteko::findOrFail($id);
        $zk = $this->connectToDevice($zteko);

        if (!$zk) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        $users = $zk->getUser(); // Corrección del método
        $zk->disconnect();

        return response()->json($users);
    }

    /**
     * Agregar un usuario al dispositivo.
     */
    public function addUser($id, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:24',
            'password' => 'nullable|string|max:8',
            'privilege' => 'required|integer|min:0|max:3', // 0: Usuario, 3: Admin
            'card_number' => 'nullable|integer|max:9999999999',
        ]);

        $zteko = Zteko::findOrFail($id);
        $zk = $this->connectToDevice($zteko);

        if (!$zk) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        $users = $zk->getUser();
        $lastUserId = collect($users)->max('uid') ?? 0;
        $newUserId = $lastUserId + 1;

        $zk->setUser(
            $newUserId,
            (string) $newUserId,
            $request->name,
            $request->password ?? '',
            $request->privilege,
            $request->card_number ?? 0
        );

        $zk->disconnect();

        return response()->json(['message' => 'Usuario agregado correctamente']);
    }

    /**
     * Eliminar un usuario del dispositivo.
     */
    public function deleteUser($id, $userId)
    {
        $zteko = Zteko::findOrFail($id);
        $zk = $this->connectToDevice($zteko);

        if (!$zk) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        $zk->removeUser((int) $userId);
        $zk->disconnect();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    /**
     * Editar un usuario del dispositivo.
     */
    public function editUser($id, $userId, Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:24',
            'password' => 'nullable|string|max:8',
            'privilege' => 'nullable|integer|min:0|max:3',
            'card_number' => 'nullable|integer|max:9999999999',
        ]);

        $zteko = Zteko::findOrFail($id);
        $zk = $this->connectToDevice($zteko);

        if (!$zk) {
            return response()->json(['error' => 'No se pudo conectar al dispositivo'], 500);
        }

        $users = $zk->getUser();
        $user = collect($users)->firstWhere('uid', (int) $userId);

        if (!$user) {
            $zk->disconnect();
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $zk->setUser(
            $userId,
            $user['id'],
            $request->name ?? $user['name'],
            $request->password ?? $user['password'],
            $request->privilege ?? $user['role'],
            $request->card_number ?? $user['cardno']
        );

        $zk->disconnect();

        return response()->json(['message' => 'Usuario editado correctamente']);
    }
}
