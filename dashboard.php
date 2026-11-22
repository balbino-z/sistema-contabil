<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();

$idEmpresa = $auth->empresaId();
// Usa o exercício aberto da empresa ativa (antes era fixo em 2).
$exercicioAtual = $idEmpresa === null
    ? null
    : (new App\Repository\ExercicioRepository())->buscarAbertoPorEmpresa($idEmpresa);
$idExercicio = (int) ($exercicioAtual['id_exercicio'] ?? 0);
$relatorioRepository = new App\Repository\RelatorioRepository();
$lancamentoRepository = new App\Repository\LancamentoRepository();
$resumo = $idEmpresa === null
    ? [
        'total_ativos' => '0.00',
        'total_passivos' => '0.00',
        'total_receitas' => '0.00',
        'total_despesas' => '0.00',
    ]
    : $relatorioRepository->obterResumoDashboard($idEmpresa, $idExercicio);
$lancamentosRecentes = $idEmpresa === null
    ? []
    : $lancamentoRepository->listarRecentesPorEmpresaEExercicio($idEmpresa, $idExercicio);

$formatarValor = static function (mixed $valor): string {
    return is_numeric($valor)
        ? 'R$ ' . number_format((float) $valor, 2, ',', '.')
        : 'R$ 0,00';
};
$formatarData = static function (mixed $valor): string {
    if (!is_string($valor) || $valor === '') {
        return '';
    }

    $dataTexto = substr($valor, 0, 10);
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dataTexto);

    return $data !== false && $data->format('Y-m-d') === $dataTexto
        ? $data->format('d/m/Y')
        : $valor;
};

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$resumos = [
    ['titulo' => 'Total de Ativos', 'valor' => $formatarValor($resumo['total_ativos']), 'icone' => 'bi-graph-up-arrow', 'cor' => 'primary'],
    ['titulo' => 'Total de Passivos', 'valor' => $formatarValor($resumo['total_passivos']), 'icone' => 'bi-graph-down-arrow', 'cor' => 'danger'],
    ['titulo' => 'Receitas', 'valor' => $formatarValor($resumo['total_receitas']), 'icone' => 'bi-cash-coin', 'cor' => 'success'],
    ['titulo' => 'Despesas', 'valor' => $formatarValor($resumo['total_despesas']), 'icone' => 'bi-receipt', 'cor' => 'warning'],
];
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Dashboard Contábil</h1>
        <p class="text-body-secondary mb-0">Visão geral da situação financeira</p>
    </div>
</div>

<section class="row g-3 mb-4" aria-label="Resumo financeiro">
    <?php foreach ($resumos as $resumo): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="small text-body-secondary fw-semibold mb-2">
                            <?= htmlspecialchars($resumo['titulo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </h2>
                        <p class="h4 mb-0">
                            <?= htmlspecialchars($resumo['valor'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </p>
                    </div>
                    <span class="text-<?= htmlspecialchars($resumo['cor'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> fs-4" aria-hidden="true">
                        <i class="bi <?= htmlspecialchars($resumo['icone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
                    </span>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</section>

<section class="card border-0 shadow-sm" aria-labelledby="lancamentosRecentes">
    <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
        <h2 class="h5 mb-0" id="lancamentosRecentes">Lançamentos Recentes</h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">Categoria</th>
                        <th scope="col" class="text-end">Valor</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lancamentosRecentes === []): ?>
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">
                                Nenhum lançamento recente para exibir.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lancamentosRecentes as $lancamento): ?>
                            <tr>
                                <td><?= htmlspecialchars($formatarData($lancamento['data'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($lancamento['historico'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars((string) ($lancamento['descricao_conta_debito'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    <span class="text-body-secondary">/</span>
                                    <?= htmlspecialchars((string) ($lancamento['descricao_conta_credito'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= htmlspecialchars($formatarValor($lancamento['valor'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($lancamento['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
