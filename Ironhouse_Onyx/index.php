<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Joan&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <title>The Iron House de ONYX | Acceso</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">

	    <span class="corner-circle top-left"></span>
            <span class="corner-circle top-right"></span>
            <span class="corner-circle bottom-left"></span>
            <span class="corner-circle bottom-right"></span>

            <div class="brand-logo">
                <img src="img/logo.png" alt="The Iron House">
            </div>

            <form id="formLogin">
                <div class="form-group">
                    <label>Ingresa tu usuario</label>
                    <input type="text" id="usuario" class="input-onyx" placeholder="Usuario" required autofocus>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" id="password" class="input-onyx" placeholder="••••••" required>
                </div>
                <p id="errorMsg" style="color: #FF1744; font-size: 0.60rem; margin-bottom: 1rem; display: none;"></p>
                <button type="submit" class="btn-onyx">Iniciar Sesión</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('formLogin').addEventListener('submit', async (e) => {
            e.preventDefault();
            const usuario = document.getElementById('usuario').value;
            const password = document.getElementById('password').value;
            const errorMsg = document.getElementById('errorMsg');

            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'login', usuario, password })
            });
            const data = await res.json();

            if (data.exito) {
                window.location.href = 'dashboard.php';
            } else {
                errorMsg.textContent = data.mensaje;
                errorMsg.style.display = 'block';
            }
        });
    </script>
</body>
</html>