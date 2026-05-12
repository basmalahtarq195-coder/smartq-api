<?php
header('Content-Type: application/json');

require '../helpers/auth_user.php';

$authUser = requireUserAuth($conn);

$scheduleId = intval($_POST['schedule_id'] ?? 0);
$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerAge = trim($_POST['customer_age'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($scheduleId <= 0 || $customerName === '' || $customerPhone === '' || $customerEmail === '') {
    jsonResponse(false, "Missing required fields", [], 422);
}

if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, "Invalid email address", [], 422);
}

if ($customerAge !== '' && (!is_numeric($customerAge) || (int)$customerAge < 0 || (int)$customerAge > 120)) {
    jsonResponse(false, "Invalid customer age", [], 422);
}

$conn->begin_transaction();

try {
    $scheduleStmt = $conn->prepare("
        SELECT
            s.id,
            s.capacity,
            s.status,
            s.schedule_date,
            s.start_time,
            s.end_time,
            p.place_name
        FROM schedules s
        INNER JOIN places p ON s.place_id = p.id
        WHERE s.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $scheduleStmt->bind_param("i", $scheduleId);
    $scheduleStmt->execute();
    $schedule = $scheduleStmt->get_result()->fetch_assoc();

    if (!$schedule) {
        throw new Exception("Schedule not found");
    }

    if ($schedule['status'] !== 'open') {
        throw new Exception("This schedule is closed");
    }

    $scheduleDateTimeEnd = strtotime($schedule['schedule_date'] . ' ' . $schedule['end_time']);
    if ($scheduleDateTimeEnd < time()) {
        throw new Exception("This schedule has already ended");
    }

    $dupStmt = $conn->prepare("
        SELECT id
        FROM appointments
        WHERE user_id = ? AND schedule_id = ?
        LIMIT 1
    ");
    $dupStmt->bind_param("ii", $authUser['id'], $scheduleId);
    $dupStmt->execute();
    $dup = $dupStmt->get_result()->fetch_assoc();

    if ($dup) {
        throw new Exception("You already booked this schedule");
    }

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM appointments
        WHERE schedule_id = ? AND status IN ('waiting', 'confirmed', 'done')
    ");
    $countStmt->bind_param("i", $scheduleId);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();

    $currentCount = (int)$countRow['total'];
    $capacity = (int)$schedule['capacity'];

    if ($currentCount >= $capacity) {
        throw new Exception("This schedule is full");
    }

    $queueStmt = $conn->prepare("
        SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_queue
        FROM appointments
        WHERE schedule_id = ?
        FOR UPDATE
    ");
    $queueStmt->bind_param("i", $scheduleId);
    $queueStmt->execute();
    $queueRow = $queueStmt->get_result()->fetch_assoc();
    $queueNumber = (int)$queueRow['next_queue'];

    $ageValue = ($customerAge === '') ? null : (int)$customerAge;

    $insertStmt = $conn->prepare("
        INSERT INTO appointments
        (user_id, schedule_id, customer_name, customer_phone, customer_email, customer_age, queue_number, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'waiting')
    ");
    $insertStmt->bind_param(
        "iisssiis",
        $authUser['id'],
        $scheduleId,
        $customerName,
        $customerPhone,
        $customerEmail,
        $ageValue,
        $queueNumber,
        $notes
    );

    if (!$insertStmt->execute()) {
        throw new Exception("Failed to create appointment");
    }

    $appointmentId = $insertStmt->insert_id;

    $conn->commit();

    jsonResponse(true, "Appointment booked successfully", [
        "appointment_id" => (int)$appointmentId,
        "queue_number" => $queueNumber,
        "place_name" => $schedule['place_name'],
        "schedule_date" => $schedule['schedule_date'],
        "start_time" => $schedule['start_time'],
        "end_time" => $schedule['end_time']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, $e->getMessage(), [], 400);
}
?>