<?php
// Router for `php -S` (see dev-server.sh): the same /jobs/<id> addresses that .htaccess provides on Apache.
// Uploaded files are never served directly, as on Apache (docker/apache-tuning.conf): PHP reads them after checking rights.
if (str_starts_with((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/uploads/')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if (preg_match('#^/jobs/([0-9]{1,12}|open)/?$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $m)) {
    $_GET['job'] = $m[1];
    require __DIR__ . '/career.php';
    return true;
}
return false;
