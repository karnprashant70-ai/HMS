<?php
ini_set("mysqli.default_socket", "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock");

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

// Insert default admin account if not exists
$checkAdmin = $conn->prepare("SELECT admin_id FROM tbl_admin WHERE email = ?");
$defaultEmail = "admin@medicare.com";
$checkAdmin->bind_param("s", $defaultEmail);
$checkAdmin->execute();
if ($checkAdmin->get_result()->num_rows === 0) {
    $defaultName = "Super Admin";
    $defaultPassword = password_hash("admin123", PASSWORD_DEFAULT);
    $insertAdmin = $conn->prepare("INSERT INTO tbl_admin (name, email, password, isAdmin, isStaff) VALUES (?, ?, ?, 1, 1)");
    $insertAdmin->bind_param("sss", $defaultName, $defaultEmail, $defaultPassword);
    $insertAdmin->execute();
    $insertAdmin->close();
}
$checkAdmin->close();

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

// Ensure tbl_appointment has created_at column
$apptCreatedAtCheck = $conn->query("SHOW COLUMNS FROM tbl_appointment LIKE 'created_at'");
if ($apptCreatedAtCheck && $apptCreatedAtCheck->num_rows === 0) {
    $alterApptCreatedAt = "ALTER TABLE tbl_appointment ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
    if ($conn->query($alterApptCreatedAt) !== TRUE) {
        die("Error adding created_at column to tbl_appointment: " . $conn->error);
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

// Create tbl_bug_report table if not exists
$bugTableSql = "CREATE TABLE IF NOT EXISTS tbl_bug_report (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(20) NOT NULL UNIQUE,
    reporter_name VARCHAR(100) NOT NULL,
    reporter_email VARCHAR(150) NOT NULL,
    user_role ENUM('Patient', 'Doctor', 'Visitor', 'Administrator') DEFAULT 'Visitor',
    bug_title VARCHAR(255) NOT NULL,
    bug_category VARCHAR(100) NOT NULL,
    severity ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    steps_to_reproduce TEXT NOT NULL,
    expected_behavior TEXT DEFAULT NULL,
    actual_behavior TEXT DEFAULT NULL,
    browser_os VARCHAR(255) DEFAULT NULL,
    screenshot_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Open', 'Under Review', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Open',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($bugTableSql) !== TRUE) {
    die("Error creating tbl_bug_report table: " . $conn->error);
}

// Create tbl_blog table if not exists
$blogTableSql = "CREATE TABLE IF NOT EXISTS tbl_blog (
    blog_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    excerpt TEXT NOT NULL,
    content LONGTEXT NOT NULL,
    author_name VARCHAR(100) DEFAULT 'Dr. Ram Sharma',
    author_role VARCHAR(100) DEFAULT 'Chief Neurologist & Medical Advisor',
    cover_image VARCHAR(255) DEFAULT NULL,
    read_time VARCHAR(20) DEFAULT '5 min read',
    is_featured TINYINT(1) DEFAULT 0,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($blogTableSql) !== TRUE) {
    die("Error creating tbl_blog table: " . $conn->error);
}

