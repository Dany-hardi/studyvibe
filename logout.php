<?php
declare(strict_types=1);

// 1. Forcer le démarrage de la session si elle n'est pas active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Vider complètement toutes les variables de session
$_SESSION = [];

// 3. Détruire le cookie de session dans le navigateur (Très important pour Serveo/Render)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, // Date d'expiration passée pour forcer la suppression
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// 4. Détruire la session sur le serveur
session_destroy();

// 5. Redirection relative propre sans slash initial pour rester sur Serveo
header('Location: index.php');
exit;
