<?php
session_start();
$_SESSION['patient_id'] = 1; // force session

// mock POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'book_appointment' => '1',
    'department_id' => '1',
    'doctor_id' => '1',
    'appointment_date' => '2026-10-10',
    'appointment_time' => '10:00',
    'appointment_type' => 'In-Person'
];

ob_start();
include 'appointments.php';
$output = ob_get_clean();

echo "=== TEST BOOK SCRIPT ===\n";
if (isset($_SESSION['appt_success'])) {
    echo "SUCCESS: " . $_SESSION['appt_success'] . "\n";
} else {
    echo "NO SUCCESS MESSAGE.\n";
    // extract errors from HTML output
    preg_match_all('/<li>(.*?)<\/li>/', $output, $matches);
    if (!empty($matches[1])) {
        echo "ERRORS FOUND IN HTML:\n";
        print_r($matches[1]);
    }
}
