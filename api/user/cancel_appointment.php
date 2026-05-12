<?php
header('Content-Type: application/json');

require '../helpers/auth_user.php';

$authUser = requireUserAuth($conn);

$appointmentId = intval($_POST['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    jsonResponse(false, "Appointment ID is required", [], 422);
}

$checkStmt = $conn->prepare("
    SELECT
        a.id,
        a.status,
        s.schedule_date,
        s.end_time
    FROM appointments a
    INNER JOIN schedules s ON a.schedule_id = s.id
    WHERE a.id = ? AND a.user_id = ?
    LIMIT 1
");
$checkStmt->bind_param("ii", $appointmentId, $authUser['id']);
$checkStmt->execute();
$appointment = $checkStmt->get_result()->fetch_assoc();

if (!$appointment) {
    jsonResponse(false, "Appointment not found", [], 404);
}

if ($appointment['status'] === 'cancelled') {
    jsonResponse(false, "Appointment already cancelled", [], 400);
}

if ($appointment['status'] === 'done') {
    jsonResponse(false, "Completed appointment cannot be cancelled", [], 400);
}

$appointmentEnd = strtotime($appointment['schedule_date'] . ' ' . $appointment['end_time']);
if ($appointmentEnd < time()) {
    jsonResponse(false, "Past appointment cannot be cancelled", [], 400);
}

$stmt = $conn->prepare("
    UPDATE appointments
    SET status = 'cancelled'
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $appointmentId, $authUser['id']);

if (!$stmt->execute()) {
    jsonResponse(false, "Failed to cancel appointment", [], 500);
}

jsonResponse(true, "Appointment cancelled successfully");
?>