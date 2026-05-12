<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "smartq_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');

    echo json_encode([
        "status" => false,
        "message" => "Database connection failed"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conn->set_charset("utf8mb4");

define("BASE_URL", "http://192.168.1.10/smartq/");
?>