<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Csrf
{

    // Função para gerar o token CSRF e armazenar na sessão do usuário, garantindo que seja único para cada sessão.
    public static function token(): string
    {
        self::iniciarSessao();

        // Se a sessão não tiver um token CSRF, será gerado um novo token aleatório e armazenado na sessão.
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        // Retornando o token CSRF armazenado na sessão do usuário.
        return $_SESSION['csrf'];
    }

    public static function campo(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<input type="hidden" name="csrf" value="' . $token . '">';
    }


    // Função para exigir o token CSRF e comparar com o token enviado pelo formulário.
    public static function exigir(): void
    {
        self::iniciarSessao();

        $tokenEnviado = $_POST['csrf'] ?? null;
        $tokenSessao = $_SESSION['csrf'] ?? null;

        if (
            !is_string($tokenEnviado)
            || !is_string($tokenSessao)
            || !hash_equals($tokenSessao, $tokenEnviado)
        ) {
            http_response_code(419);
            exit;
        }
    }

    // Função para iniciar a sessão do usuário, caso ainda não tenha sido iniciada, garantindo que o token CSRF seja armazenado na sessão.
    private static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
    }
}
