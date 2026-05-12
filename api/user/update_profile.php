<?php
header('Content-Type: application/json');

require '../helpers/auth_user.php';

$authUser = requireUserAuth($conn);

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$birthDate = trim($_POST['birth_date'] ?? '');

if ($fullName === '' || $email === '') {
    jsonResponse(false, "Full name and email are required", [], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, "Invalid email address", [], 422);
}

if ($birthDate !== '') {
    $birthDate = normalizeDateInput($birthDate);
    if ($birthDate === false) {
        jsonResponse(false, "Birth date must be valid and in YYYY-MM-DD or YYYY/MM/DD format", [], 422);
    }
}

$checkStmt = $conn->prepare("
    SELECT id
    FROM users
    WHERE email = ? AND id != ?
    LIMIT 1
");
$checkStmt->bind_param("si", $email, $authUser['id']);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();

if ($existing) {
    jsonResponse(false, "Email already used by another account", [], 409);
}

$stmt = $conn->prepare("
    UPDATE users
    SET full_name = ?, email = ?, phone = ?, birth_date = ?
    WHERE id = ? AND role = 'user'
");
$stmt->bind_param("ssssi", $fullName, $email, $phone, $birthDate, $authUser['id']);

if (!$stmt->execute()) {
    jsonResponse(false, "Failed to update profile", [], 500);
}

jsonResponse(true, "Profile updated successfully", [
    "user" => [
        "id" => (int)$authUser['id'],
        "full_name" => $fullName,
        "email" => $email,
        "phone" => $phone,
        "birth_date" => $birthDate
    ]
]);
?>