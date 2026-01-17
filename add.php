<?php
include 'db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['student_name'];
    $index = $_POST['student_index'];
    $present = isset($_POST['present']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO attendance (student_name, student_index, present) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $index, $present);
    $stmt->execute();
    header("Location: index.php");
    exit;
}
?>

<h1>Dodaj studenta</h1>
<form method="post">
    Imię i nazwisko: <input type="text" name="student_name" required><br>
    Index: <input type="text" name="student_index" required><br>
    Obecność: <input type="checkbox" name="present"><br>
    <input type="submit" value="Dodaj">
</form>
<a href="index.php">Powrót do listy</a>