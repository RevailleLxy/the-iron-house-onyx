<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require_once 'api/conexion.php';

// Indicadores rápidos
$totalSocios = $conn->query("SELECT COUNT(*) AS total FROM socios WHERE estatus_registro='Activo'")->fetch_assoc()['total'];
$sociosActivos = $conn->query("SELECT COUNT(*) AS total FROM socios WHERE estatus_registro='Activo' AND fecha_vencimiento >= CURDATE()")->fetch_assoc()['total'];
$porVencer = $conn->query("SELECT COUNT(*) AS total FROM socios WHERE estatus_registro='Activo' AND DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND 3")->fetch_assoc()['total'];
$ingresosMes = $conn->query("SELECT IFNULL(SUM(monto),0) AS total FROM pagos WHERE MONTH(fecha_pago)=MONTH(CURDATE()) AND YEAR(fecha_pago)=YEAR(CURDATE())")->fetch_assoc()['total'];

// Catálogo de planes y lista de socios para selectores
$planesRes = $conn->query("SELECT * FROM cat_planes WHERE activo = 1");
$planes = [];
while ($p = $planesRes->fetch_assoc()) { $planes[] = $p; }

$sociosListRes = $conn->query("SELECT id_socio, folio_socio, nombre_completo, fecha_vencimiento FROM socios WHERE estatus_registro = 'Activo' ORDER BY nombre_completo ASC");
$listaSocios = [];
while ($s = $sociosListRes->fetch_assoc()) { $listaSocios[] = $s; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Gestión | The Iron House - ONYX</title>
    <link rel="stylesheet" href="css/estilos.css?v=5">
    <link href="https://fonts.googleapis.com/css2?family=Joan&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Menú Lateral -->
        <aside class="sidebar">
            <div>
                <div class="sidebar-logo">
                     <img src="img/logo.png" alt="ONYX"
		     >
                </div>
                <ul class="nav-menu">
                    <li class="nav-item active" onclick="cambiarSeccion('sec-monitor', this)"> <span class="nav-circle"></span> Monitor de Accesos</li>
                    <li class="nav-item" onclick="cambiarSeccion('sec-socios', this)"> <span class="nav-circle"></span> Gestión de Socios</li>
                    <li class="nav-item" onclick="cambiarSeccion('sec-cobros', this)"> <span class="nav-circle"></span> Cobros y Recibos PDF</li>
                    <?php if ($_SESSION['rol'] === 'Administrador'): ?>
                        <li class="nav-item" onclick="cambiarSeccion('sec-planes', this)"> <span class="nav-circle"></span> Planes y Precios</li>
                        <li class="nav-item" onclick="cambiarSeccion('sec-reportes', this); cargarReportes('mes');"> <span class="nav-circle"></span> Reportes y Bitácora</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <a href="api/auth.php?accion=logout" style="color: #FF1744; text-decoration: none; font-weight: bold; font-size: 0.9rem;">🚪 Cerrar Sesión</a>
            </div>
        </aside>

        <!-- Contenido Principal -->
        <main class="main-content">
            <header class="topbar">
                <div>
                    <h2 class="titulo-dashboard">Sistema Administrativo</h2>
                    <p style="color: #888; font-size: 0.9rem;">Operador en turno: <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                </div>
                <span class="role-badge"><?php echo $_SESSION['rol']; ?></span>
            </header>

            <!-- KPIs -->
            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Socios Activos en Padrón</div>
                    <div class="kpi-value"><?php echo $totalSocios; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Membresías al Corriente</div>
                    <div class="kpi-value" style="color: #2f240c;"><?php echo $sociosActivos; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Por Vencer (≤ 3 días)</div>
                    <div class="kpi-value" style="color: #2f240c;"><?php echo $porVencer; ?></div>
                </div>
                <?php if ($_SESSION['rol'] === 'Administrador'): ?>
                <div class="kpi-card">
                    <div class="kpi-title">Ingresos del Mes Actual</div>
                    <div class="kpi-value">$<?php echo number_format($ingresosMes, 2); ?></div>
                </div>
                <?php endif; ?>
            </section>

            <!-- ==================== SECCIÓN 1: MONITOR SEMÁFORO ==================== -->
            <section id="sec-monitor" class="seccion-panel active">
                <div class="search-box">
                    <label style="display:block; margin-bottom: 0.6rem;"class="titulo-busqueda"> 
                        🔍 Busqueda por Nombre, Folio IH o Celular:
                    </label>
                    <input type="text" id="buscadorSocio" class="input-onyx" placeholder="Escribe el nombre o folio del socio..." autofocus>
                    <div id="contenedorSocios" class="socios-grid"></div>
                </div>
            </section>

            <!-- ==================== SECCIÓN 2: GESTIÓN DE SOCIOS ==================== -->
            <section id="sec-socios" class="seccion-panel">
                <div class="card-panel">
                    <h3 id="tituloFormSocio" style=" font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000; margin-bottom: 1.2rem;">➕ Registrar Nuevo Socio</h3>
                    <form id="formSocio">
                        <input type="hidden" id="id_socio_edit" value="0">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombre Completo *</label>
                                <input type="text" id="soc_nombre" class="input-onyx" required>
                            </div>
                            <div class="form-group">
                                <label>Fecha de Nacimiento *</label>
                                <input type="date" id="soc_nacimiento" class="input-onyx" required>
                            </div>
                            <div class="form-group">
                                <label>Teléfono Celular *</label>
                                <input type="text" id="soc_celular" class="input-onyx" required>
                            </div>
                            <div class="form-group">
                                <label>Correo Electrónico</label>
                                <input type="email" id="soc_correo" class="input-onyx">
                            </div>
                            <div class="form-group">
                                <label>Contacto de Emergencia (Nombre) *</label>
                                <input type="text" id="soc_em_nombre" class="input-onyx" required>
                            </div>
                            <div class="form-group">
                                <label>Teléfono de Emergencia *</label>
                                <input type="text" id="soc_em_tel" class="input-onyx" required>
                            </div>
                            <div class="form-group">
                                <label>Parentesco *</label>
                                <input type="text" id="soc_em_par" class="input-onyx" placeholder="Madre, Padre, Esposo(a)..." required>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                            <button type="submit" class="btn-onyx" style="max-width: 260px;">Guardar Socio</button>
                            <button type="button" class="btn-secondary" onclick="limpiarFormSocio()">Limpiar Formulario</button>
                        </div>
                    </form>
                </div>

                <div class="card-panel">
                    <h3 style="font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000; margin-bottom: 1rem;">📋 Padrón General de Socios</h3>
                    <div class="table-responsive">
                        <table class="table-onyx">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Nombre</th>
                                    <th>Celular</th>
                                    <th>Emergencia</th>
                                    <th>Vencimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPadronSocios"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ==================== SECCIÓN 3: COBROS Y RENOVACIONES ==================== -->
            <section id="sec-cobros" class="seccion-panel">
                <div class="card-panel">
                    <h3 style="font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000; margin-bottom: 1.2rem;">💳 Registrar Pago / Renovación de Membresía</h3>
                    <form id="formCobro">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Seleccionar Socio Titular *</label>
                                <select id="cob_socio" class="input-onyx" required>
                                    <option value="">-- Selecciona un socio --</option>
                                    <?php foreach ($listaSocios as $s): ?>
                                        <option value="<?php echo $s['id_socio']; ?>">
                                            <?php echo htmlspecialchars($s['folio_socio'] . " - " . $s['nombre_completo'] . " (Vence: " . ($s['fecha_vencimiento'] ?? 'Sin plan') . ")"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Plan o Membresía a Contratar *</label>
                                <select id="cob_plan" class="input-onyx" required onchange="verificarPlanPareja()">
                                    <?php foreach ($planes as $p): ?>
                                        <option value="<?php echo $p['id_plan']; ?>" data-pareja="<?php echo $p['es_pareja']; ?>" data-precio="<?php echo $p['precio']; ?>">
                                            <?php echo htmlspecialchars($p['nombre_plan'] . " - $" . number_format($p['precio'], 2) . " (" . $p['duracion_dias'] . " días)"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="grupoSocioPareja" style="display: none;">
                                <label style="color: var(--onyx-cyan); font-weight: bold;">👩‍❤️‍👨 Segundo Socio (Plan Parejas) *</label>
                                <select id="cob_pareja" class="input-onyx">
                                    <option value="">-- Selecciona a su pareja --</option>
                                    <?php foreach ($listaSocios as $s): ?>
                                        <option value="<?php echo $s['id_socio']; ?>">
                                            <?php echo htmlspecialchars($s['folio_socio'] . " - " . $s['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Método de Pago *</label>
                                <select id="cob_metodo" class="input-onyx" required>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Transferencia">Transferencia</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn-onyx" style="max-width: 320px; margin-top: 1rem;">
                            Cobrar y Generar Recibo PDF
                        </button>
                    </form>
                </div>
            </section>

            <?php if ($_SESSION['rol'] === 'Administrador'): ?>
            <!-- ==================== SECCIÓN 4: PLANES Y PRECIOS (ADMIN) ==================== -->
            <section id="sec-planes" class="seccion-panel">
                <div class="card-panel">
                    <h3 style="font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000; margin-bottom: 1rem;">⚙️ Administración de Catálogo de Planes y Precios</h3>
                    <p style="font-family: 'Joan', serif; color: #AAA; font-size: 0.9rem; margin-bottom: 1.5rem;">Exclusivo para Gerencia. Cualquier cambio quedará registrado en la bitácora de auditoría.</p>
                    <div class="table-responsive">
                        <table class="table-onyx">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre del Plan</th>
                                    <th>Duración (Días)</th>
                                    <th>Precio Oficial (MXN)</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planes as $p): ?>
                                <tr>
                                    <td>#<?php echo $p['id_plan']; ?></td>
                                    <td><input type="text" id="nom_plan_<?php echo $p['id_plan']; ?>" class="input-onyx" value="<?php echo htmlspecialchars($p['nombre_plan']); ?>"></td>
                                    <td><?php echo $p['duracion_dias']; ?> días</td>
                                    <td><input type="number" step="0.01" id="pre_plan_<?php echo $p['id_plan']; ?>" class="input-onyx" value="<?php echo $p['precio']; ?>"></td>
                                    <td>
                                        <button class="btn-secondary" onclick="actualizarPlan(<?php echo $p['id_plan']; ?>)">💾 Guardar</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ==================== SECCIÓN 5: REPORTES Y BITÁCORA (ADMIN) ==================== -->
            <section id="sec-reportes" class="seccion-panel">
                <div class="card-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <h3 style="font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000;">📊 Reporte de Ingresos y Corte de Caja</h3>
                        <div style="display: flex; gap: 0.6rem;">
                            <button class="btn-secondary" onclick="cargarReportes('dia')">Hoy (Corte Diario)</button>
                            <button class="btn-secondary" onclick="cargarReportes('semana')">Esta Semana</button>
                            <button class="btn-secondary" onclick="cargarReportes('mes')">Este Mes</button>
                            <button class="btn-secondary" onclick="cargarReportes('todos')">Histórico Total</button>
                        </div>
                    </div>

                    <h2 style=" font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000; margin: 1.2rem 0; color: var(--semaforo-verde);">Total Filtrado: $<span id="lblTotalIngresos">0.00</span> MXN</h2>

                    <div class="table-responsive">
                        <table class="table-onyx">
                            <thead>
                                <tr>
                                    <th>Folio Recibo</th>
                                    <th>Fecha / Hora</th>
                                    <th>Socio</th>
                                    <th>Plan</th>
                                    <th>Método</th>
                                    <th>Cobró</th>
                                    <th>Monto</th>
                                    <th>PDF</th>
                                </tr>
                            </thead>
                            <tbody id="tablaReportesPagos"></tbody>
                        </table>
                    </div>
                </div>

                <div class="card-panel">
                    <h3 style=" font-family: 'Joan', serif; font-size: 28px; font-weight: normal; color: #000000;color: var(--onyx-cyan); margin-bottom: 1rem;">🛡️ Bitácora Inmutable de Actividades del Sistema</h3>
                    <div class="table-responsive">
                        <table class="table-onyx">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Operador</th>
                                    <th>Rol</th>
                                    <th>Módulo</th>
                                    <th>Acción Registrada</th>
                                </tr>
                            </thead>
                            <tbody id="tablaBitacora"></tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        let cacheSocios = [];

        function cambiarSeccion(idSeccion, elementoMenu) {
            document.querySelectorAll('.seccion-panel').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.getElementById(idSeccion).classList.add('active');
            elementoMenu.classList.add('active');
        }

        async function cargarSocios(filtro = '') {
            const res = await fetch(`api/auth.php?accion=buscar_socios&q=${encodeURIComponent(filtro)}`);
            const socios = await res.json();
            cacheSocios = socios;

            // Renderizar tarjetas del semáforo
            document.getElementById('contenedorSocios').innerHTML = socios.map(s => `
                <div class="socio-card ${s.color}">
                    <div>
                        <span style="font-family: 'Joan', serif; font-size: 1rem; color: #000000; font-weight: normal; -webkit-text-stroke: 0.4px #000000;">${s.folio_socio}</span>
                        <h3 style="font-family: 'Joan', serif; font-size: 1rem; color: #000000; font-weight: normal; -webkit-text-stroke: 0.4px #000000;">${s.nombre_completo}</h3>
                        <p style="font-family: 'Joan', serif; font-size: 0.85rem; color: #816014;">Plan: ${s.nombre_plan || 'Sin plan activo'}</p>
                        <p style="font-size: 0.8rem; color: #816014;">Vence: ${s.fecha_vencimiento || 'N/A'}</p>
                        <p style="font-size: 0.75rem; color: #816014; margin-top: 4px;">🚨 ${s.emergencia_nombre} (${s.emergencia_parentesco}) - ${s.emergencia_telefono}</p>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge-semaforo ${s.color}">${s.estado_texto}</span>
                    </div>
                </div>
            `).join('');

            // Renderizar tabla en Gestión de Socios
            const tbody = document.getElementById('tablaPadronSocios');
            if (tbody) {
                tbody.innerHTML = socios.map(s => `
                    <tr>
                        <td style="color: var(--onyx-cyan); font-weight: bold;">${s.folio_socio}</td>
                        <td>${s.nombre_completo}</td>
                        <td>${s.celular}</td>
                        <td>${s.emergencia_nombre} (${s.emergencia_parentesco}) - ${s.emergencia_telefono}</td>
                        <td><span class="badge-semaforo ${s.color}">${s.fecha_vencimiento || 'Sin vigencia'}</span></td>
                        <td>
                            <button class="btn-secondary" onclick="editarSocio(${s.id_socio})">✏️ Editar</button>
                            <button class="btn-danger" onclick="bajaSocio(${s.id_socio})">🗑️ Baja</button>
                        </td>
                    </tr>
                `).join('');
            }
        }

        document.getElementById('buscadorSocio').addEventListener('input', (e) => cargarSocios(e.target.value));

        // Guardar o Editar Socio
        document.getElementById('formSocio').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                accion: 'guardar_socio',
                id_socio: document.getElementById('id_socio_edit').value,
                nombre_completo: document.getElementById('soc_nombre').value,
                fecha_nacimiento: document.getElementById('soc_nacimiento').value,
                celular: document.getElementById('soc_celular').value,
                correo: document.getElementById('soc_correo').value,
                emergencia_nombre: document.getElementById('soc_em_nombre').value,
                emergencia_telefono: document.getElementById('soc_em_tel').value,
                emergencia_parentesco: document.getElementById('soc_em_par').value
            };

            const res = await fetch('api/operaciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            alert(data.mensaje);
            if (data.exito) { location.reload(); }
        });

        function editarSocio(id) {
            const s = cacheSocios.find(x => parseInt(x.id_socio) === parseInt(id));
            if (!s) return;
            document.getElementById('tituloFormSocio').textContent = '✏️ Editando Socio: ' + s.folio_socio;
            document.getElementById('id_socio_edit').value = s.id_socio;
            document.getElementById('soc_nombre').value = s.nombre_completo;
            document.getElementById('soc_nacimiento').value = s.fecha_nacimiento;
            document.getElementById('soc_celular').value = s.celular;
            document.getElementById('soc_correo').value = s.correo;
            document.getElementById('soc_em_nombre').value = s.emergencia_nombre;
            document.getElementById('soc_em_tel').value = s.emergencia_telefono;
            document.getElementById('soc_em_par').value = s.emergencia_parentesco;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function limpiarFormSocio() {
            document.getElementById('tituloFormSocio').textContent = '➕ Registrar Nuevo Socio';
            document.getElementById('id_socio_edit').value = '0';
            document.getElementById('formSocio').reset();
        }

        async function bajaSocio(id) {
            if (!confirm('¿Estás seguro de dar de baja a este socio?')) return;
            const res = await fetch('api/operaciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'baja_socio', id_socio: id })
            });
            const data = await res.json();
            alert(data.mensaje);
            location.reload();
        }

        // Mostrar selector de pareja cuando se elige Plan Parejas
        function verificarPlanPareja() {
            const sel = document.getElementById('cob_plan');
            const esPareja = sel.options[sel.selectedIndex].getAttribute('data-pareja');
            document.getElementById('grupoSocioPareja').style.display = (esPareja === '1') ? 'block' : 'none';
        }

        // Cobrar y abrir Recibo PDF
        document.getElementById('formCobro').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                accion: 'registrar_pago',
                id_socio: document.getElementById('cob_socio').value,
                id_plan: document.getElementById('cob_plan').value,
                id_socio_pareja: document.getElementById('cob_pareja').value,
                metodo_pago: document.getElementById('cob_metodo').value
            };

            const res = await fetch('api/operaciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            alert(data.mensaje);
            if (data.exito) {
                window.open(`recibo.php?id_pago=${data.id_pago}`, '_blank');
                location.reload();
            }
        });

        async function actualizarPlan(id_plan) {
            const nombre_plan = document.getElementById(`nom_plan_${id_plan}`).value;
            const precio = document.getElementById(`pre_plan_${id_plan}`).value;
            const res = await fetch('api/operaciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'actualizar_plan', id_plan, nombre_plan, precio })
            });
            const data = await res.json();
            alert(data.mensaje);
            if (data.exito) location.reload();
        }

        async function cargarReportes(periodo = 'mes') {
            const res = await fetch(`api/operaciones.php?accion=obtener_reportes&periodo=${periodo}`);
            const data = await res.json();

            document.getElementById('lblTotalIngresos').textContent = data.total_ingresos;

            document.getElementById('tablaReportesPagos').innerHTML = data.pagos.map(p => `
                <tr>
                    <td style="font-family: 'Joan', serif; font-weight: normal; color: var(--onyx-cyan);">${p.folio_recibo}</td>
                    <td>${p.fecha_pago}</td>
                    <td>${p.socio} (${p.folio_socio})</td>
                    <td>${p.nombre_plan}</td>
                    <td>${p.metodo_pago}</td>
                    <td>${p.cajero}</td>
                    <td style=" color: var(--semaforo-verde); font-weight: bold;">$${parseFloat(p.monto).toFixed(2)}</td>
                    <td><a href="recibo.php?id_pago=${p.id_pago}" target="_blank" class="btn-secondary" style="text-decoration:none;"> PDF</a></td>
                </tr>
            `).join('');

            document.getElementById('tablaBitacora').innerHTML = data.bitacora.map(b => `
                <tr>
                    <td>${b.fecha_hora}</td>
                    <td>${b.nombre_completo}</td>
                    <td><span class="role-badge" style="font-size: 0.7rem;">${b.rol}</span></td>
                    <td style="color: var(--onyx-cyan);">${b.modulo}</td>
                    <td>${b.accion}</td>
                </tr>
            `).join('');
        }

        cargarSocios();
    </script>
</body>
</html>