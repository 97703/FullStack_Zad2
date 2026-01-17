<?php
$host = "my-mysql"; // nazwa serwisu w Kubernetes
$user = "app";
$password = "apppass";
$database = "appdb";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>