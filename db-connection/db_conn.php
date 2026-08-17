<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hsm";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Connect to the specific database
$conn->select_db($dbname);

// Create tbl_doctor table if not exists
$tableSql = "CREATE TABLE IF NOT EXISTS tbl_doctor (
    doctor_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    OTP CHAR(6) NOT NULL DEFAULT '000000',
    temporary_address TEXT NOT NULL,
    permanent_address TEXT NOT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    marital_status ENUM('Single','Married','Divorced','Widowed') NOT NULL,
    department VARCHAR(50) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    qualification VARCHAR(150) NOT NULL,
    licence_number VARCHAR(50) NOT NULL,
    years_experience VARCHAR(10) NOT NULL DEFAULT '0-3',
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    available_time VARCHAR(100) NOT NULL DEFAULT '',
    status ENUM('Available','Unavailable','On Leave') NOT NULL DEFAULT 'Available',
    verification_status ENUM('Unverified','Verified') NOT NULL DEFAULT 'Unverified',
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($tableSql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Migrate years_experience column from INT to VARCHAR(10) for range values
$conn->query("ALTER TABLE tbl_doctor MODIFY years_experience VARCHAR(10) NOT NULL DEFAULT '0-3'");

// Create tbl_admin table if not exists
$adminTableSql = "CREATE TABLE IF NOT EXISTS tbl_admin (
    admin_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    isAdmin TINYINT(1) NOT NULL DEFAULT 1,
    isStaff TINYINT(1) NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($adminTableSql) !== TRUE) {
    die("Error creating admin table: " . $conn->error);
}

// Insert default admin account if none exists
$checkAdmin = $conn->query("SELECT admin_id FROM tbl_admin LIMIT 1");
if ($checkAdmin->num_rows === 0) {
    $defaultName = "Super Admin";
    $defaultEmail = "admin@medicare.com";
    $defaultPassword = password_hash("admin123", PASSWORD_DEFAULT);
    $insertAdmin = $conn->prepare("INSERT INTO tbl_admin (name, email, password, isAdmin, isStaff) VALUES (?, ?, ?, 1, 1)");
    $insertAdmin->bind_param("sss", $defaultName, $defaultEmail, $defaultPassword);
    $insertAdmin->execute();
    $insertAdmin->close();
}

// Create tbl_department table if not exists
$deptTableSql = "CREATE TABLE IF NOT EXISTS tbl_department (
    department_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($deptTableSql) !== TRUE) {
    die("Error creating department table: " . $conn->error);
}

// Seed default departments if none exist
$checkDept = $conn->query("SELECT department_id FROM tbl_department LIMIT 1");
if ($checkDept->num_rows === 0) {
    $defaultDepts = ['Cardiology', 'Neurology', 'Orthopedics', 'Pediatrics', 'General Medicine'];
    $insertDept = $conn->prepare("INSERT INTO tbl_department (department_name) VALUES (?)");
    foreach ($defaultDepts as $deptName) {
        $insertDept->bind_param("s", $deptName);
        $insertDept->execute();
    }
    $insertDept->close();
}

// Create tbl_patient table if not exists
$patientTableSql = "CREATE TABLE IF NOT EXISTS tbl_patient (
    patient_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    OTP CHAR(6) NOT NULL DEFAULT '000000',
    temporary_address TEXT DEFAULT NULL,
    permanent_address TEXT DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    marital_status ENUM('Single','Married','Divorced','Widowed') DEFAULT NULL,
    occupation VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($patientTableSql) !== TRUE) {
    die("Error creating patient table: " . $conn->error);
}

// Create tbl_appointment table if not exists
$appointmentTableSql = "CREATE TABLE IF NOT EXISTS tbl_appointment (
    appointment_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    department_id INT(11) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    appointment_type VARCHAR(50) NOT NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Confirmed', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES tbl_patient(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES tbl_doctor(doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES tbl_department(department_id) ON DELETE CASCADE
)";

if ($conn->query($appointmentTableSql) !== TRUE) {
    die("Error creating appointment table: " . $conn->error);
}

// Ensure tbl_appointment has the status column for pending/confirmed tracking.
$statusColumnCheck = $conn->query("SHOW COLUMNS FROM tbl_appointment LIKE 'status'");
if ($statusColumnCheck && $statusColumnCheck->num_rows === 0) {
    $alterStatusSql = "ALTER TABLE tbl_appointment ADD COLUMN status ENUM('Pending','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Pending' AFTER appointment_type";
    if ($conn->query($alterStatusSql) !== TRUE) {
        die("Error adding status column to tbl_appointment: " . $conn->error);
    }
}

// Ensure tbl_appointment has the reschedule_note column for patient reschedule requests.
$rescheduleNoteCheck = $conn->query("SHOW COLUMNS FROM tbl_appointment LIKE 'reschedule_note'");
if ($rescheduleNoteCheck && $rescheduleNoteCheck->num_rows === 0) {
    $alterRescheduleSql = "ALTER TABLE tbl_appointment ADD COLUMN reschedule_note VARCHAR(255) DEFAULT NULL AFTER status";
    if ($conn->query($alterRescheduleSql) !== TRUE) {
        die("Error adding reschedule_note column to tbl_appointment: " . $conn->error);
    }
}

