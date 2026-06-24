<?php
require_once __DIR__ . '/Database.php';
$pdo = Database::getInstance();
$stmt = $pdo->query("SELECT s.id, s.title, q.question_text FROM live_eval_sessions s JOIN live_eval_questions q ON s.id = q.session_id ORDER BY s.id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo "Session ID: {$r['id']} | Title: {$r['title']} | Q: {$r['question_text']}\n";
}
