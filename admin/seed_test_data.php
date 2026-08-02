<?php
/**
 * Insert test admin data into tbl_admin
 * Run this file once, then delete it.
 */
require_once __DIR__ . '/../db-connection/db_conn.php';

$testAdmins = [
    [
        'name'    => 'Prashant Karn',
        'email'   => 'prashant@medicare.com',
        'password'=> 'prashant123',
        'isAdmin' => 1,
        'isStaff' => 1
    ],
    [
        'name'    => 'Test Staff',
        'email'   => 'staff@medicare.com',
        'password'=> 'staff123',
        'isAdmin' => 0,
        'isStaff' => 1
    ]
];

$inserted = 0;
$skipped  = 0;

foreach ($testAdmins as $admin) {
    // Check if email already exists
    $check = $conn->prepare("SELECT admin_id FROM tbl_admin WHERE email = ?");
    $check->bind_param("s", $admin['email']);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        $check->close();
        continue;
    }
    $check->close();

    $hashed = password_hash($admin['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO tbl_admin (name, email, password, isAdmin, isStaff) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $admin['name'], $admin['email'], $hashed, $admin['isAdmin'], $admin['isStaff']);
    $stmt->execute();
    $stmt->close();
    $inserted++;
}

echo "<h2>✅ Done!</h2>";
echo "<p>Inserted: $inserted | Skipped (already exist): $skipped</p>";
echo "<hr>";
echo "<h3>All Admin Accounts:</h3>";
echo "<table border='1' cellpadding='8' cellspacing='0'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>isAdmin</th><th>isStaff</th><th>Created</th></tr>";

$all = $conn->query("SELECT admin_id, name, email, isAdmin, isStaff, createdAt FROM tbl_admin");
while ($row = $all->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['admin_id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td>" . ($row['isAdmin'] ? 'Yes' : 'No') . "</td>";
    echo "<td>" . ($row['isStaff'] ? 'Yes' : 'No') . "</td>";
    echo "<td>{$row['createdAt']}</td>";
    echo "</tr>";
}
echo "</table>";
echo "<br><p><a href='login.php'>→ Go to Admin Login</a></p>";
