<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db-connection/db_conn.php';

$type = trim($_REQUEST['type'] ?? '');
$value = trim($_REQUEST['value'] ?? '');

if (empty($type) || empty($value)) {
    echo json_encode(['success' => false, 'message' => 'Type and value parameters are required.']);
    exit;
}

if ($type === 'email') {
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT doctor_id FROM tbl_doctor WHERE email = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }

    $stmt->bind_param('s', $value);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        echo json_encode(['success' => true, 'exists' => true, 'message' => 'This email address is already registered.']);
    } else {
        echo json_encode(['success' => true, 'exists' => false, 'message' => 'Email address is available.']);
    }
    $stmt->close();
} elseif ($type === 'phone') {
    if (!preg_match('/^(97|98)\d{8}$/', $value)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT doctor_id FROM tbl_doctor WHERE phone_number = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }

    $stmt->bind_param('s', $value);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        echo json_encode(['success' => true, 'exists' => true, 'message' => 'This phone number is already registered.']);
    } else {
        echo json_encode(['success' => true, 'exists' => false, 'message' => 'Phone number is available.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid type requested.']);
}
?>
