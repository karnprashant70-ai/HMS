<?php
require_once 'db-connection/db_conn.php';

// Seed one test patient
$patientEmail = 'patient@medicare.com';
$checkPat = $conn->query("SELECT patient_id FROM tbl_patient WHERE email = '$patientEmail'");
if ($checkPat->num_rows === 0) {
    $hashedPwd = password_hash('Patient123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO tbl_patient (first_name, last_name, gender, phone_number, email, password, temporary_address, permanent_address, marital_status) VALUES ('John', 'Doe', 'Male', '9841234567', '$patientEmail', '$hashedPwd', 'Kathmandu', 'Kathmandu', 'Single')");
    echo "Patient seeded successfully.\n";
} else {
    echo "Patient already seeded.\n";
}

// Seed one test doctor
$doctorEmail = 'doctor@medicare.com';
$checkDoc = $conn->query("SELECT doctor_id FROM tbl_doctor WHERE email = '$doctorEmail'");
if ($checkDoc->num_rows === 0) {
    $hashedPwd = password_hash('Doctor123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO tbl_doctor (first_name, last_name, gender, phone_number, email, password, temporary_address, permanent_address, marital_status, department, specialization, qualification, licence_number, years_experience, consultation_fee, available_time, status) VALUES ('Jane', 'Smith', 'Female', '9847654321', '$doctorEmail', '$hashedPwd', 'Lalitpur', 'Lalitpur', 'Married', 'Cardiology', 'Cardiologist', 'MD Cardiology', 'LIC-12345', 10, 500.00, 'Mon-Fri 10:00 AM - 4:00 PM', 'Available')");
    echo "Doctor seeded successfully.\n";
} else {
    echo "Doctor already seeded.\n";
}
?>
