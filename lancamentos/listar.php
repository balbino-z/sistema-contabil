<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();
$podeEscrever = $auth->podeEscrever();

$idEmpresa = $auth->empresaId();
// Usa o exercício aberto da empresa ativa (antes era fixo em 2).
$exercicioAtual = $idEmpresa === null
    ? null
    : (new App\Repository\ExercicioRepository())->buscarAbertoPorEmpresa($idEmpresa);
$idExercicio = (int) ($exercicioAtual['id_exercicio'] ?? 0);
$lancamentos = $idEmpresa === null
    ? []
    : (new App\Repository\LancamentoRepository())->listarPorEmpresaEExercicio(
        $idEmpresa,
        $idExercicio
    );

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$formatarData = static function (mixed $valor): string {
    if (!is_string($valor) || $valor === '') {
        return '';
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', substr($valor, 0, 10));

    return $data !== false && $data->format('Y-m-d') === substr($valor, 0, 10)
        ? $data->format('d/m/Y')
        : $valor;
};
$formatarValor = static function (mixed $valor): string {
    return is_numeric($valor)
        ? 'R$ ' . number_format((float) $valor, 2, ',', '.')
        : 'R$ 0,00';
};
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <h1 class="h2 mb-0">Lançamentos Contábeis</h1>
    <?php if ($podeEscrever): ?>
        <a href="/Contabil/lancamentos/form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
            Novo Lançamento
        </a>
    <?php endif; ?>
</div>

<?php if ($idEmpresa === null): ?>
    <div class="alert alert-info" role="status">
        Nenhuma empresa ativa está selecionada. Selecione uma empresa para consultar os lançamentos.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Histórico</th>
                            <th scope="col">Documento</th>
                            <th scope="col">Conta Débito</th>
                            <th scope="col">Conta Crédito</th>
                            <th scope="col" class="text-end">Valor</th>
                            <th scope="col">Status</th>
                            <?php if ($podeEscrever): ?>
                                <th scope="col" class="text-end">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lancamentos === []): ?>
                            <tr>
                                <td colspan="<?= $podeEscrever ? 8 : 7 ?>" class="text-center text-body-secondary py-4">
                                    Nenhum lançamento encontrado para o exercício selecionado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lancamentos as $lancamento): ?>
                                <?php
                                $idLancamento = (int) ($lancamento['id_lancamento'] ?? 0);
                                $status = is_string($lancamento['status'] ?? null)
                                    ? $lancamento['status']
                                    : '';
                                $linhaCancelada = $status === 'cancelado';
                                ?>
                                <tr class="<?= $linhaCancelada ? 'text-muted' : '' ?>">
                                    <td><?= $escapar($formatarData($lancamento['data'] ?? null)) ?></td>
                                    <td><?= $escapar((string) ($lancamento['historico'] ?? '')) ?></td>
                                    <td><?= $escapar((string) ($lancamento['documento'] ?? '')) ?></td>
                                    <td><?= $escapar((string) ($lancamento['descricao_conta_debito'] ?? '')) ?></td>
                                    <td><?= $escapar((string) ($lancamento['descricao_conta_credito'] ?? '')) ?></td>
                                    <td class="text-end text-nowrap"><?= $escapar($formatarValor($lancamento['valor'] ?? null)) ?></td>
                                    <td><?= $escapar($status) ?></td>
                                    <?php if ($podeEscrever): ?>
                                        <td class="text-end">
                                            <?php if ($status === 'ativo'): ?>
                                                <a
                                                    href="/Contabil/lancamentos/form.php?id=<?= $idLancamento ?>"
                                                    class="btn btn-outline-primary btn-sm"
                                                    aria-label="Editar lançamento <?= $idLancamento ?>"
                                                    title="Editar"
                                                >
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </a>
                                                <form
                                                    action="/Contabil/lancamentos/estornar.php?id=<?= $idLancamento ?>"
                                                    method="post"
                                                    class="d-inline"
                                                >
                                                    <?= App\Core\Csrf::campo() ?>
                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-warning btn-sm btn-excluir"
                                                    >
                                                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                                                        Estornar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-body-secondary">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
