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
        p.place_name
    FROM appointments a
    INNER JOIN schedules s ON a.schedule_id = s.id
    INNER JOIN places p ON s.place_id = p.id
    WHERE a.user_id = ?
    ORDER BY s.schedule_date DESC, s.start_time DESC
");

if (!$stmt) {
    jsonResponse(false, "Failed to prepare appointments query", [], 500);
}

$stmt->bind_param("i", $authUser['id']);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];

while ($row = $result->fetch_assoc()) {
    $appointments[] = [
        "id" => (int)$row["id"],
        "queue_number" => (int)$row["queue_number"],
        "appointment_status" => $row["appointment_status"],
        "schedule" => [
            "schedule_date" => $row["schedule_date"],
            "start_time" => $row["start_time"],
            "end_time" => $row["end_time"]
        ],
        "place" => [
            "place_name" => $row["place_name"]
        ]
    ];
}

jsonResponse(true, "Appointments fetched successfully", [
    "appointments" => $appointments
]);
?>