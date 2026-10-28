<?php


declare(strict_types=1);

// Carregando as variáveis de ambiente automaticamente a partir do arquivo .env, para que não seja necessário usar vários requires.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    // Reject invalid class-name segments to prevent paths escaping src/.
    if (!preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/D', $relativeClass)) {
        return;
    }

    $file = __DIR__
        . DIRECTORY_SEPARATOR . 'src'
        . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
        . '.php';

    if (is_file($file)) {
        require $file;
    }
});
