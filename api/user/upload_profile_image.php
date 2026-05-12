<?php
header('Content-Type: application/json');

require '../helpers/auth_user.php';

$authUser = requireUserAuth($conn);

if (!isset($_FILES['profile_image'])) {
    jsonResponse(false, "No image uploaded", [], 422);
}

$file = $_FILES['profile_image'];

if ($file['error'] !== 0) {
    jsonResponse(false, "Upload error", [], 400);
}

if ($file['size'] > 5 * 1024 * 1024) {
    jsonResponse(false, "Image size must be less than 5MB", [], 422);
}

$tmpName = $file['tmp_name'];
$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

if (!in_array($ext, $allowedExt)) {
    jsonResponse(false, "Invalid image type", [], 422);
}

$imageInfo = getimagesize($tmpName);
if ($imageInfo === false) {
    jsonResponse(false, "Uploaded file is not a valid image", [], 422);
}

$uploadDir = "../../uploads/profile_images/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newFileName = "user_" . $authUser['id'] . "_" . time() . "." . $ext;
$targetPath = $uploadDir . $newFileName;
$relativePath = "uploads/profile_images/" . $newFileName;

if (!move_uploaded_file($tmpName, $targetPath)) {
    jsonResponse(false, "Failed to upload image", [], 500);
}

$oldImage = $authUser['profile_image'] ?? '';

$stmt = $conn->prepare("
    UPDATE users
    SET profile_image = ?
    WHERE id = ? AND role = 'user'
");
$stmt->bind_param("si", $relativePath, $authUser['id']);

if (!$stmt->execute()) {
    jsonResponse(false, "Failed to save image path", [], 500);
}

if (!empty($oldImage)) {
    $oldFilePath = "../../" . ltrim($oldImage, '/');
    if (file_exists($oldFilePath) && is_file($oldFilePath)) {
        @unlink($oldFilePath);
    }
}

jsonResponse(true, "Profile image uploaded successfully", [
    "profile_image" => $relativePath,
    "profile_image_url" => buildFileUrl($relativePath)
]);
?>