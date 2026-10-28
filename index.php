<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();

if ($auth->logado()) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
