<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use PDO;

final class RelatorioRepository
{
    private PDO $conexao;

    public function __construct()
    {
        $this->conexao = Database::getInstance()->getConnection();
    }

    /**
     * @return array{
     *     total_ativos: string,
     *     total_passivos: string,
     *     total_receitas: string,
     *     total_despesas: string
     * }
     */
    public function obterResumoDashboard(int $id_empresa, int $id_exercicio): array
    {
        $sql = '
            SELECT
                COALESCE(SUM(CASE
                    WHEN UPPER(TRIM(saldos.classificacao)) = \'ATIVO\'
                    THEN saldos.saldo
                    ELSE 0
                END), 0) AS total_ativos,
                COALESCE(SUM(CASE
                    WHEN UPPER(TRIM(saldos.classificacao)) = \'PASSIVO\'
                    THEN saldos.saldo
                    ELSE 0
                END), 0) AS total_passivos,
                COALESCE(SUM(CASE
                    WHEN UPPER(TRIM(saldos.classificacao)) = \'RECEITA\'
                    THEN saldos.saldo
                    ELSE 0
                END), 0) AS total_receitas,
                COALESCE(SUM(CASE
                    WHEN UPPER(TRIM(saldos.classificacao)) IN (\'CUSTO\', \'DESPESA\')
                    THEN saldos.saldo
                    ELSE 0
                END), 0) AS total_despesas
            FROM (
                SELECT conta.classificacao,
                       CASE
                           WHEN UPPER(TRIM(conta.natureza)) = \'DEVEDORA\'
                               THEN COALESCE(debitos.total_debitado, 0)
                                    - COALESCE(creditos.total_creditado, 0)
                           WHEN UPPER(TRIM(conta.natureza)) = \'CREDORA\'
                               THEN COALESCE(creditos.total_creditado, 0)
                                    - COALESCE(debitos.total_debitado, 0)
                           ELSE 0
                       END AS saldo
                FROM contas_contabeis AS conta
                LEFT JOIN (
                    SELECT lancamento.id_conta_debito AS id_conta,
                           SUM(lancamento.valor) AS total_debitado
                    FROM lancamentos AS lancamento
                    WHERE lancamento.id_empresa = :empresa_debito
                      AND lancamento.id_exercicio = :exercicio_debito
                      AND lancamento.status = :status_debito
                    GROUP BY lancamento.id_conta_debito
                ) AS debitos ON debitos.id_conta = conta.id_conta
                LEFT JOIN (
                    SELECT lancamento.id_conta_credito AS id_conta,
                           SUM(lancamento.valor) AS total_creditado
                    FROM lancamentos AS lancamento
                    WHERE lancamento.id_empresa = :empresa_credito
                      AND lancamento.id_exercicio = :exercicio_credito
                      AND lancamento.status = :status_credito
                    GROUP BY lancamento.id_conta_credito
                ) AS creditos ON creditos.id_conta = conta.id_conta
                WHERE conta.id_empresa = :empresa_conta
            ) AS saldos
        ';

        $statement = $this->conexao->prepare($sql);
        $statement->execute([
            'empresa_debito' => $id_empresa,
            'exercicio_debito' => $id_exercicio,
            'status_debito' => 'ativo',
            'empresa_credito' => $id_empresa,
            'exercicio_credito' => $id_exercicio,
            'status_credito' => 'ativo',
            'empresa_conta' => $id_empresa,
        ]);

        $resumo = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'total_ativos' => (string) ($resumo['total_ativos'] ?? '0.00'),
            'total_passivos' => (string) ($resumo['total_passivos'] ?? '0.00'),
            'total_receitas' => (string) ($resumo['total_receitas'] ?? '0.00'),
            'total_despesas' => (string) ($resumo['total_despesas'] ?? '0.00'),
        ];
    }

    /**
     * @return array{
     *     contas: list<array<string, mixed>>,
     *     total_debitos: string,
     *     total_creditos: string
     * }
     */
    public function gerarBalancete(int $id_empresa, int $id_exercicio): array
    {
        $contas = $this->obterSaldosContabeis($id_empresa, $id_exercicio);
        $totalDebitos = 0;
        $totalCreditos = 0;

        foreach ($contas as &$conta) {
            if (($conta['tipo'] ?? null) === 'analitica') {
                $totalDebitos += (int) $conta['_debitos_centavos'];
                $totalCreditos += (int) $conta['_creditos_centavos'];
            }

            unset(
                $conta['_debitos_centavos'],
                $conta['_creditos_centavos'],
                $conta['_saldo_centavos']
            );
        }
        unset($conta);

        return [
            'contas' => $contas,
            'total_debitos' => $this->formatarCentavos($totalDebitos),
            'total_creditos' => $this->formatarCentavos($totalCreditos),
        ];
    }

