<?php
require_once __DIR__ . '/api_helper.php';
require_once __DIR__ . '/../../config/db.php';

function requireUserAuth($conn)
{
    $token = getBearerToken();

    // fallback for compatibility with old app requests
    if ($token === '') {
        $token = trim($_POST['api_token'] ?? $_GET['api_token'] ?? '');
    }

    if ($token === '') {
        jsonResponse(false, "Authorization token is required", [], 401);
    }

    $stmt = $conn->prepare("
        SELECT id, full_name, email, phone, birth_date, profile_image, role, api_token
        FROM users
        WHERE api_token = ? AND role = 'user'
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        jsonResponse(false, "Invalid or expired token", [], 401);
    }

    return $user;
}
?>