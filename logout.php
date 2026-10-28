<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !session_start()) {
    throw new RuntimeException('Não foi possível iniciar a sessão.');
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'config.php';

App\Core\Auth::sair();

header('Location: login.php');
exit;
