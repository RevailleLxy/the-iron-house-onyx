<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? $_GET['accion'] ?? '';

if ($accion === 'login') {
    $usuario = $conn->real_escape_string($input['usuario']);
    $password = $input['password'];

    $sql = "SELECT * FROM usuarios WHERE usuario = '$usuario' AND activo = 1 LIMIT 1";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if ($password === $row['password_hash']) {
            $_SESSION['id_usuario'] = $row['id_usuario'];
            $_SESSION['nombre'] = $row['nombre_completo'];
            $_SESSION['rol'] = $row['rol'];

            // Registrar en bitácora
            $id_u = $row['id_usuario'];
            $conn->query("INSERT INTO bitacora (id_usuario, modulo, accion) VALUES ($id_u, 'Seguridad', 'Inicio de sesión en el sistema')");

            echo json_encode(["exito" => true]);
            exit;
        }
    }
    echo json_encode(["exito" => false, "mensaje" => "Usuario o contraseña incorrectos"]);
    exit;
}

if ($accion === 'logout') {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

if ($accion === 'buscar_socios') {
    $q = $conn->real_escape_string($_GET['q'] ?? '');
    $sql = "SELECT s.*, p.nombre_plan,
            DATEDIFF(s.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM socios s
            LEFT JOIN cat_planes p ON s.id_plan_actual = p.id_plan
            WHERE s.estatus_registro = 'Activo'
            AND (s.nombre_completo LIKE '%$q%' OR s.folio_socio LIKE '%$q%' OR s.celular LIKE '%$q%')
            ORDER BY s.id_socio DESC";
            
    $res = $conn->query($sql);
    $socios = [];
    while ($row = $res->fetch_assoc()) {
        $dias = (int)$row['dias_restantes'];
        if ($dias < 0) {
            $row['color'] = 'ROJO';
            $row['estado_texto'] = 'VENCIDO (' . abs($dias) . ' días)';
        } elseif ($dias <= 3) {
            $row['color'] = 'AMARILLO';
            $row['estado_texto'] = 'POR VENCER (' . $dias . ' días)';
        } else {
            $row['color'] = 'VERDE';
            $row['estado_texto'] = 'ACTIVO (' . $dias . ' días)';
        }
        $socios[] = $row;
    }
    echo json_encode($socios);
    exit;
}
?>