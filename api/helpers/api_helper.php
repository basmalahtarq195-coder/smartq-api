<?php

function jsonResponse($status, $message, $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getAuthorizationToken()
{
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    if (isset($headers['Authorization'])) {
        return trim($headers['Authorization']);
    }

    if (isset($headers['authorization'])) {
        return trim($headers['authorization']);
    }

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    return '';
}

function getBearerToken()
{
    $authHeader = getAuthorizationToken();

    if ($authHeader === '') {
        return '';
    }

    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function buildFileUrl($path)
{
    if (empty($path)) {
        return "";
    }

    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function normalizeDateInput($date)
{
    $date = trim((string)$date);

    if ($date === '') {
        return '';
    }

    $normalized = str_replace('/', '-', $date);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
        return false;
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $normalized);
    $errors = DateTime::getLastErrors();

    if (
        !$dateObj ||
        ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
    ) {
        return false;
    }

    return $dateObj->format('Y-m-d');
}
?>