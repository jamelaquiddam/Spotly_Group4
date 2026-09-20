<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/release_noshows.php';

$count = releaseNoShows($pdo);
echo 'Released ' . $count . " no-show reservation(s)." . PHP_EOL;
