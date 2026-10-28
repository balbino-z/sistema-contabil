<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use PDO;

final class ExercicioRepository
{
    private PDO $conexao;

    public function __construct()
    {
        $this->conexao = Database::getInstance()->getConnection();
    }

    /** @return array<string, mixed>|null */
    public function buscarAbertoPorEmpresa(int $id_empresa): ?array
    {
        $statement = $this->conexao->prepare(
            "SELECT id_exercicio, id_empresa, ano, data_inicio, data_fim, status
             FROM exercicios
             WHERE id_empresa = :empresa
               AND status = 'aberto'
             ORDER BY ano DESC
             LIMIT 1"
        );
        $statement->execute(['empresa' => $id_empresa]);
        $exercicio = $statement->fetch(PDO::FETCH_ASSOC);

        return $exercicio === false ? null : $exercicio;
    }

    /** @return list<array<string, mixed>> */
    public function listarPorEmpresa(int $id_empresa): array
    {
        $statement = $this->conexao->prepare(
            'SELECT id_exercicio, id_empresa, ano, data_inicio, data_fim, status
             FROM exercicios
             WHERE id_empresa = :empresa
             ORDER BY ano DESC'
        );
        $statement->execute(['empresa' => $id_empresa]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
