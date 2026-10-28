<?php

declare(strict_types=1);

namespace App\Core;

use App\Repository\EmpresaRepository;
use App\Repository\UsuarioRepository;
use InvalidArgumentException;
use RuntimeException;

final class Auth
{
    private const HASH_DUMMY = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private const PERFIS_COM_ESCRITA = ['admin', 'contador'];

    private ?UsuarioRepository $usuarios;

    private ?EmpresaRepository $empresas;

    public function __construct(
        ?UsuarioRepository $usuarios = null,
        ?EmpresaRepository $empresas = null
    ) {
        $this->usuarios = $usuarios;
        $this->empresas = $empresas;
    }

    public function entrar(string $email, string $senha): ?string
    {
        $this->iniciarSessao();

        $usuario = ($this->usuarios ??= new UsuarioRepository())->buscarPorEmail($email);
        $hash = is_array($usuario) && isset($usuario['senha']) && is_string($usuario['senha'])
            ? $usuario['senha']
            : self::HASH_DUMMY;
        $senhaValida = password_verify($senha, $hash);

        if (
            $usuario === null
            || !isset($usuario['senha'])
            || !is_string($usuario['senha'])
            || !$senhaValida
        ) {
            return 'E-mail ou senha inválidos.';
        }

        if (($usuario['status'] ?? null) !== 'ativo') {
            return 'Este usuário está inativo.';
        }

        if (
            !isset($usuario['id_usuario'], $usuario['nome'], $usuario['email'], $usuario['perfil'])
            || !is_numeric($usuario['id_usuario'])
            || !is_string($usuario['nome'])
            || !is_string($usuario['email'])
            || !is_string($usuario['perfil'])
        ) {
            return 'Não foi possível autenticar este usuário.';
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Não foi possível renovar a sessão após a autenticação.');
        }

        $_SESSION['usuario'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nome' => (string) $usuario['nome'],
            'email' => (string) $usuario['email'],
            'perfil' => (string) $usuario['perfil'],
        ];

        return null;
    }

    public static function sair(): void
    {
        self::iniciarSessao();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parametros['path'],
                    'domain' => $parametros['domain'],
                    'secure' => $parametros['secure'],
                    'httponly' => $parametros['httponly'],
                    'samesite' => $parametros['samesite'] ?? 'Lax',
                ]
            );
        }

        if (!session_destroy()) {
            throw new RuntimeException('Não foi possível encerrar a sessão.');
        }
    }

    /** @return array{id_usuario: int, nome: string, email: string, perfil: string}|null */
    public function usuario(): ?array
    {
        $this->iniciarSessao();
        $usuario = $_SESSION['usuario'] ?? null;

        if (
            !is_array($usuario)
            || !isset($usuario['id_usuario'], $usuario['nome'], $usuario['email'], $usuario['perfil'])
            || !is_numeric($usuario['id_usuario'])
            || !is_string($usuario['nome'])
            || !is_string($usuario['email'])
            || !is_string($usuario['perfil'])
        ) {
            return null;
        }

        return [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'perfil' => $usuario['perfil'],
        ];
    }

    public function logado(): bool
    {
        return $this->usuario() !== null;
    }

    public function id(): int
    {
        return $this->usuario()['id_usuario'] ?? 0;
    }

    public function perfil(): string
    {
        return $this->usuario()['perfil'] ?? '';
    }

    public function exigirLogin(): void
    {
        if (!$this->logado()) {
            throw new RuntimeException('É necessário entrar para acessar este recurso.');
        }
    }

    /** @param list<string> $perfis */
    public function exigirPerfil(array $perfis): void
    {
        $this->exigirLogin();

        if (!in_array($this->perfil(), $perfis, true)) {
            throw new RuntimeException('Você não tem permissão para acessar este recurso.');
        }
    }

    public function exigirEscrita(): void
    {
        $this->exigirLogin();

        if (!$this->podeEscrever()) {
            throw new RuntimeException('Você não tem permissão para alterar este recurso.');
        }
    }

    public function podeEscrever(): bool
    {
        return in_array($this->perfil(), self::PERFIS_COM_ESCRITA, true);
    }

    public function empresaId(): ?int
    {
        $this->iniciarSessao();
        $empresaId = $_SESSION['empresa_id'] ?? null;

        if (is_int($empresaId) && $empresaId > 0) {
            $empresa = ($this->empresas ??= new EmpresaRepository())->buscar($empresaId);
            if (($empresa['status'] ?? null) === 'ativo') {
                return $empresaId;
            }
        }

        unset($_SESSION['empresa_id']);

        foreach (($this->empresas ??= new EmpresaRepository())->ativas() as $empresa) {
            if (isset($empresa['id_empresa']) && is_numeric($empresa['id_empresa'])) {
                $id = (int) $empresa['id_empresa'];
                if ($id > 0) {
                    $_SESSION['empresa_id'] = $id;

                    return $id;
                }
            }
        }

        return null;
    }

    public function definirEmpresa(int $id): void
    {
        $this->exigirLogin();

        if (
            $id <= 0
            || (($this->empresas ??= new EmpresaRepository())->buscar($id)['status'] ?? null) !== 'ativo'
        ) {
            throw new InvalidArgumentException('A empresa informada não existe ou não está ativa.');
        }

        $this->iniciarSessao();
        $_SESSION['empresa_id'] = $id;
    }

    private static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
    }
}
