<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use PDO;
use InvalidArgumentException;

final class UsuarioRepository
{
    // Definindo os campos que podem ser gravados no banco de dados.
    private const CAMPOS_GRAVAVEIS = [
        'nome',
        'email',
        'senha',
        'perfil',
        'status',
        'data_cadastro',
    ];

    // Estabelecendo a conexão com o banco de dados usando PDO, garatindo que a conexão seja única e reutilizável em toda a aplicação.
    private PDO $conexao;

    // Função construtura para a conexão.
    public function __construct()
    {
        $this->conexao = Database::getInstance()->getConnection();
    }

    // Retornando um usuário pelo ID, caso não exista, retorna null.
    /** @return array<string, mixed>|null */

    // Função para buscar um usuário pelo ID, retornando um array associativo com os dados do usuário ou null caso não seja encontrado.
    public function buscar(int $id): ?array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_usuario, nome, email, senha, perfil, status, data_cadastro
             FROM usuarios
             WHERE id_usuario = :id'
        );
        $statement->execute(['id' => $id]);

        $usuario = $statement->fetch(PDO::FETCH_ASSOC);

        return $usuario === false ? null : $usuario;
    }

    // Retornando um usuário pelo e-mail, caso não exista, retorna null.
    /** @return array<string, mixed>|null */

    // Função para buscar um usuário pelo e-mail, retornando um array associativo com os dados do usuário ou null caso não seja encontrado.
    public function buscarPorEmail(string $email): ?array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_usuario, nome, email, senha, perfil, status, data_cadastro
             FROM usuarios
             WHERE email = :email'
        );
        $statement->execute(['email' => $email]);

        $usuario = $statement->fetch(PDO::FETCH_ASSOC);

        return $usuario === false ? null : $usuario;
    }

    /** @return list<array{id_usuario: int|string, nome: string, email: string, perfil: string, status: string}> */
    public function listar(): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_usuario, nome, email, perfil, status
             FROM usuarios
             ORDER BY nome ASC, id_usuario ASC'
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Função para buscar todos os usuários, retornando um array de arrays associativos com os dados dos usuários.
    /**
     * @param array<string, mixed> $dados
     */

    // Função para salvar um novo usuário no banco de dados, retornando o ID do usuário inserido.
    public function salvar(array $dados): int
    {
        $dados = $this->filtrarDados($dados);

        // Se não houver campos válidos para salvar, será lançada uma exceção.
        if ($dados === []) {
            throw new InvalidArgumentException('Informe ao menos um campo válido para salvar o usuário.');
        }

        $colunas = array_keys($dados);
        $placeholders = array_map(
            static fn (string $coluna): string => ':' . $coluna,
            $colunas
        );

        $sql = sprintf(
            'INSERT INTO usuarios (%s) VALUES (%s)',
            implode(', ', $colunas),
            implode(', ', $placeholders)
        );

        $statement = $this->conexao->prepare($sql);
        $statement->execute($dados);

        return (int) $this->conexao->lastInsertId();
    }

    // Função para atualizar um usuário existente no banco de dados, retornando true se a atualização foi bem-sucedida e false caso contrário.
    /**
     * @param array<string, mixed> $dados
     */

    // Função para atualizar um usuário existente no banco de dados, retornando true se a atualização foi bem-sucedida e false caso contrário.
    public function atualizar(int $id, array $dados): bool
    {
        $dados = $this->filtrarDados($dados);

        if ($dados === []) {
            throw new InvalidArgumentException('Informe ao menos um campo válido para atualizar o usuário.');
        }

        $atribuicoes = [];
        foreach (array_keys($dados) as $coluna) {
            $atribuicoes[] = $coluna . ' = :' . $coluna;
        }

        $dados['id_usuario'] = $id;
        $sql = sprintf(
            'UPDATE usuarios SET %s WHERE id_usuario = :id_usuario',
            implode(', ', $atribuicoes)
        );

        $statement = $this->conexao->prepare($sql);
        $statement->execute($dados);

        return $statement->rowCount() > 0;
    }

    // Função para deletar um usuário existente no banco de dados, retornando true se a exclusão foi bem-sucedida e false caso contrário.
    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */

    // Função para filtrar os dados do usuário, retornando apenas os campos que podem ser gravados no banco de dados.
    private function filtrarDados(array $dados): array
    {
        return array_intersect_key($dados, array_flip(self::CAMPOS_GRAVAVEIS));
    }
}
