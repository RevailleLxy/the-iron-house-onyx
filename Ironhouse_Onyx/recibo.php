<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { die("Acceso no autorizado"); }
require_once 'api/conexion.php';

$id_pago = (int)($_GET['id_pago'] ?? 0);
$sql = "SELECT p.*, s.nombre_completo AS socio_titular, s.folio_socio, s.celular,
               s2.nombre_completo AS socio_pareja, s2.folio_socio AS folio_pareja,
               c.nombre_plan, c.duracion_dias, u.nombre_completo AS cajero
        FROM pagos p
        INNER JOIN socios s ON p.id_socio = s.id_socio
        LEFT JOIN socios s2 ON p.id_socio_secundario = s2.id_socio
        INNER JOIN cat_planes c ON p.id_plan = c.id_plan
        INNER JOIN usuarios u ON p.id_usuario_cobro = u.id_usuario
        WHERE p.id_pago = $id_pago LIMIT 1";

$res = $conn->query($sql);
if (!$res || $res->num_rows === 0) { die("Recibo no encontrado"); }
$r = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo_<?php echo $r['folio_recibo']; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f0f0;
            padding: 30px;
            color: #121212;
        }
        .ticket-box {
            max-width: 520px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 30px;
            border-radius: 10px;
            border-top: 8px solid #121212;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #CCC;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 { margin: 0; font-size: 24px; letter-spacing: 2px; }
        .header span { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .total-box {
            background: #121212;
            color: #00E5FF;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: #777;
            margin-top: 25px;
            border-top: 1px solid #EEE;
            padding-top: 12px;
        }
        .btn-print {
            display: block;
            width: 100%;
            max-width: 520px;
            margin: 20px auto 0;
            padding: 12px;
            background: #00E5FF;
            color: #121212;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
        }
        @media print {
            body { background: #FFF; padding: 0; }
            .ticket-box { box-shadow: none; border: 1px solid #000; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket-box">
        <div class="header">
            <h1>THE IRON HOUSE</h1>
            <span>Comprobante Oficial de Pago de Membresía</span><br>
            <strong style="font-size: 13px; color: #0088cc;">Folio: <?php echo $r['folio_recibo']; ?></strong>
        </div>

        <div class="row"><strong>Fecha y Hora de Emisión:</strong> <span><?php echo $r['fecha_pago']; ?></span></div>
        <div class="row"><strong>Socio Titular:</strong> <span><?php echo htmlspecialchars($r['socio_titular']) . " (" . $r['folio_socio'] . ")"; ?></span></div>
        <?php if (!empty($r['socio_pareja'])): ?>
            <div class="row"><strong>Socio Vinculado (Pareja):</strong> <span><?php echo htmlspecialchars($r['socio_pareja']) . " (" . $r['folio_pareja'] . ")"; ?></span></div>
        <?php endif; ?>
        <div class="row"><strong>Plan Contratado:</strong> <span><?php echo htmlspecialchars($r['nombre_plan']); ?></span></div>
        <div class="row"><strong>Método de Pago:</strong> <span><?php echo $r['metodo_pago']; ?></span></div>
        <div class="row"><strong>Inicio de Vigencia:</strong> <span><?php echo $r['vigencia_inicio']; ?></span></div>
        <div class="row"><strong>Fecha de Vencimiento:</strong> <span style="font-weight:bold;"><?php echo $r['vigencia_fin']; ?></span></div>
        <div class="row"><strong>Atendido en Recepción por:</strong> <span><?php echo htmlspecialchars($r['cajero']); ?></span></div>

        <div class="total-box">
            <span>TOTAL PAGADO</span>
            <span>$<?php echo number_format($r['monto'], 2); ?> MXN</span>
        </div>

        <div class="footer">
            Sistema Administrativo Local desarrollado por <strong>ONYX Software Systems</strong><br>
            "Arquitectura Digital para el Mundo Real"
        </div>
    </div>

    <button class="btn-print" onclick="window.print()">🖨️ Imprimir o Guardar como PDF</button>
</body>
</html>