    /**
     * @return array{
     *     total_receitas: string,
     *     total_custos: string,
     *     lucro_bruto: string,
     *     total_despesas: string,
     *     resultado_liquido_periodo: string,
     *     resultado_periodo: string
     * }
     */
    public function gerarDRE(int $id_empresa, int $id_exercicio): array
    {
        $totalReceitas = 0;
        $totalCustos = 0;
        $totalDespesas = 0;

        foreach ($this->obterSaldosContabeis($id_empresa, $id_exercicio) as $conta) {
            if (($conta['tipo'] ?? null) !== 'analitica') {
                continue;
            }

            $codigo = (string) ($conta['codigo'] ?? '');
            $saldo = (int) $conta['_saldo_centavos'];

            if ($this->pertenceAoGrupo($codigo, '4')) {
                $totalReceitas += $saldo;
            } elseif ($this->pertenceAoGrupo($codigo, '5.1')) {
                $totalCustos += $saldo;
            } elseif ($this->pertenceAoGrupo($codigo, '5.2')) {
                $totalDespesas += $saldo;
            }
        }

        $lucroBruto = $totalReceitas - $totalCustos;
        $resultadoLiquido = $lucroBruto - $totalDespesas;
        return [
            'total_receitas' => $this->formatarCentavos($totalReceitas),
            'total_custos' => $this->formatarCentavos($totalCustos),
            'lucro_bruto' => $this->formatarCentavos($lucroBruto),
            'total_despesas' => $this->formatarCentavos($totalDespesas),
            'resultado_liquido_periodo' => $this->formatarCentavos($resultadoLiquido),
            'resultado_periodo' => $this->formatarCentavos($resultadoLiquido),
        ];
    }

