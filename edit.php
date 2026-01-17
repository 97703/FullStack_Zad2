<?php
include 'db.php';

$id = $_GET['id'];
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['student_name'];
    $index = $_POST['student_index'];
    $present = isset($_POST['present']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE attendance SET student_name=?, student_index=?, present=? WHERE id=?");
    $stmt->bind_param("ssii", $name, $index, $present, $id);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

$result = $conn->query("SELECT * FROM attendance WHERE id=$id");
$row = $result->fetch_assoc();
?>

<h1>Edytuj studenta</h1>
<form method="post">
    Imię i nazwisko: <input type="text" name="student_name" value="<?= $row['student_name'] ?>" required><br>
    Index: <input type="text" name="student_index" value="<?= $row['student_index'] ?>" required><br>
    Obecność: <input type="checkbox" name="present" <?= $row['present'] ? 'checked' : '' ?>><br>
    <input type="submit" value="Zapisz">
</form>
<a href="index.php">Powrót do listy</a>