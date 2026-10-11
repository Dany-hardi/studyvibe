<?php
// Prints the two lines to add to .env (or to the host's environment) to switch web push on. Run once; keep the private part secret.
//   php scripts/generate-vapid.php
require_once __DIR__ . '/../lib/WebPush.php';
$k = WebPush::generateKeys();
echo "VAPID_PRIVATE_PEM_B64={$k['pem_b64']}\n";
echo "VAPID_SUBJECT=mailto:admin@studyvibe.edu\n";
echo "# public key shown to browsers (derived, nothing to store): {$k['public']}\n";
