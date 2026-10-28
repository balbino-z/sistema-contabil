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

$dre = $idEmpresa !== null && $idExercicio !== null
    ? (new App\Repository\RelatorioRepository())->gerarDRE($idEmpresa, $idExercicio)
    : [
        'total_receitas' => '0.00',
        'total_custos' => '0.00',
        'lucro_bruto' => '0.00',
        'total_despesas' => '0.00',
        'resultado_liquido_periodo' => '0.00',
    ];
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
        ? 'R$ ' . number_format(abs((float) $valor), 2, ',', '.')
        : 'R$ 0,00';
};
$resultado = is_numeric($dre['resultado_liquido_periodo'])
    ? (float) $dre['resultado_liquido_periodo']
    : 0.0;
$lucroBruto = is_numeric($dre['lucro_bruto']) ? (float) $dre['lucro_bruto'] : 0.0;
$resultadoPositivo = $resultado >= 0;
$urlExercicio = $idExercicio !== null ? '?exercicio=' . $idExercicio : '';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Demonstração do Resultado do Exercício</h1>
        <p class="text-body-secondary mb-0">
            Exercício <?= $escapar((string) ($exercicioSelecionado['ano'] ?? '—')) ?>
        </p>
    </div>
</div>

<ul class="nav nav-pills mb-4" aria-label="Relatórios contábeis">
    <li class="nav-item">
        <a class="nav-link" href="/Contabil/relatorios/balancete.php<?= $urlExercicio ?>">
            Balancete
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="/Contabil/relatorios/dre.php<?= $urlExercicio ?>" aria-current="page">
            DRE
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/Contabil/relatorios/balanco.php<?= $urlExercicio ?>">
            Balanço Patrimonial
        </a>
    </li>
</ul>

<?php if ($idEmpresa === null): ?>
    <div class="alert alert-info" role="status">
        Nenhuma empresa ativa está selecionada. Selecione uma empresa para consultar a DRE.
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
        <form action="/Contabil/relatorios/dre.php" method="get" class="row g-2 align-items-end mb-4">
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

        <div class="card border-0 shadow-sm mx-auto" style="max-width: 800px;">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Demonstração do Resultado do Exercício</h2>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span>Receita Bruta de Vendas e Serviços</span>
                    <span class="text-nowrap fw-semibold"><?= $escapar($formatarValor($dre['total_receitas'])) ?></span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span>(-) Custos das Mercadorias / Serviços</span>
                    <span class="text-nowrap">- <?= $escapar($formatarValor($dre['total_custos'])) ?></span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-3 border-bottom fw-bold">
                    <span>(=) Resultado Bruto</span>
                    <span class="text-nowrap"><?= $lucroBruto < 0 ? '- ' : '' ?><?= $escapar($formatarValor($dre['lucro_bruto'])) ?></span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span>(-) Despesas Operacionais (Administrativas, Pessoal, Comerciais)</span>
                    <span class="text-nowrap">- <?= $escapar($formatarValor($dre['total_despesas'])) ?></span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-3 fw-bold fs-5 <?= $resultadoPositivo ? 'text-success' : 'text-danger' ?>">
                    <span>(=) <?= $resultadoPositivo ? 'Lucro Líquido do Exercício' : 'Prejuízo do Exercício' ?></span>
                    <span class="text-nowrap">
                        <?= $resultadoPositivo ? '' : '- ' ?><?= $escapar($formatarValor($dre['resultado_liquido_periodo'])) ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
