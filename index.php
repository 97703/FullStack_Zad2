<?php
include 'db.php';

$result = $conn->query("SELECT * FROM attendance ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Lista obecności</title>

    <!-- Bootstrap 5 -->
    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >
</head>
<body class="bg-light">

<div class="container mt-5">

    <h1 class="mb-4">Lista obecności</h1>

    <a href="add.php" class="btn btn-primary mb-3">Dodaj studenta</a>

    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Imię i nazwisko</th>
                <th>Index</th>
                <th>Obecność</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['student_name'] ?></td>
                <td><?= $row['student_index'] ?></td>
                <td class="text-center">
                    <?= $row['present'] ? "✓" : "✗" ?>
                </td>
                <td>
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edytuj</a>
                    <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger">Usuń</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</div>

</body>
</html>