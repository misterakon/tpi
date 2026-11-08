<?php

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// Aligne la casse du dossier sur celle de l'URL réellement demandée
$uri = $_SERVER['REQUEST_URI'] ?? '';
if ($base !== '' && strncasecmp($uri, $base, strlen($base)) === 0) {
    $base = substr($uri, 0, strlen($base));
}

$_SERVER['SCRIPT_NAME'] = $base.'/index.php';
$_SERVER['PHP_SELF'] = $base.'/index.php';

require __DIR__.'/public/index.php';