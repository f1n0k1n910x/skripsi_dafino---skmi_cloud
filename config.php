<?php
$servername = "localhost";
$username = "app2skmi_skmicloud"; // Your database username
$password = "apps2skmicloudstoragePrivate";     // Your database password
$dbname = "app2skmi_skmicloud"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
