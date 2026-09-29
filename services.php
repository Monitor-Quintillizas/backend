<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Sessions\Session;
use App\Utils\Errors;
use App\Utils\ValueValidation;
use App\Utils\Tools;

require_once 'connection.php';
require_once 'sessions/Session.php';
require_once 'Errors.php';
require_once 'ValueValidation.php';
require_once 'Tools.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

// ==========================================
// SERVICIO: INICIO DE SESIÓN
// ==========================================
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        Errors::badRequest("Formato JSON inválido o vacío.");
    }

    $username = ValueValidation::validateString($input['username'] ?? '', 3, 50);
    $password = ValueValidation::validateString($input['password'] ?? '', 1, 255);

    if (!$username || !$password) {
        Errors::badRequest("Usuario y contraseña son obligatorios y deben tener un formato válido.");
    }

    try {
        $db = (new Connection())->getConnection();
        
        $stmt = $db->prepare("SELECT user_id, password_hash FROM USERS WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            Session::set('user_id', $user['user_id']);
            Session::set('username', $username);

            $updateStmt = $db->prepare("UPDATE USERS SET last_login_at = NOW() WHERE user_id = :id");
            $updateStmt->bindParam(':id', $user['user_id']);
            $updateStmt->execute();

            http_response_code(200);
            echo json_encode([
                "ok" => true, 
                "mensaje" => "Acceso concedido", 
                "usuario" => $username
            ]);
            exit;
        } else {
            Errors::unauthorized("Usuario o contraseña incorrectos.");
        }
    } catch (\PDOException $e) {
        Errors::databaseError("Error interno al validar credenciales.");
    }
} 
// ==========================================
// SERVICIO: OBTENER Y GUARDAR MÉTRICAS
// ==========================================
elseif ($action === 'get_metrics' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // 1. Validar que exista una sesión activa
    if (!Session::isValid()) {
        Errors::unauthorized("Debes iniciar sesión para consultar las métricas.");
    }

    // 2. Ejecutar script de Python a través de Tools
    $metrics = Tools::getPythonMetrics();

    if (!$metrics) {
        Errors::databaseError("No se pudieron leer las métricas del sistema.");
    }

    try {
        $db = (new Connection())->getConnection();
        
        // 3. Guardar en la Base de Datos (host_id = 1 corresponde al Servidor-Principal)
        $stmt = $db->prepare("INSERT INTO METRICS (host_id, cpu_usage_pct, ram_used_mb, ram_usage_pct, disk_usage_pct) 
                              VALUES (1, :cpu, :ram_mb, :ram_pct, :disk_pct)");
        
        $stmt->execute([
            ':cpu' => $metrics['cpu_usage_pct'],
            ':ram_mb' => $metrics['ram_used_mb'],
            ':ram_pct' => $metrics['ram_usage_pct'],
            ':disk_pct' => $metrics['disk_usage_pct']
        ]);

        // 4. Devolver las métricas al Frontend
        http_response_code(200);
        echo json_encode([
            "ok" => true,
            "data" => $metrics
        ]);
        exit;
    } catch (\PDOException $e) {
        Errors::databaseError("Error al guardar las métricas en la base de datos.");
    }
}
// ==========================================
// SERVICIO: OBTENER HISTORIAL DE MÉTRICAS
// ==========================================
elseif ($action === 'get_history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // 1. Validar sesión
    if (!Session::isValid()) {
        Errors::unauthorized("Debes iniciar sesión para consultar el historial.");
    }

    // 2. Validar parámetros de fecha
    $startDate = ValueValidation::validateDate($_GET['start_date'] ?? '');
    $endDate = ValueValidation::validateDate($_GET['end_date'] ?? '');

    if (!$startDate || !$endDate) {
        Errors::badRequest("Las fechas son obligatorias y deben tener el formato YYYY-MM-DD HH:MM:SS.");
    }

    // 3. Validar lógica de fechas (inicial anterior a final)
    if (strtotime($startDate) >= strtotime($endDate)) {
        Errors::badRequest("La fecha inicial debe ser anterior a la fecha final.");
    }

    try {
        $db = (new Connection())->getConnection();
        
        // 4. Consultar las métricas en ese rango de tiempo para el host 1
        $stmt = $db->prepare("SELECT cpu_usage_pct, ram_used_mb, ram_usage_pct, disk_usage_pct, measured_at 
                              FROM METRICS 
                              WHERE host_id = 1 AND measured_at BETWEEN :start AND :end 
                              ORDER BY measured_at ASC");
        
        $stmt->execute([
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        
        $history = $stmt->fetchAll();

        // 5. Devolver resultados
        http_response_code(200);
        echo json_encode([
            "ok" => true,
            "data" => $history,
            "mensaje" => empty($history) ? "No existen registros en el rango consultado." : "Historial recuperado."
        ]);
        exit;
    } catch (\PDOException $e) {
        Errors::databaseError("Error al consultar el historial en la base de datos.");
    }
}
// ==========================================
// SERVICIOS: UMBRALES (CRUD)
// ==========================================
// LEER UMBRALES
elseif ($action === 'get_thresholds' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!Session::isValid()) Errors::unauthorized("Sesión requerida.");
    try {
        $db = (new Connection())->getConnection();
        $stmt = $db->query("SELECT * FROM THRESHOLDS WHERE host_id = 1");
        http_response_code(200);
        echo json_encode(["ok" => true, "data" => $stmt->fetchAll()]);
        exit;
    } catch (\PDOException $e) {
        Errors::databaseError("Error al consultar los umbrales.");
    }
}
// CREAR UMBRAL
elseif ($action === 'create_threshold' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::isValid()) Errors::unauthorized("Sesión requerida.");
    $input = json_decode(file_get_contents("php://input"), true);

    $metricName = ValueValidation::validateInList($input['metric_name'] ?? '', ['cpu_usage_pct', 'ram_usage_pct', 'disk_usage_pct']);
    $operator = ValueValidation::validateInList($input['comparison_operator'] ?? '', ['>', '<', '>=', '<=', '=', '!=']);
    $value = is_numeric($input['threshold_value'] ?? null) ? (float)$input['threshold_value'] : null;

    if (!$metricName || !$operator || $value === null) {
        Errors::badRequest("Datos de umbral inválidos o incompletos.");
    }

    try {
        $db = (new Connection())->getConnection();
        $stmt = $db->prepare("INSERT INTO THRESHOLDS (host_id, metric_name, comparison_operator, threshold_value) VALUES (1, :metric, :op, :val)");
        $stmt->execute([':metric' => $metricName, ':op' => $operator, ':val' => $value]);
        http_response_code(200);
        echo json_encode(["ok" => true, "mensaje" => "Umbral creado exitosamente."]);
        exit;
    } catch (\PDOException $e) {
        Errors::databaseError("Error al crear el umbral.");
    }
}
// ACTUALIZAR UMBRAL
elseif ($action === 'update_threshold' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::isValid()) Errors::unauthorized("Sesión requerida.");
    $input = json_decode(file_get_contents("php://input"), true);

    $id = ValueValidation::validateId($input['threshold_id'] ?? null);
    $metricName = ValueValidation::validateInList($input['metric_name'] ?? '', ['cpu_usage_pct', 'ram_usage_pct', 'disk_usage_pct']);
    $operator = ValueValidation::validateInList($input['comparison_operator'] ?? '', ['>', '<', '>=', '<=', '=', '!=']);
    $value = is_numeric($input['threshold_value'] ?? null) ? (float)$input['threshold_value'] : null;

    if (!$id || !$metricName || !$operator || $value === null) {
        Errors::badRequest("Datos inválidos para actualizar el umbral.");
    }

    try {
        $db = (new Connection())->getConnection();
        $stmt = $db->prepare("UPDATE THRESHOLDS SET metric_name = :metric, comparison_operator = :op, threshold_value = :val WHERE threshold_id = :id");
        $stmt->execute([':metric' => $metricName, ':op' => $operator, ':val' => $value, ':id' => $id]);
        http_response_code(200);
        echo json_encode(["ok" => true, "mensaje" => "Umbral actualizado exitosamente."]);
        exit;
    } catch (\PDOException $e) {
        Errors::databaseError("Error al actualizar el umbral.");
    }
}
// ELIMINAR UMBRAL
elseif ($action === 'delete_threshold' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::isValid()) Errors::unauthorized("Sesión requerida.");
    $input = json_decode(file_get_contents("php://input"), true);
    
    $id = ValueValidation::validateId($input['threshold_id'] ?? null);

    if (!$id) {
        Errors::badRequest("ID de umbral inválido.");
    }

    try {
        $db = (new Connection())->getConnection();
        $stmt = $db->prepare("DELETE FROM THRESHOLDS WHERE threshold_id = :id");
        $stmt->execute([':id' => $id]);
        http_response_code(200);
        echo json_encode(["ok" => true, "mensaje" => "Umbral eliminado exitosamente."]);
        exit;
    } catch (\PDOException $e) {
        Errors::databaseError("Error al eliminar el umbral.");
    }
}

// ==========================================
// SERVICIO: CIERRE DE SESIÓN
// ==========================================
elseif ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::destroy();
    http_response_code(200);
    echo json_encode([
        "ok" => true,
        "mensaje" => "Sesión cerrada correctamente."
    ]);
    exit;
}

// ==========================================
// SERVICIO NO ENCONTRADO
// ==========================================
else {
    Errors::notFound("El servicio o la acción solicitada no existe.");
}