    /**
     * @return array{
     *     ativo_circulante: string,
     *     ativo_nao_circulante: string,
     *     passivo_circulante: string,
     *     passivo_nao_circulante: string,
     *     patrimonio_liquido: string,
     *     resultado_periodo: string,
     *     total_ativo: string,
     *     total_passivo_patrimonio_liquido: string
     * }
     */
    public function gerarBalancoPatrimonial(int $id_empresa, int $id_exercicio): array
    {
        $ativoCirculante = 0;
        $ativoNaoCirculante = 0;
        $passivoCirculante = 0;
        $passivoNaoCirculante = 0;
        $patrimonioLiquido = 0;

        foreach ($this->obterSaldosContabeis($id_empresa, $id_exercicio) as $conta) {
            if (($conta['tipo'] ?? null) !== 'analitica') {
                continue;
            }

            $codigo = (string) ($conta['codigo'] ?? '');
            $classificacao = strtolower(trim((string) ($conta['classificacao'] ?? '')));
            $saldo = (int) $conta['_saldo_centavos'];

            if ($this->pertenceAoGrupo($codigo, '1.1')) {
                $ativoCirculante += $saldo;
            } elseif ($this->pertenceAoGrupo($codigo, '1.2')) {
                $ativoNaoCirculante += $saldo;
            } elseif ($this->pertenceAoGrupo($codigo, '2.1')) {
                $passivoCirculante += $saldo;
            } elseif ($this->pertenceAoGrupo($codigo, '2.2')) {
                $passivoNaoCirculante += $saldo;
            } elseif ($classificacao === 'patrimonio_liquido') {
                $patrimonioLiquido += $saldo;
            }
        }

        $resultadoPeriodo = (int) $this->centavosDaMoeda(
            $this->gerarDRE($id_empresa, $id_exercicio)['resultado_liquido_periodo']
        );
        $patrimonioLiquido += $resultadoPeriodo;
        $totalAtivo = $ativoCirculante + $ativoNaoCirculante;
        $totalPassivoPatrimonioLiquido = $passivoCirculante
            + $passivoNaoCirculante
            + $patrimonioLiquido;

        return [
            'ativo_circulante' => $this->formatarCentavos($ativoCirculante),
            'ativo_nao_circulante' => $this->formatarCentavos($ativoNaoCirculante),
            'passivo_circulante' => $this->formatarCentavos($passivoCirculante),
            'passivo_nao_circulante' => $this->formatarCentavos($passivoNaoCirculante),
            'patrimonio_liquido' => $this->formatarCentavos($patrimonioLiquido),
            'resultado_periodo' => $this->formatarCentavos($resultadoPeriodo),
            'total_ativo' => $this->formatarCentavos($totalAtivo),
            'total_passivo_patrimonio_liquido' => $this->formatarCentavos($totalPassivoPatrimonioLiquido),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function obterSaldosContabeis(int $id_empresa, int $id_exercicio): array
    {
        $statement = $this->conexao->prepare(
            'SELECT conta.id_conta, conta.id_empresa, conta.codigo, conta.descricao,
                    conta.tipo, conta.natureza, conta.classificacao, conta.conta_pai,
                    conta.nivel, conta.status,
                    COALESCE(debitos.total_debitado, 0) AS total_debitado,
                    COALESCE(creditos.total_creditado, 0) AS total_creditado
             FROM contas_contabeis AS conta
             LEFT JOIN (
                 SELECT id_conta_debito AS id_conta, SUM(valor) AS total_debitado
                 FROM lancamentos
                 WHERE id_empresa = :empresa_debito
                   AND id_exercicio = :exercicio_debito
                   AND status = :status_debito
                 GROUP BY id_conta_debito
             ) AS debitos ON debitos.id_conta = conta.id_conta
             LEFT JOIN (
                 SELECT id_conta_credito AS id_conta, SUM(valor) AS total_creditado
                 FROM lancamentos
                 WHERE id_empresa = :empresa_credito
                   AND id_exercicio = :exercicio_credito
                   AND status = :status_credito
                 GROUP BY id_conta_credito
             ) AS creditos ON creditos.id_conta = conta.id_conta
             WHERE conta.id_empresa = :empresa_conta
             ORDER BY conta.codigo ASC'
        );
        $statement->execute([
            'empresa_debito' => $id_empresa,
            'exercicio_debito' => $id_exercicio,
            'status_debito' => 'ativo',
            'empresa_credito' => $id_empresa,
            'exercicio_credito' => $id_exercicio,
            'status_credito' => 'ativo',
            'empresa_conta' => $id_empresa,
        ]);

        $contas = $statement->fetchAll(PDO::FETCH_ASSOC);
        $contasPorId = [];
        foreach ($contas as $indice => $conta) {
            $idConta = (int) $conta['id_conta'];
            $debitos = $this->centavosDaMoeda($conta['total_debitado'] ?? 0);
            $creditos = $this->centavosDaMoeda($conta['total_creditado'] ?? 0);
            $natureza = strtolower(trim((string) ($conta['natureza'] ?? '')));
            $saldo = match ($natureza) {
                'devedora' => $debitos - $creditos,
                'credora' => $creditos - $debitos,
                default => 0,
            };

            $contas[$indice]['_debitos_centavos'] = $debitos;
            $contas[$indice]['_creditos_centavos'] = $creditos;
            $contas[$indice]['_saldo_centavos'] = $saldo;
            $contasPorId[$idConta] = $indice;
        }

        foreach ($contas as $conta) {
            if (($conta['tipo'] ?? null) !== 'analitica') {
                continue;
            }

            $visitados = [];
            $idPai = (int) ($conta['conta_pai'] ?? 0);
            while ($idPai > 0 && isset($contasPorId[$idPai]) && !isset($visitados[$idPai])) {
                $visitados[$idPai] = true;
                $indicePai = $contasPorId[$idPai];

                if (($contas[$indicePai]['tipo'] ?? null) === 'sintetica') {
                    $contas[$indicePai]['_debitos_centavos'] += $conta['_debitos_centavos'];
                    $contas[$indicePai]['_creditos_centavos'] += $conta['_creditos_centavos'];
                    $contas[$indicePai]['_saldo_centavos'] += $conta['_saldo_centavos'];
                }

                $idPai = (int) ($contas[$indicePai]['conta_pai'] ?? 0);
            }
        }

        foreach ($contas as &$conta) {
            $conta['total_debitado'] = $this->formatarCentavos((int) $conta['_debitos_centavos']);
            $conta['total_creditado'] = $this->formatarCentavos((int) $conta['_creditos_centavos']);
            $conta['saldo'] = $this->formatarCentavos((int) $conta['_saldo_centavos']);
        }
        unset($conta);

        return $contas;
    }

    private function pertenceAoGrupo(string $codigo, string $grupo): bool
    {
        return $codigo === $grupo || str_starts_with($codigo, $grupo . '.');
    }

    private function centavosDaMoeda(mixed $valor): int
    {
        if (!is_numeric($valor)) {
            return 0;
        }

        return (int) round((float) $valor * 100);
    }

    private function formatarCentavos(int $centavos): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $valorAbsoluto = abs($centavos);

        return sprintf(
            '%s%d.%02d',
            $sinal,
            intdiv($valorAbsoluto, 100),
            $valorAbsoluto % 100
        );
    }
}
