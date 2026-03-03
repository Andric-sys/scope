<?php
declare(strict_types=1);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "core_global_logistics";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
