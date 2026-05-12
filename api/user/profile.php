<?php
header('Content-Type: application/json');

require '../helpers/auth_user.php';

$user = requireUserAuth($conn);

jsonResponse(true, "Profile fetched successfully", [
    "user" => [
        "id" => (int)$user["id"],
        "full_name" => $user["full_name"],
        "email" => $user["email"],
        "phone" => $user["phone"],
        "birth_date" => $user["birth_date"],
        "profile_image" => $user["profile_image"] ?? "",
        "profile_image_url" => buildFileUrl($user["profile_image"] ?? "")
    ]
]);
?>