<?php
require_once __DIR__ . '/../Database.php';
$pdo = Database::getInstance();
$stmt = $pdo->query("SELECT id, title, start_time FROM live_eval_sessions ORDER BY id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo "ID: {$r['id']} | Title: {$r['title']}\n";
}
