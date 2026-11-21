<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = getenv('DB_HOST') ?: 'localhost';
$username = (string)getenv('DB_USER');
$password = (string)getenv('DB_PASSWORD');
$dbname = (string)getenv('DB_NAME');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {

    http_response_code(500);
    header("Content-Type: application/json");

    die(json_encode([
        "ok" => false,
        "erro" => "Falha na conexão com banco de dados",
        "debug" => $e->getMessage()
    ]));
}