<?php
$_POST['session_id'] = 10; // Change this to a valid session ID if known, or let it fail validation
$_POST['action'] = 'dispatch';
$_POST['registration_ids'] = '[1,2]';
$_SERVER['REQUEST_METHOD'] = 'POST';

// We need to bypass auth for the test
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Database.php';

echo "Env loaded: " . SMTP_HOST . "\n";
