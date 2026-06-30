<?php
declare(strict_types=1);

// 404.php — Page d'erreur 404 personnalisée StudyVibe
$errorCode = 404;
$errorTitle = 'Égaré dans la bibliothèque.';
$errorMessage = 'Chaque rayon de notre savoir est à sa place — mais cette page a quitté les étagères. Peut-être cherche-t-elle sa cote Dewey.';
$badgeText = 'Page introuvable';
$typewriterLines = [
    '> ERREUR : chemin non résolu',
    '> Recherche dans 4,829 leçons… Introuvable.',
    '> Cote Dewey : 404.0 — Page manquante',
    '> Suggestion : retourner à l\'accueil →',
    '> Diagnostic : vous avez quitté le sentier.',
];

include __DIR__ . '/error.php';
exit;
