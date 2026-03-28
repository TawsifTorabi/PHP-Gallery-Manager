<?php
$servername = "mysql-server"; // container name
$username = "root";          // your MySQL user
$password = "root";              // your MySQL password
$dbname = "image_gallery";   // your database

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
