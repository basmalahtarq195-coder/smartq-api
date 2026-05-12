<?php
header('Content-Type: application/json');

require '../../config/db.php';
require '../helpers/api_helper.php';

$placeId = intval($_GET['place_id'] ?? 0);

if ($placeId <= 0) {
    jsonResponse(false, "Place ID is required", [], 422);
}

$placeStmt = $conn->prepare("
    SELECT
        id,
        place_name,
        category,
        city,
        address,
        description,
        place_image,
        active
    FROM places
    WHERE id = ?
    LIMIT 1
");

if (!$placeStmt) {
    jsonResponse(false, "Failed to prepare place query", [], 500);
}

$placeStmt->bind_param("i", $placeId);
$placeStmt->execute();
$place = $placeStmt->get_result()->fetch_assoc();

if (!$place || (int)$place['active'] !== 1) {
    jsonResponse(false, "Place not found", [], 404);
}

$scheduleStmt = $conn->prepare("
    SELECT
        t.id,
        t.schedule_date,
        t.start_time,
        t.end_time,
        t.capacity,
        t.status,
        t.booked_count
    FROM (
        SELECT
            s.id,
            s.schedule_date,
            s.start_time,
            s.end_time,
            s.capacity,
            s.status,
            (
                SELECT COUNT(*)
                FROM appointments a
                WHERE a.schedule_id = s.id
                  AND a.status IN ('waiting', 'confirmed', 'done')
            ) AS booked_count
        FROM schedules s
        WHERE s.place_id = ?
          AND s.status = 'open'
          AND (
                s.schedule_date > CURDATE()
                OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME())
              )
    ) AS t
    WHERE t.booked_count < t.capacity
    ORDER BY t.schedule_date ASC, t.start_time ASC
");

if (!$scheduleStmt) {
    jsonResponse(false, "Failed to prepare schedule query", [], 500);
}

$scheduleStmt->bind_param("i", $placeId);
$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();

$schedules = [];

while ($row = $scheduleResult->fetch_assoc()) {
    $capacity = (int)$row['capacity'];
    $bookedCount = (int)$row['booked_count'];
    $availableSlots = max(0, $capacity - $bookedCount);

    $schedules[] = [
        "id" => (int)$row['id'],
        "schedule_date" => $row['schedule_date'],
        "start_time" => $row['start_time'],
        "end_time" => $row['end_time'],
        "capacity" => $capacity,
        "status" => $row['status'],
        "booked_count" => $bookedCount,
        "available_slots" => $availableSlots
    ];
}

jsonResponse(true, "Place details fetched successfully", [
    "place" => [
        "id" => (int)$place['id'],
        "place_name" => $place['place_name'],
        "category" => $place['category'],
        "city" => $place['city'],
        "address" => $place['address'],
        "description" => $place['description'],
        "place_image" => $place['place_image'] ?? "",
        "place_image_url" => buildFileUrl($place['place_image'] ?? "")
    ],
    "schedules" => $schedules
]);
?>