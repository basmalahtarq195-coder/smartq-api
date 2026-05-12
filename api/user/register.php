<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require '../../config/db.php';
require '../helpers/api_helper.php';

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$birthDate = trim($_POST['birth_date'] ?? '');

if ($fullName === '' || $email === '' || $password === '') {
    jsonResponse(false, "Full name, email and password are required", [], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, "Invalid email address", [], 422);
}

if (strlen($password) < 6) {
    jsonResponse(false, "Password must be at least 6 characters", [], 422);
}

if ($birthDate !== '') {
    $birthDate = normalizeDateInput($birthDate);
    if ($birthDate === false) {
        jsonResponse(false, "Birth date must be valid and in YYYY-MM-DD or YYYY/MM/DD format", [], 422);
    }
} else {
    $birthDate = null;
}

$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();

if ($existing) {
    jsonResponse(false, "Email already exists", [], 409);
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$apiToken = bin2hex(random_bytes(32));

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, birth_date, profile_image, api_token, role) VALUES (?, ?, ?, ?, ?, NULL, ?, 'user')");
$stmt->bind_param("ssssss", $fullName, $email, $hashedPassword, $phone, $birthDate, $apiToken);

if (!$stmt->execute()) {
    jsonResponse(false, "Failed to register user: " . $stmt->error, [], 500);
}

$userId = $stmt->insert_id;

jsonResponse(true, "Registration successful", [
    "user" => [
        "id" => (int)$userId,
        "full_name" => $fullName,
        "email" => $email,
        "phone" => $phone,
        "birth_date" => $birthDate,
        "profile_image" => "",
        "profile_image_url" => ""
    ],
    "api_token" => $apiToken
]);
?>
