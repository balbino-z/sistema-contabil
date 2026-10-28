<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Flash
{
    // Funções para adicionar mensagens de sucesso, erro e aviso na sessão do usuário.
    public static function sucesso(string $msg): void
    {
        self::adicionar('sucesso', $msg);
    }

    public static function erro(string $msg): void
    {
        self::adicionar('erro', $msg);
    }

    public static function aviso(string $msg): void
    {
        self::adicionar('aviso', $msg);
    }

    /**
     * @return array{sucesso: list<string>, erro: list<string>, aviso: list<string>}
     */

    // Função para consumir as mensagens de sucesso, erro e aviso da sessão do usuário e retornar em um array.
    public static function consumir(): array
    {
        self::iniciarSessao();

        $mensagens = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return [
            'sucesso' => self::normalizar($mensagens, 'sucesso'),
            'erro' => self::normalizar($mensagens, 'erro'),
            'aviso' => self::normalizar($mensagens, 'aviso'),
        ];
    }


    // Função para adicionar mensagens de sucesso, erro e aviso na sessão do usuário, garantindo que seja possível armazenar várias mensagens do mesmo tipo.
    private static function adicionar(string $tipo, string $msg): void
    {
        self::iniciarSessao();

        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }

        if (!isset($_SESSION['flash'][$tipo]) || !is_array($_SESSION['flash'][$tipo])) {
            $_SESSION['flash'][$tipo] = [];
        }

        $_SESSION['flash'][$tipo][] = $msg;
    }

    /**
     * @param mixed $mensagens
     * @return list<string>
     */
    
    // Função para normalizar as mensagens de sucesso, erro e aviso da sessão do usuário, garantindo que sejam retornadas apenas mensagens válidas em um array.
    private static function normalizar(mixed $mensagens, string $tipo): array
    {
        if (!is_array($mensagens) || !isset($mensagens[$tipo]) || !is_array($mensagens[$tipo])) {
            return [];
        }

        return array_values(array_filter($mensagens[$tipo], 'is_string'));
    }

    private static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
    }
}
