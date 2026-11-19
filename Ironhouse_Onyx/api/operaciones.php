<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida"]);
    exit;
}

$id_usuario_actual = (int)$_SESSION['id_usuario'];
$rol_actual = $_SESSION['rol'];
$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? $_GET['accion'] ?? '';

function registrarBitacora($conn, $id_usuario, $modulo, $accion_texto) {
    $mod = $conn->real_escape_string($modulo);
    $acc = $conn->real_escape_string($accion_texto);
    $conn->query("INSERT INTO bitacora (id_usuario, modulo, accion) VALUES ($id_usuario, '$mod', '$acc')");
}

// 1. GUARDAR O ACTUALIZAR SOCIO
if ($accion === 'guardar_socio') {
    $id_socio   = (int)($input['id_socio'] ?? 0);
    $nombre     = $conn->real_escape_string($input['nombre_completo']);
    $nacimiento = $conn->real_escape_string($input['fecha_nacimiento']);
    $celular    = $conn->real_escape_string($input['celular']);
    $correo     = $conn->real_escape_string($input['correo']);
    $em_nombre  = $conn->real_escape_string($input['emergencia_nombre']);
    $em_tel     = $conn->real_escape_string($input['emergencia_telefono']);
    $em_par     = $conn->real_escape_string($input['emergencia_parentesco']);

    if ($id_socio > 0) {
        $sql = "UPDATE socios SET 
                nombre_completo='$nombre', fecha_nacimiento='$nacimiento', celular='$celular', correo='$correo',
                emergencia_nombre='$em_nombre', emergencia_telefono='$em_tel', emergencia_parentesco='$em_par'
                WHERE id_socio = $id_socio";
        $conn->query($sql);
        registrarBitacora($conn, $id_usuario_actual, 'Socios', "Actualizó datos del socio ID #$id_socio ($nombre)");
        echo json_encode(["exito" => true, "mensaje" => "Datos del socio actualizados correctamente"]);
    } else {
        $sql = "INSERT INTO socios (nombre_completo, fecha_nacimiento, celular, correo, emergencia_nombre, emergencia_telefono, emergencia_parentesco)
                VALUES ('$nombre', '$nacimiento', '$celular', '$correo', '$em_nombre', '$em_tel', '$em_par')";
        if ($conn->query($sql)) {
            $nuevo_id = $conn->insert_id;
            $folio = 'IH-' . str_pad($nuevo_id, 4, '0', STR_PAD_LEFT);
            $conn->query("UPDATE socios SET folio_socio = '$folio' WHERE id_socio = $nuevo_id");
            registrarBitacora($conn, $id_usuario_actual, 'Socios', "Registró nuevo socio $folio ($nombre)");
            echo json_encode(["exito" => true, "mensaje" => "Socio registrado con Folio: $folio", "id_socio" => $nuevo_id]);
        } else {
            echo json_encode(["exito" => false, "mensaje" => "Error al registrar: " . $conn->error]);
        }
    }
    exit;
}

// 2. DAR DE BAJA SOCIO
if ($accion === 'baja_socio') {
    $id_socio = (int)$input['id_socio'];
    $conn->query("UPDATE socios SET estatus_registro = 'Baja' WHERE id_socio = $id_socio");
    registrarBitacora($conn, $id_usuario_actual, 'Socios', "Dio de baja al socio ID #$id_socio");
    echo json_encode(["exito" => true, "mensaje" => "Socio dado de baja del padrón activo"]);
    exit;
}

