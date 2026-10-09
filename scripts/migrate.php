<?php
declare(strict_types=1);

/**
 * Applies every pending schema change from the command line, for deploy scripts and CI:
 *
 *   php scripts/migrate.php
 *
 * The same migrations run on the first web request after a deployment (see Database.php); this just lets a deploy
 * run them before traffic arrives. Safe to run any number of times.
 */

require_once __DIR__ . '/../Database.php';

Database::getInstance();
Database::forceMigrate();
echo "Schema is up to date.\n";
