<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class EmpresaRepository
{
    private const CAMPOS_GRAVAVEIS = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'endereco',
        'regime_tributario',
        'responsavel',
        'status',
    ];

    private PDO $conexao;

    public function __construct()
    {
        $this->conexao = Database::getInstance()->getConnection();
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_empresa, razao_social, nome_fantasia, cnpj, endereco,
                    regime_tributario, responsavel, status
             FROM empresas
             WHERE id_empresa = :id'
        );
        $statement->execute(['id' => $id]);

        $empresa = $statement->fetch(PDO::FETCH_ASSOC);

        return $empresa === false ? null : $empresa;
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_empresa, razao_social, nome_fantasia, cnpj, endereco,
                    regime_tributario, responsavel, status
             FROM empresas
             ORDER BY razao_social ASC, id_empresa ASC'
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function salvar(array $dados): int
    {
        $dados = $this->filtrarDados($dados);

        if ($dados === []) {
            throw new InvalidArgumentException('Informe ao menos um campo válido para salvar a empresa.');
        }

        $colunas = array_keys($dados);
        $placeholders = array_map(
            static fn (string $coluna): string => ':' . $coluna,
            $colunas
        );

        $sql = sprintf(
            'INSERT INTO empresas (%s) VALUES (%s)',
            implode(', ', $colunas),
            implode(', ', $placeholders)
        );

        $statement = $this->conexao->prepare($sql);
        $statement->execute($dados);

        return (int) $this->conexao->lastInsertId();
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $id, array $dados): bool
    {
        $dados = $this->filtrarDados($dados);

        if ($dados === []) {
            throw new InvalidArgumentException('Informe ao menos um campo válido para atualizar a empresa.');
        }

        $atribuicoes = [];
        foreach (array_keys($dados) as $coluna) {
            $atribuicoes[] = $coluna . ' = :' . $coluna;
        }

        $dados['id_empresa'] = $id;
        $sql = sprintf(
            'UPDATE empresas SET %s WHERE id_empresa = :id_empresa',
            implode(', ', $atribuicoes)
        );

        $statement = $this->conexao->prepare($sql);
        $statement->execute($dados);

        return $statement->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function ativas(): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_empresa, razao_social, nome_fantasia, cnpj, endereco,
                    regime_tributario, responsavel, status
             FROM empresas
             WHERE status = :status'
        );
        $statement->execute(['status' => 'ativo']);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function filtrarDados(array $dados): array
    {
        return array_intersect_key($dados, array_flip(self::CAMPOS_GRAVAVEIS));
    }
}
