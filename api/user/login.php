<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require '../../config/db.php';
require '../helpers/api_helper.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    jsonResponse(false, "Email and password are required", [], 422);
}

$stmt = $conn->prepare("
    SELECT id, full_name, email, password, phone, birth_date, profile_image, role
    FROM users
    WHERE email = ? AND role = 'user'
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, "Invalid email or password", [], 401);
}

$newToken = bin2hex(random_bytes(32));

$tokenStmt = $conn->prepare("
    UPDATE users
    SET api_token = ?
    WHERE id = ?
");
$tokenStmt->bind_param("si", $newToken, $user['id']);
$tokenStmt->execute();

jsonResponse(true, "Login successful", [
    "user" => [
        "id" => (int)$user["id"],
        "full_name" => $user["full_name"],
        "email" => $user["email"],
        "phone" => $user["phone"],
        "birth_date" => $user["birth_date"],
        "profile_image" => $user["profile_image"] ?? "",
        "profile_image_url" => buildFileUrl($user["profile_image"] ?? "")
    ],
    "api_token" => $newToken
]);
?>