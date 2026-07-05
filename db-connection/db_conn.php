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
    years_experience INT(11) NOT NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    available_time VARCHAR(100) NOT NULL DEFAULT '',
    status ENUM('Available','Unavailable','On Leave') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($tableSql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

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
?>