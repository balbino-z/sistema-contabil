<?php

declare(strict_types=1);

// Conexão com o banco de dados e definições de variáveis de ambiente.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: '3306'));
define('DB_NAME', getenv('DB_NAME') ?: 'sistema_contabil');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Configurações do aplicativo web.
define('APP_NAME', 'Sistema Contábil');

$appDebug = getenv('APP_DEBUG');
define(
    'APP_DEBUG',
    $appDebug !== false ? filter_var($appDebug, FILTER_VALIDATE_BOOLEAN) : false
);