// 3. REGISTRAR PAGO / RENOVACIÓN (CON LÓGICA DE PAREJAS Y VIGENCIA ACUMULABLE)
if ($accion === 'registrar_pago') {
    $id_socio      = (int)$input['id_socio'];
    $id_pareja     = !empty($input['id_socio_pareja']) ? (int)$input['id_socio_pareja'] : "NULL";
    $id_plan       = (int)$input['id_plan'];
    $metodo_pago   = $conn->real_escape_string($input['metodo_pago']);

    // Obtener info del plan
    $plan = $conn->query("SELECT * FROM cat_planes WHERE id_plan = $id_plan")->fetch_assoc();
    $dias = (int)$plan['duracion_dias'];
    $monto = (float)$plan['precio'];
    $es_pareja = (int)$plan['es_pareja'];

    if ($es_pareja === 1 && $id_pareja === "NULL") {
        echo json_encode(["exito" => false, "mensaje" => "Para el Plan Parejas debes seleccionar al segundo socio."]);
        exit;
    }

    // Verificar vigencia actual del socio titular para no perder días si renueva antes
    $socio = $conn->query("SELECT nombre_completo, fecha_vencimiento FROM socios WHERE id_socio = $id_socio")->fetch_assoc();
    $hoy = date('Y-m-d');

    if (!empty($socio['fecha_vencimiento']) && $socio['fecha_vencimiento'] >= $hoy) {
        $inicio_vigencia = $socio['fecha_vencimiento'];
        $fin_vigencia = date('Y-m-d', strtotime($inicio_vigencia . " + $dias days"));
    } else {
        $inicio_vigencia = $hoy;
        $fin_vigencia = date('Y-m-d', strtotime($hoy . " + $dias days"));
    }

    $folio_recibo = 'REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    $sqlPago = "INSERT INTO pagos (folio_recibo, id_socio, id_socio_secundario, id_plan, monto, metodo_pago, vigencia_inicio, vigencia_fin, id_usuario_cobro)
                VALUES ('$folio_recibo', $id_socio, $id_pareja, $id_plan, $monto, '$metodo_pago', '$inicio_vigencia', '$fin_vigencia', $id_usuario_actual)";

    if ($conn->query($sqlPago)) {
        $id_pago = $conn->insert_id;

        // Actualizar socio titular
        $conn->query("UPDATE socios SET id_plan_actual = $id_plan, fecha_inicio_membresia = '$inicio_vigencia', fecha_vencimiento = '$fin_vigencia', id_socio_pareja = $id_pareja WHERE id_socio = $id_socio");

        // Si es plan pareja, actualizar también al segundo socio con la misma fecha de vencimiento
        if ($es_pareja === 1 && $id_pareja !== "NULL") {
            $conn->query("UPDATE socios SET id_plan_actual = $id_plan, fecha_inicio_membresia = '$inicio_vigencia', fecha_vencimiento = '$fin_vigencia', id_socio_pareja = $id_socio WHERE id_socio = $id_pareja");
        }

        registrarBitacora($conn, $id_usuario_actual, 'Cobros', "Cobró $folio_recibo ($$monto - {$plan['nombre_plan']}) al socio ID #$id_socio");

        echo json_encode([
            "exito" => true,
            "mensaje" => "Pago registrado con éxito. Folio: $folio_recibo",
            "id_pago" => $id_pago
        ]);
    } else {
        echo json_encode(["exito" => false, "mensaje" => "Error al procesar pago: " . $conn->error]);
    }
    exit;
}

// 4. ACTUALIZAR PRECIO DE PLAN (EXCLUSIVO ADMINISTRADOR)
if ($accion === 'actualizar_plan') {
    if ($rol_actual !== 'Administrador') {
        echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: Solo el Administrador puede modificar precios."]);
        exit;
    }
    $id_plan = (int)$input['id_plan'];
    $precio  = (float)$input['precio'];
    $nombre  = $conn->real_escape_string($input['nombre_plan']);

    $conn->query("UPDATE cat_planes SET precio = $precio, nombre_plan = '$nombre' WHERE id_plan = $id_plan");
    registrarBitacora($conn, $id_usuario_actual, 'Catálogos', "Modificó el plan '$nombre' a nuevo precio: $$precio MXN");
    echo json_encode(["exito" => true, "mensaje" => "Precio actualizado correctamente"]);
    exit;
}

// 5. OBTENER REPORTES DE INGRESOS Y BITÁCORA
if ($accion === 'obtener_reportes') {
    $periodo = $_GET['periodo'] ?? 'mes';
    $condicion = "DATE(p.fecha_pago) = CURDATE()";
    if ($periodo === 'semana') {
        $condicion = "YEARWEEK(p.fecha_pago, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($periodo === 'mes') {
        $condicion = "MONTH(p.fecha_pago) = MONTH(CURDATE()) AND YEAR(p.fecha_pago) = YEAR(CURDATE())";
    } elseif ($periodo === 'todos') {
        $condicion = "1=1";
    }

    $sqlPagos = "SELECT p.*, s.nombre_completo AS socio, s.folio_socio, c.nombre_plan, u.nombre_completo AS cajero
                 FROM pagos p
                 INNER JOIN socios s ON p.id_socio = s.id_socio
                 INNER JOIN cat_planes c ON p.id_plan = c.id_plan
                 INNER JOIN usuarios u ON p.id_usuario_cobro = u.id_usuario
                 WHERE $condicion
                 ORDER BY p.id_pago DESC";
    $resPagos = $conn->query($sqlPagos);
    $pagos = [];
    $suma = 0;
    while ($r = $resPagos->fetch_assoc()) {
        $suma += (float)$r['monto'];
        $pagos[] = $r;
    }

    $sqlBit = "SELECT b.*, u.nombre_completo, u.rol 
               FROM bitacora b 
               INNER JOIN usuarios u ON b.id_usuario = u.id_usuario 
               ORDER BY b.id_bitacora DESC LIMIT 60";
    $resBit = $conn->query($sqlBit);
    $bitacora = [];
    while ($b = $resBit->fetch_assoc()) {
        $bitacora[] = $b;
    }

    echo json_encode([
        "pagos" => $pagos,
        "total_ingresos" => number_format($suma, 2),
        "bitacora" => $bitacora
    ]);
    exit;
}
?>