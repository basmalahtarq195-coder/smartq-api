<?php
header('Content-Type: application/json');

require '../helpers/auth_user.php';

$authUser = requireUserAuth($conn);

$stmt = $conn->prepare("
    SELECT
        a.id,
        a.queue_number,
        a.status AS appointment_status,
        s.schedule_date,
        s.start_time,
        s.end_time,
        p.place_name,
        p.category,
        p.city,
        p.address,
        p.place_image
    FROM appointments a
    INNER JOIN schedules s ON a.schedule_id = s.id
    INNER JOIN places p ON s.place_id = p.id
    WHERE a.user_id = ?
      AND a.status IN ('waiting', 'confirmed')
      AND (
            s.schedule_date > CURDATE()
            OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME())
          )
    ORDER BY s.schedule_date ASC, s.start_time ASC
    LIMIT 1
");
$stmt->bind_param("i", $authUser['id']);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    jsonResponse(false, "No upcoming appointment found", [
        "appointment" => null
    ], 404);
}

jsonResponse(true, "Upcoming appointment fetched successfully", [
    "appointment" => [
        "id" => (int)$appointment['id'],
        "queue_number" => (int)$appointment['queue_number'],
        "appointment_status" => $appointment['appointment_status'],
        "schedule_date" => $appointment['schedule_date'],
        "start_time" => $appointment['start_time'],
        "end_time" => $appointment['end_time'],
        "place_name" => $appointment['place_name'],
        "category" => $appointment['category'],
        "city" => $appointment['city'],
        "address" => $appointment['address'],
        "place_image" => $appointment['place_image'] ?? "",
        "place_image_url" => buildFileUrl($appointment['place_image'] ?? "")
    ]
]);
?>