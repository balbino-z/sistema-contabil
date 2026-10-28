<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class LancamentoRepository
{
    private const CAMPOS_GRAVAVEIS = [
        'id_empresa',
        'id_exercicio',
        'data',
        'historico',
        'documento',
        'valor',
        'id_conta_debito',
        'id_conta_credito',
        'id_centro_custo',
        'id_usuario',
        'status',
    ];

    private PDO $conexao;

    public function __construct()
    {
        $this->conexao = Database::getInstance()->getConnection();
    }

    /** @return list<array<string, mixed>> */
    public function listarPorEmpresaEExercicio(int $id_empresa, int $id_exercicio): array
    {
        $statement = $this->conexao->prepare(
            'SELECT l.id_lancamento, l.id_empresa, l.id_exercicio, l.data,
                    l.historico, l.documento, l.valor, l.id_conta_debito,
                    l.id_conta_credito, l.id_centro_custo, l.id_usuario, l.status,
                    debito.descricao AS descricao_conta_debito,
                    credito.descricao AS descricao_conta_credito
             FROM lancamentos AS l
             INNER JOIN contas_contabeis AS debito
                ON debito.id_conta = l.id_conta_debito
             INNER JOIN contas_contabeis AS credito
                ON credito.id_conta = l.id_conta_credito
             WHERE l.id_empresa = :id_empresa
               AND l.id_exercicio = :id_exercicio
             ORDER BY l.data DESC, l.id_lancamento DESC'
        );
        $statement->execute([
            'id_empresa' => $id_empresa,
            'id_exercicio' => $id_exercicio,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listarRecentesPorEmpresaEExercicio(int $id_empresa, int $id_exercicio): array
    {
        $statement = $this->conexao->prepare(
            'SELECT l.id_lancamento, l.data, l.historico, l.documento, l.valor, l.status,
                    debito.descricao AS descricao_conta_debito,
                    credito.descricao AS descricao_conta_credito
             FROM lancamentos AS l
             INNER JOIN contas_contabeis AS debito
                ON debito.id_conta = l.id_conta_debito
             INNER JOIN contas_contabeis AS credito
                ON credito.id_conta = l.id_conta_credito
             WHERE l.id_empresa = :id_empresa
               AND l.id_exercicio = :id_exercicio
             ORDER BY l.data DESC, l.id_lancamento DESC
             LIMIT 5'
        );
        $statement->execute([
            'id_empresa' => $id_empresa,
            'id_exercicio' => $id_exercicio,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id_lancamento): ?array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_lancamento, id_empresa, id_exercicio, data, historico,
                    documento, valor, id_conta_debito, id_conta_credito,
                    id_centro_custo, id_usuario, status
             FROM lancamentos
             WHERE id_lancamento = :id_lancamento'
        );
        $statement->execute(['id_lancamento' => $id_lancamento]);
        $lancamento = $statement->fetch(PDO::FETCH_ASSOC);

        return $lancamento === false ? null : $lancamento;
    }

    public function estornar(int $id_lancamento, int $id_empresa): bool
    {
        $statement = $this->conexao->prepare(
            "UPDATE lancamentos
             SET status = 'cancelado'
             WHERE id_lancamento = :id
               AND id_empresa = :empresa"
        );
        $statement->execute([
            'id' => $id_lancamento,
            'empresa' => $id_empresa,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $id, array $dados): bool
    {
        $idEmpresa = $dados['id_empresa'] ?? null;
        $camposAtualizaveis = [
            'data',
            'historico',
            'documento',
            'valor',
            'id_conta_debito',
            'id_conta_credito',
            'id_centro_custo',
        ];
        $dados = array_intersect_key($dados, array_flip($camposAtualizaveis));

        if ($dados === []) {
            throw new InvalidArgumentException('Informe ao menos um campo válido para atualizar o lançamento.');
        }

        $camposObrigatorios = ['data', 'historico', 'valor', 'id_conta_debito', 'id_conta_credito'];
        $camposAusentes = array_diff($camposObrigatorios, array_keys($dados));
        if ($camposAusentes !== [] || !is_numeric($idEmpresa) || (int) $idEmpresa < 1) {
            if (!is_numeric($idEmpresa) || (int) $idEmpresa < 1) {
                $camposAusentes[] = 'id_empresa';
            }

            throw new InvalidArgumentException(
                'Informe todos os campos obrigatórios para atualizar o lançamento: '
                . implode(', ', $camposAusentes)
                . '.'
            );
        }

        $atribuicoes = [];
        foreach ($camposAtualizaveis as $campo) {
            if (array_key_exists($campo, $dados)) {
                $atribuicoes[] = $campo . ' = :' . $campo;
            }
        }

        $dados['id'] = $id;
        $dados['id_empresa'] = (int) $idEmpresa;
        $statement = $this->conexao->prepare(
            'UPDATE lancamentos
             SET ' . implode(', ', $atribuicoes) . '
             WHERE id_lancamento = :id
               AND id_empresa = :id_empresa'
        );
        $statement->execute($dados);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function salvar(array $dados): int
    {
        $dados = $this->filtrarDados($dados);
        $camposObrigatorios = [
            'id_empresa',
            'id_exercicio',
            'data',
            'historico',
            'valor',
            'id_conta_debito',
            'id_conta_credito',
            'id_usuario',
            'status',
        ];
        $camposAusentes = array_diff($camposObrigatorios, array_keys($dados));

        if ($camposAusentes !== []) {
            throw new InvalidArgumentException(
                'Informe todos os campos obrigatórios para salvar o lançamento: '
                . implode(', ', $camposAusentes)
                . '.'
            );
        }

        $camposInsercao = self::CAMPOS_GRAVAVEIS;
        $parametros = [];
        foreach ($camposInsercao as $campo) {
            $parametros[$campo] = $dados[$campo] ?? null;
        }

        $statement = $this->conexao->prepare(
            'INSERT INTO lancamentos (
                id_empresa, id_exercicio, data, historico, documento, valor,
                id_conta_debito, id_conta_credito, id_centro_custo, id_usuario, status
            ) VALUES (
                :id_empresa, :id_exercicio, :data, :historico, :documento, :valor,
                :id_conta_debito, :id_conta_credito, :id_centro_custo, :id_usuario, :status
            )'
        );
        $statement->execute($parametros);

        return (int) $this->conexao->lastInsertId();
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