// Ensure tbl_appointment has the report column for doctor report notes.
$reportCheck = $conn->query("SHOW COLUMNS FROM tbl_appointment LIKE 'report'");
if ($reportCheck && $reportCheck->num_rows === 0) {
    $alterReportSql = "ALTER TABLE tbl_appointment ADD COLUMN report TEXT DEFAULT NULL AFTER reschedule_note";
    if ($conn->query($alterReportSql) !== TRUE) {
        die("Error adding report column to tbl_appointment: " . $conn->error);
    }
}

// Ensure tbl_appointment has the investigation column for doctor investigation notes.
$investigationCheck = $conn->query("SHOW COLUMNS FROM tbl_appointment LIKE 'investigation'");
if ($investigationCheck && $investigationCheck->num_rows === 0) {
    $alterInvestigationSql = "ALTER TABLE tbl_appointment ADD COLUMN investigation TEXT DEFAULT NULL AFTER report";
    if ($conn->query($alterInvestigationSql) !== TRUE) {
        die("Error adding investigation column to tbl_appointment: " . $conn->error);
    }
}

// Ensure tbl_appointment has the follow_up column for doctor follow-up instructions.
$followUpCheck = $conn->query("SHOW COLUMNS FROM tbl_appointment LIKE 'follow_up'");
if ($followUpCheck && $followUpCheck->num_rows === 0) {
    $alterFollowUpSql = "ALTER TABLE tbl_appointment ADD COLUMN follow_up TEXT DEFAULT NULL AFTER investigation";
    if ($conn->query($alterFollowUpSql) !== TRUE) {
        die("Error adding follow_up column to tbl_appointment: " . $conn->error);
    }
}

// Create tbl_follow_up table if not exists
$followUpTableSql = "CREATE TABLE IF NOT EXISTS tbl_follow_up (
    follow_up_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT(11) NOT NULL,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    follow_up_date DATE NOT NULL,
    follow_up_reason TEXT NOT NULL,
    status ENUM('Pending', 'Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES tbl_appointment(appointment_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES tbl_patient(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES tbl_doctor(doctor_id) ON DELETE CASCADE
)";

if ($conn->query($followUpTableSql) !== TRUE) {
    die("Error creating tbl_follow_up table: " . $conn->error);
}

// Ensure tbl_doctor has shift_start, shift_end, slot_duration
$shiftStartCheck = $conn->query("SHOW COLUMNS FROM tbl_doctor LIKE 'shift_start'");
if ($shiftStartCheck && $shiftStartCheck->num_rows === 0) {
    $conn->query("ALTER TABLE tbl_doctor ADD COLUMN shift_start TIME DEFAULT '09:00:00'");
}
$shiftEndCheck = $conn->query("SHOW COLUMNS FROM tbl_doctor LIKE 'shift_end'");
if ($shiftEndCheck && $shiftEndCheck->num_rows === 0) {
    $conn->query("ALTER TABLE tbl_doctor ADD COLUMN shift_end TIME DEFAULT '17:00:00'");
}
$slotDurationCheck = $conn->query("SHOW COLUMNS FROM tbl_doctor LIKE 'slot_duration'");
if ($slotDurationCheck && $slotDurationCheck->num_rows === 0) {
    $conn->query("ALTER TABLE tbl_doctor ADD COLUMN slot_duration INT DEFAULT 30");
}
$verStatusCheck = $conn->query("SHOW COLUMNS FROM tbl_doctor LIKE 'verification_status'");
if ($verStatusCheck && $verStatusCheck->num_rows === 0) {
    $conn->query("ALTER TABLE tbl_doctor ADD COLUMN verification_status ENUM('Unverified', 'Verified') NOT NULL DEFAULT 'Unverified'");
}
$isArchivedCheck = $conn->query("SHOW COLUMNS FROM tbl_doctor LIKE 'is_archived'");
if ($isArchivedCheck && $isArchivedCheck->num_rows === 0) {
    $conn->query("ALTER TABLE tbl_doctor ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
}
$ratingCheck = $conn->query("SHOW COLUMNS FROM tbl_doctor LIKE 'rating'");
if ($ratingCheck && $ratingCheck->num_rows === 0) {
    $conn->query("ALTER TABLE tbl_doctor ADD COLUMN rating DECIMAL(3,2) NOT NULL DEFAULT 0.00");
}

// Create tbl_prescription table if not exists
$prescriptionTableSql = "CREATE TABLE IF NOT EXISTS tbl_prescription (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT(11) NOT NULL,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    medications TEXT NOT NULL,
    instructions TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES tbl_appointment(appointment_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES tbl_patient(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES tbl_doctor(doctor_id) ON DELETE CASCADE
)";
if ($conn->query($prescriptionTableSql) !== TRUE) {
    die("Error creating tbl_prescription table: " . $conn->error);
}

// Create tbl_rating table if not exists
$ratingTableSql = "CREATE TABLE IF NOT EXISTS tbl_rating (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT(11) NOT NULL UNIQUE,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    rating_stars TINYINT(1) NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES tbl_appointment(appointment_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES tbl_patient(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES tbl_doctor(doctor_id) ON DELETE CASCADE
)";
if ($conn->query($ratingTableSql) !== TRUE) {
    die("Error creating tbl_rating table: " . $conn->error);
}
?>