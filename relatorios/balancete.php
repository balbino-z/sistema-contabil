<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();

$idEmpresa = $auth->empresaId();
$exercicios = $idEmpresa === null
    ? []
    : (new App\Repository\ExercicioRepository())->listarPorEmpresa($idEmpresa);
$exerciciosPorId = [];
foreach ($exercicios as $exercicio) {
    $exerciciosPorId[(int) ($exercicio['id_exercicio'] ?? 0)] = $exercicio;
}

$idExercicio = null;
$erroFiltro = null;
if ($exercicios !== []) {
    $idExercicio = (int) array_key_first($exerciciosPorId);
    $exercicioAberto = $idEmpresa === null
        ? null
        : (new App\Repository\ExercicioRepository())->buscarAbertoPorEmpresa($idEmpresa);
    if (
        $exercicioAberto !== null
        && isset($exerciciosPorId[(int) ($exercicioAberto['id_exercicio'] ?? 0)])
    ) {
        $idExercicio = (int) $exercicioAberto['id_exercicio'];
    }

    if (isset($_GET['exercicio'])) {
        $exercicioRecebido = $_GET['exercicio'];
        if (
            is_string($exercicioRecebido)
            && ctype_digit($exercicioRecebido)
            && isset($exerciciosPorId[(int) $exercicioRecebido])
        ) {
            $idExercicio = (int) $exercicioRecebido;
        } else {
            $erroFiltro = 'Selecione um exercício válido para esta empresa.';
        }
    }
}

$balancete = $idEmpresa !== null && $idExercicio !== null
    ? (new App\Repository\RelatorioRepository())->gerarBalancete($idEmpresa, $idExercicio)
    : ['contas' => [], 'total_debitos' => '0.00', 'total_creditos' => '0.00'];
$contas = $balancete['contas'];
$exercicioSelecionado = $idExercicio !== null
    ? ($exerciciosPorId[$idExercicio] ?? null)
    : null;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$formatarValor = static function (mixed $valor): string {
    return is_numeric($valor)
        ? 'R$ ' . number_format((float) $valor, 2, ',', '.')
        : 'R$ 0,00';
};
$centavos = static function (mixed $valor): int {
    return is_numeric($valor) ? (int) round((float) $valor * 100) : 0;
};
$equilibrado = $centavos($balancete['total_debitos'])
    === $centavos($balancete['total_creditos']);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Balancete</h1>
        <p class="text-body-secondary mb-0">
            Exercício <?= $escapar((string) ($exercicioSelecionado['ano'] ?? '—')) ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($idEmpresa !== null && $idExercicio !== null && $equilibrado): ?>
            <span class="badge bg-success">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                Balancete Equilibrado
            </span>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1" aria-hidden="true"></i>
            Imprimir
        </button>
    </div>
</div>

<ul class="nav nav-pills mb-4" aria-label="Relatórios contábeis">
    <li class="nav-item">
        <a class="nav-link active" href="/Contabil/relatorios/balancete.php" aria-current="page">
            Balancete
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/Contabil/relatorios/dre.php">DRE</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/Contabil/relatorios/balanco.php">Balanço Patrimonial</a>
    </li>
</ul>

<?php if ($idEmpresa === null): ?>
    <div class="alert alert-info" role="status">
        Nenhuma empresa ativa está selecionada. Selecione uma empresa para consultar o balancete.
    </div>
<?php else: ?>
    <?php if ($erroFiltro !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $escapar($erroFiltro) ?></div>
    <?php endif; ?>

    <?php if ($exercicios === []): ?>
        <div class="alert alert-info" role="status">
            Não há exercícios cadastrados para esta empresa.
        </div>
    <?php else: ?>
        <form action="/Contabil/relatorios/balancete.php" method="get" class="row g-2 align-items-end mb-4">
            <div class="col-auto">
                <label for="exercicio" class="form-label mb-1">Exercício</label>
                <select class="form-select" id="exercicio" name="exercicio">
                    <?php foreach ($exercicios as $exercicio): ?>
                        <?php $idOpcao = (int) ($exercicio['id_exercicio'] ?? 0); ?>
                        <option
                            value="<?= $idOpcao ?>"
                            <?= $idExercicio === $idOpcao ? 'selected' : '' ?>
                        >
                            <?= $escapar((string) ($exercicio['ano'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Consultar</button>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Conta</th>
                                <th scope="col" class="text-end">Soma Débitos</th>
                                <th scope="col" class="text-end">Soma Créditos</th>
                                <th scope="col" class="text-end">Saldo Atual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($contas === []): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-body-secondary py-4">
                                        Nenhuma conta contábil cadastrada para esta empresa.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contas as $conta): ?>
                                    <?php
                                    $nivel = max(1, (int) ($conta['nivel'] ?? 1));
                                    $espacamento = ($nivel - 1) * 1.25;
                                    $sintetica = ($conta['tipo'] ?? null) === 'sintetica';
                                    ?>
                                    <tr class="<?= $sintetica ? 'fw-bold table-light' : '' ?>">
                                        <td><?= $escapar((string) ($conta['codigo'] ?? '')) ?></td>
                                        <td style="padding-left: <?= $espacamento ?>rem;">
                                            <?= $escapar((string) ($conta['descricao'] ?? '')) ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?= $escapar($formatarValor($conta['total_debitado'] ?? null)) ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?= $escapar($formatarValor($conta['total_creditado'] ?? null)) ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?= $escapar($formatarValor($conta['saldo'] ?? null)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="2">Totais gerais</td>
                                <td class="text-end text-nowrap">
                                    <?= $escapar($formatarValor($balancete['total_debitos'])) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= $escapar($formatarValor($balancete['total_creditos'])) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
