<?php
require 'db-connection/db_conn.php';
$conn->query("DROP TABLE IF EXISTS tbl_doctor");
echo "Dropped tbl_doctor\n";
require 'db-connection/db_conn.php';
echo "Recreated tbl_doctor\n";
