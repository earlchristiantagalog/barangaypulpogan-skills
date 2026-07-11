<?php
require_once __DIR__ . '/db.php';
$pdo = getDB();
$cols = $pdo->query("SHOW COLUMNS FROM residents")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('role', $cols)) {
    $pdo->exec("ALTER TABLE residents ADD COLUMN role ENUM('citizen','officer') NOT NULL DEFAULT 'citizen' AFTER password_hash");
    echo "role column added successfully.\n";
} else {
    echo "role column already exists.\n";
}