// Seed initial blog posts if empty
$blogCheck = $conn->query("SELECT COUNT(*) AS count FROM tbl_blog");
if ($blogCheck && $blogCheck->fetch_assoc()['count'] == 0) {
    $sampleBlogs = [
        [
            'title'        => '4 Essential Habits for a Healthy Heart: Cardiologist Guidelines',
            'slug'         => 'essential-habits-healthy-heart',
            'category'     => 'Cardiology',
            'excerpt'      => 'Cardiovascular diseases remain the leading global cause of mortality. Learn key dietary changes, exercise protocols, and daily habits recommended by heart specialists.',
            'content'      => '<p>Maintaining optimal cardiovascular health is one of the most vital investments you can make for longevity and quality of life. Cardiovascular conditions can often be prevented through proactive lifestyle habits.</p><h3>1. Prioritize Aerobic Activity</h3><p>Engage in at least 150 minutes of moderate aerobic exercise (such as brisk walking, cycling, or swimming) every week. Regular activity improves circulation and strengthens heart muscle efficiency.</p><h3>2. Adopt a Mediterranean-Style Diet</h3><p>Focus on leafy greens, antioxidant-rich berries, olive oil, nuts, and lean proteins while reducing excessive saturated fats and processed sodium intake.</p><h3>3. Monitor Blood Pressure & Cholesterol Regularly</h3><p>Hypertension is often called a silent condition. Regular check-ups with your cardiologist help detect arterial stiffness early before symptoms arise.</p><h3>4. Manage Chronic Stress</h3><p>High cortisol levels over extended periods contribute to increased vascular inflammation. Practice mindfulness, proper sleep hygiene (7-8 hours nightly), and breathing exercises.</p>',
            'author_name'  => 'Dr. Makunda Timalsina',
            'author_role'  => 'Senior Physician & Cardiologist',
            'read_time'    => '4 min read',
            'is_featured'  => 1
        ],
        [
            'title'        => 'Understanding Migraines vs. Tension Headaches: Causes and Relief',
            'slug'         => 'understanding-migraines-vs-tension-headaches',
            'category'     => 'Neurology',
            'excerpt'      => 'Frequent headaches can significantly impair daily productivity. Discover the neurological distinctions between migraines, cluster headaches, and tension aches.',
            'content'      => '<p>Headaches affect millions of people worldwide, yet many individuals struggle to differentiate between standard tension headaches and debilitating neurological migraines.</p><h3>What Is a Tension Headache?</h3><p>Tension headaches typically present as a dull, aching band of pressure around the forehead and temples. They are most commonly triggered by postural strain, screen fatigue, and dehydration.</p><h3>The Anatomy of a Migraine</h3><p>Migraines are complex neurological events characterized by throbbing unilateral pain, nausea, sensitivity to light (photophobia), and visual auras. They involve rapid neurotransmitter shifts affecting the trigeminal nerve.</p><h3>When to Consult a Neurologist</h3><p>If your headaches occur more than twice weekly, resist over-the-counter pain relievers, or are accompanied by numbness or speech difficulties, schedule a clinical neurological evaluation immediately.</p>',
            'author_name'  => 'Dr. Ram Sharma',
            'author_role'  => 'Chief Neurologist & Medical Advisor',
            'read_time'    => '6 min read',
            'is_featured'  => 0
        ],
        [
            'title'        => 'Pediatric Health: Essential Vaccines & Developmental Milestones',
            'slug'         => 'pediatric-health-essential-vaccines',
            'category'     => 'Pediatrics',
            'excerpt'      => 'A comprehensive guide for parents on childhood immunization schedules, nutritional requirements for toddlers, and cognitive milestones.',
            'content'      => '<p>Every parent wants their child to grow up strong, happy, and resilient. Understanding childhood immunization timelines and developmental benchmarks empowers families to safeguard their little ones.</p><h3>The Power of Timely Immunization</h3><p>Childhood vaccines protect against severe communicable diseases including measles, mumps, rubella, polio, and hepatitis. Adhering strictly to national vaccination calendars ensures herd immunity and long-term protection.</p><h3>Tracking Key Developmental Milestones</h3><p>From motor skills (rolling, crawling, unassisted walking) to verbal fluency, tracking milestones allows pediatricians to detect sensory or speech delays early when intervention is most effective.</p><h3>Balanced Nutrition for Growing Bodies</h3><p>Ensure a rich intake of calcium, Vitamin D, iron, and zinc during key growth spurts. Limit processed sugars and sweetened fruit juices.</p>',
            'author_name'  => 'Dr. Jane Doe',
            'author_role'  => 'Pediatric Specialist',
            'read_time'    => '5 min read',
            'is_featured'  => 0
        ],
        [
            'title'        => 'Modern Dental Hygiene: Preventing Gum Disease and Enamel Wear',
            'slug'         => 'modern-dental-hygiene-preventing-gum-disease',
            'category'     => 'Dentistry',
            'excerpt'      => 'Oral health directly impacts systemic wellness. Learn proper brushing techniques, flossing strategies, and the science behind enamel remineralization.',
            'content'      => '<p>Your mouth is the gateway to your overall health. Emerging medical research consistently connects periodontal inflammation with cardiovascular conditions and metabolic disorders.</p><h3>The Two-Minute Rule</h3><p>Brush twice daily with a soft-bristled brush and fluoride toothpaste. Avoid brushing immediately after consuming acidic foods to prevent enamel abrasion.</p><h3>Interdental Cleaning is Non-Negotiable</h3><p>Brushing reaches only 60% of tooth surfaces. Daily flossing or water picking removes plaque colonies before they calcify into tartar.</p><h3>Bi-Annual Professional Cleanings</h3><p>Routine dental scaling every 6 months removes deep calculus and allows early diagnosis of cavities before root canal treatments become necessary.</p>',
            'author_name'  => 'Dr. RIYAN Khan',
            'author_role'  => 'Lead Dental Surgeon & Orthodontist',
            'read_time'    => '4 min read',
            'is_featured'  => 0
        ],
        [
            'title'        => 'How Artificial Intelligence & Smart Records Are Transforming Healthcare',
            'slug'         => 'ai-smart-records-transforming-healthcare',
            'category'     => 'Healthcare Tech',
            'excerpt'      => 'Explore how modern hospital management platforms, digital appointments, and AI-assisted diagnostics are enhancing patient care outcomes.',
            'content'      => '<p>Digital health technology is revolutionizing modern clinical delivery, transitioning hospital administration from siloed paper files into real-time, encrypted health platforms.</p><h3>Instant Diagnostic Timelines</h3><p>With centralized electronic health records (EHR), attending physicians can instantly review past prescriptions, lab tests, and allergy warnings across departments in seconds.</p><h3>Reducing Patient Wait Times</h3><p>Smart scheduling algorithms dynamically balance physician shift loads, eliminating clinic congestion and allowing patients to book and reschedule visits effortlessly.</p><h3>The Future: Predictive Care</h3><p>Integrated analytics allow hospital teams to detect epidemiological trends and optimize medication inventories proactively.</p>',
            'author_name'  => 'Medi-Care Engineering Team',
            'author_role'  => 'Health Informatics Group',
            'read_time'    => '5 min read',
            'is_featured'  => 0
        ]
    ];

    foreach ($sampleBlogs as $b) {
        $bStmt = $conn->prepare("INSERT INTO tbl_blog (title, slug, category, excerpt, content, author_name, author_role, read_time, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $bStmt->bind_param("ssssssssi", $b['title'], $b['slug'], $b['category'], $b['excerpt'], $b['content'], $b['author_name'], $b['author_role'], $b['read_time'], $b['is_featured']);
        $bStmt->execute();
        $bStmt->close();
    }
}
?>