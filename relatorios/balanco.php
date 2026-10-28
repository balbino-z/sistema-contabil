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

$balanco = $idEmpresa !== null && $idExercicio !== null
    ? (new App\Repository\RelatorioRepository())->gerarBalancoPatrimonial($idEmpresa, $idExercicio)
    : [
        'ativo_circulante' => '0.00',
        'ativo_nao_circulante' => '0.00',
        'passivo_circulante' => '0.00',
        'passivo_nao_circulante' => '0.00',
        'patrimonio_liquido' => '0.00',
        'resultado_periodo' => '0.00',
        'total_ativo' => '0.00',
        'total_passivo_patrimonio_liquido' => '0.00',
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
$centavos = static function (mixed $valor): int {
    return is_numeric($valor) ? (int) round((float) $valor * 100) : 0;
};
$ativoIgualPassivoPl = $centavos($balanco['total_ativo'])
    === $centavos($balanco['total_passivo_patrimonio_liquido']);
$resultadoPeriodo = is_numeric($balanco['resultado_periodo'])
    ? (float) $balanco['resultado_periodo']
    : 0.0;
$urlExercicio = $idExercicio !== null ? '?exercicio=' . $idExercicio : '';
?>

<div class="mb-4">
    <h1 class="h2 mb-1">Balanço Patrimonial</h1>
    <p class="text-body-secondary mb-0">
        Exercício <?= $escapar((string) ($exercicioSelecionado['ano'] ?? '—')) ?>
    </p>
</div>

<ul class="nav nav-pills mb-4" aria-label="Relatórios contábeis">
    <li class="nav-item">
        <a class="nav-link" href="/Contabil/relatorios/balancete.php<?= $urlExercicio ?>">
            Balancete
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/Contabil/relatorios/dre.php<?= $urlExercicio ?>">DRE</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="/Contabil/relatorios/balanco.php<?= $urlExercicio ?>" aria-current="page">
            Balanço Patrimonial
        </a>
    </li>
</ul>

<?php if ($idEmpresa === null): ?>
    <div class="alert alert-info" role="status">
        Nenhuma empresa ativa está selecionada. Selecione uma empresa para consultar o balanço patrimonial.
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
        <form action="/Contabil/relatorios/balanco.php" method="get" class="row g-2 align-items-end mb-4">
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

        <div class="row g-4">
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h2 class="h5 mb-0">ATIVO</h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span>Ativo Circulante</span>
                            <span class="text-nowrap"><?= $escapar($formatarValor($balanco['ativo_circulante'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span>Ativo Não Circulante</span>
                            <span class="text-nowrap"><?= $escapar($formatarValor($balanco['ativo_nao_circulante'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between gap-3 pt-3 fw-bold">
                            <span>Total do Ativo</span>
                            <span class="text-nowrap"><?= $escapar($formatarValor($balanco['total_ativo'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-dark text-white">
                        <h2 class="h5 mb-0">PASSIVO + PATRIMÔNIO LÍQUIDO</h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span>Passivo Circulante</span>
                            <span class="text-nowrap"><?= $escapar($formatarValor($balanco['passivo_circulante'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span>Passivo Não Circulante</span>
                            <span class="text-nowrap"><?= $escapar($formatarValor($balanco['passivo_nao_circulante'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span>
                                Patrimônio Líquido
                                <small class="d-block text-body-secondary">
                                    Capital Social + Resultado do Exercício da DRE
                                    (<?= $resultadoPeriodo < 0 ? '- ' : '' ?><?= $escapar($formatarValor($balanco['resultado_periodo'])) ?>)
                                </small>
                            </span>
                            <span class="text-nowrap">
                                <?= $resultadoPeriodo < 0 ? '- ' : '' ?><?= $escapar($formatarValor($balanco['patrimonio_liquido'])) ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between gap-3 pt-3 fw-bold">
                            <span>Total do Passivo + PL</span>
                            <span class="text-nowrap">
                                <?= $escapar($formatarValor($balanco['total_passivo_patrimonio_liquido'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($ativoIgualPassivoPl): ?>
            <div class="alert alert-success mt-4 mb-0" role="status">
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                Igualdade patrimonial confirmada: Total do Ativo é igual ao Total do Passivo + Patrimônio Líquido.
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
