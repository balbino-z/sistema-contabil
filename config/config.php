<?php

declare(strict_types=1);

// Conexão com o banco de dados e definições de variáveis de ambiente.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: '3306'));
define('DB_NAME', getenv('DB_NAME') ?: 'sistema_contabil');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// SSL/TLS para bancos gerenciados na nuvem (ex.: Aiven, que exige conexão segura).
// DB_SSL=true ativa o TLS; DB_SSL_CA (caminho) ou DB_SSL_CA_PEM (conteúdo do certificado)
// informam a CA para validar o servidor.
define('DB_SSL', filter_var(getenv('DB_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');
define('DB_SSL_CA_PEM', getenv('DB_SSL_CA_PEM') ?: '');

// Configurações do aplicativo web.
define('APP_NAME', 'Sistema Contábil');

$appDebug = getenv('APP_DEBUG');
define(
    'APP_DEBUG',
    $appDebug !== false ? filter_var($appDebug, FILTER_VALIDATE_BOOLEAN) : false
);
