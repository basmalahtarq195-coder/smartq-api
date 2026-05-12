<?php
header('Content-Type: application/json');

require '../../config/db.php';
require '../helpers/api_helper.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$city = trim($_GET['city'] ?? '');

$sql = "
    SELECT
        id,
        place_name,
        category,
        city,
        address,
        description,
        place_image
    FROM places
    WHERE active = 1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (
        place_name LIKE ?
        OR city LIKE ?
        OR category LIKE ?
        OR address LIKE ?
        OR description LIKE ?
    )";

    $searchLike = "%{$search}%";
    $params = array_merge($params, [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike]);
    $types .= "sssss";
}

if ($category !== '' && strtolower($category) !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($city !== '' && strtolower($city) !== 'all') {
    $sql .= " AND city = ?";
    $params[] = $city;
    $types .= "s";
}

$sql .= " ORDER BY place_name ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$places = [];

while ($row = $result->fetch_assoc()) {
    $places[] = [
        "id" => (int)$row["id"],
        "place_name" => $row["place_name"],
        "category" => $row["category"],
        "city" => $row["city"],
        "address" => $row["address"],
        "description" => $row["description"],
        "place_image" => $row["place_image"] ?? "",
        "place_image_url" => buildFileUrl($row["place_image"] ?? "")
    ];
}

jsonResponse(true, "Places fetched successfully", [
    "places" => $places
]);
?>