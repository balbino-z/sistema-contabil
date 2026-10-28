<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();
$podeEscrever = $auth->podeEscrever();

$idEmpresa = $auth->empresaId();
$contas = $idEmpresa === null
    ? []
    : (new App\Repository\ContaContabilRepository())->listarPorEmpresa($idEmpresa);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <h1 class="h2 mb-0">Plano de Contas</h1>
    <?php if ($podeEscrever): ?>
        <a href="/Contabil/plano_contas/form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
            Nova Conta
        </a>
    <?php endif; ?>
</div>

<?php if ($idEmpresa === null): ?>
    <div class="alert alert-info" role="status">
        Nenhuma empresa ativa está selecionada. Selecione uma empresa para consultar o plano de contas.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Código</th>
                            <th scope="col">Descrição</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Natureza</th>
                            <?php if ($podeEscrever): ?>
                                <th scope="col" class="text-end">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($contas === []): ?>
                            <tr>
                                <td colspan="<?= $podeEscrever ? 5 : 4 ?>" class="text-center text-body-secondary py-4">
                                    Nenhuma conta cadastrada para esta empresa.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contas as $conta): ?>
                                <?php
                                $idConta = (int) ($conta['id_conta'] ?? 0);
                                $nivel = max(1, (int) ($conta['nivel'] ?? 1));
                                $espacamento = $nivel > 1 ? ($nivel - 1) * 1.25 : 0;
                                $tipo = (string) ($conta['tipo'] ?? '');
                                $natureza = (string) ($conta['natureza'] ?? '');
                                ?>
                                <tr>
                                    <td><?= $escapar((string) ($conta['codigo'] ?? '')) ?></td>
                                    <td style="padding-left: <?= $espacamento ?>rem;">
                                        <?= $escapar((string) ($conta['descricao'] ?? '')) ?>
                                    </td>
                                    <td>
                                        <?php if ($tipo === 'sintetica'): ?>
                                            <span class="badge bg-secondary">Sintética</span>
                                        <?php elseif ($tipo === 'analitica'): ?>
                                            <span class="badge bg-info text-dark">Analítica</span>
                                        <?php else: ?>
                                            <?= $escapar($tipo) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($natureza === 'devedora'): ?>
                                            <span class="badge bg-primary">Devedora</span>
                                        <?php elseif ($natureza === 'credora'): ?>
                                            <span class="badge bg-success">Credora</span>
                                        <?php else: ?>
                                            <?= $escapar($natureza) ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($podeEscrever): ?>
                                        <td class="text-end text-nowrap">
                                            <a
                                                href="/Contabil/plano_contas/form.php?id=<?= $idConta ?>"
                                                class="btn btn-outline-primary btn-sm"
                                                aria-label="Editar conta <?= $escapar((string) ($conta['codigo'] ?? '')) ?>"
                                                title="Editar"
                                            >
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form
                                                action="/Contabil/plano_contas/excluir.php?id=<?= $idConta ?>"
                                                method="post"
                                                class="d-inline"
                                                data-confirm
                                            >
                                                <?= App\Core\Csrf::campo() ?>
                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-danger btn-sm btn-excluir"
                                                    aria-label="Excluir conta <?= $escapar((string) ($conta['codigo'] ?? '')) ?>"
                                                    title="Excluir"
                                                >
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
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
