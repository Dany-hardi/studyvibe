<?php
require 'Mailer.php';
echo "Testing SMTP...\n";
$start = microtime(true);
$res = Mailer::send('test@example.com', 'Test', 'Hello');
$end = microtime(true);
echo "Result: " . ($res ? 'Success' : 'Failed') . "\n";
echo "Time: " . ($end - $start) . " seconds\n";
if (!$res) {
    echo "Error: " . Mailer::getLastError() . "\n";
}
