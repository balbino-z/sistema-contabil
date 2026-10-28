<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\RegraNegocioException;
use InvalidArgumentException;
use PDO;
use PDOException;

final class ContaContabilRepository
{
    private const CAMPOS_GRAVAVEIS = [
        'id_empresa',
        'codigo',
        'descricao',
        'tipo',
        'natureza',
        'classificacao',
        'conta_pai',
        'nivel',
        'status',
    ];

    private PDO $conexao;

    public function __construct()
    {
        $this->conexao = Database::getInstance()->getConnection();
    }

    /** @return list<array<string, mixed>> */
    public function listarPorEmpresa(int $id_empresa): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_conta, id_empresa, codigo, descricao, tipo, natureza,
                    classificacao, conta_pai, nivel, status
             FROM contas_contabeis
             WHERE id_empresa = :id_empresa
             ORDER BY codigo ASC'
        );
        $statement->execute(['id_empresa' => $id_empresa]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listarAnaliticasAtivasPorEmpresa(int $id_empresa): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_conta, id_empresa, codigo, descricao, tipo, natureza,
                    classificacao, conta_pai, nivel, status
             FROM contas_contabeis
             WHERE id_empresa = :id_empresa
               AND tipo = :tipo
               AND status = :status
             ORDER BY codigo ASC'
        );
        $statement->execute([
            'id_empresa' => $id_empresa,
            'tipo' => 'analitica',
            'status' => 'ativo',
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_conta, id_empresa, codigo, descricao, tipo, natureza,
                    classificacao, conta_pai, nivel, status
             FROM contas_contabeis
             WHERE id_conta = :id'
        );
        $statement->execute(['id' => $id]);

        $conta = $statement->fetch(PDO::FETCH_ASSOC);

        return $conta === false ? null : $conta;
    }

    /** @return array<string, mixed>|null */
    public function buscarPorCodigo(int $id_empresa, string $codigo, ?int $ignorar_id = null): ?array
    {
        $sql = 'SELECT id_conta, id_empresa, codigo, descricao, tipo, natureza,
                       classificacao, conta_pai, nivel, status
                FROM contas_contabeis
                WHERE id_empresa = :id_empresa
                  AND codigo = :codigo';
        $parametros = [
            'id_empresa' => $id_empresa,
            'codigo' => $codigo,
        ];

        if ($ignorar_id !== null) {
            $sql .= ' AND id_conta <> :ignorar_id';
            $parametros['ignorar_id'] = $ignorar_id;
        }

        $statement = $this->conexao->prepare($sql);
        $statement->execute($parametros);
        $conta = $statement->fetch(PDO::FETCH_ASSOC);

        return $conta === false ? null : $conta;
    }

    /** @return list<array<string, mixed>> */
    public function listarSinteticasPorEmpresa(int $id_empresa): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_conta, id_empresa, codigo, descricao, tipo, natureza,
                    classificacao, conta_pai, nivel, status
             FROM contas_contabeis
             WHERE id_empresa = :id_empresa
               AND tipo = :tipo
             ORDER BY codigo ASC'
        );
        $statement->execute([
            'id_empresa' => $id_empresa,
            'tipo' => 'sintetica',
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function possuiSubcontas(int $id): bool
    {
        $statement = $this->conexao->prepare(
            'SELECT 1
             FROM contas_contabeis
             WHERE conta_pai = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function possuiLancamentos(int $id): bool
    {
        $statement = $this->conexao->prepare(
            'SELECT 1
             FROM lancamentos
             WHERE id_conta_debito = :id_debito
                OR id_conta_credito = :id_credito
             LIMIT 1'
        );
        $statement->execute([
            'id_debito' => $id,
            'id_credito' => $id,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function salvar(array $dados): int
    {
        $dados = $this->filtrarDados($dados);

        if ($dados === []) {
            throw new InvalidArgumentException('Informe ao menos um campo válido para salvar a conta contábil.');
        }

        $colunas = array_keys($dados);
        $placeholders = array_map(
            static fn (string $coluna): string => ':' . $coluna,
            $colunas
        );

        $sql = sprintf(
            'INSERT INTO contas_contabeis (%s) VALUES (%s)',
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
            throw new InvalidArgumentException('Informe ao menos um campo válido para atualizar a conta contábil.');
        }

        $atribuicoes = [];
        foreach (array_keys($dados) as $coluna) {
            $atribuicoes[] = $coluna . ' = :' . $coluna;
        }

        $dados['id_conta'] = $id;
        $sql = sprintf(
            'UPDATE contas_contabeis SET %s WHERE id_conta = :id_conta',
            implode(', ', $atribuicoes)
        );

        $statement = $this->conexao->prepare($sql);
        $statement->execute($dados);

        return $statement->rowCount() > 0;
    }

    public function excluir(int $id): bool
    {
        try {
            $statement = $this->conexao->prepare(
                'DELETE FROM contas_contabeis WHERE id_conta = :id'
            );
            $statement->execute(['id' => $id]);

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $codigoMySql = (int) ($exception->errorInfo[1] ?? 0);

            if ($sqlState === '23000' || in_array($codigoMySql, [1451, 1452], true)) {
                throw new RegraNegocioException(
                    'Esta conta não pode ser excluída pois possui subcontas ou lançamentos contábeis vinculados a ela.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }
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
