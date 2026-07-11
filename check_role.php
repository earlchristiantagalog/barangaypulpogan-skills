<?php
require_once __DIR__ . '/db.php';
$pdo = getDB();
$rows = $pdo->query("SELECT id, full_name, role FROM residents")->fetchAll();
echo "Current residents:\n";
foreach ($rows as $r) {
    echo "  ID: {$r['id']} | Name: {$r['full_name']} | Role: {$r['role']}\n";
}
