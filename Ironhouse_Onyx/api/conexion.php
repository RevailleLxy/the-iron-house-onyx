<?php
$host = "localhost";
$user = "root";
$pass = ""; // En XAMPP por defecto va vacío
$db   = "ironhouse_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["error" => "Error de conexión ONYX DB: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('America/Mexico_City');
?>