<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();
$podeEscrever = $auth->podeEscrever();

$empresas = (new App\Repository\EmpresaRepository())->listar();

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$regimesTributarios = [
    'mei' => 'MEI',
    'simples_nacional' => 'Simples Nacional',
    'lucro_presumido' => 'Lucro Presumido',
    'lucro_real' => 'Lucro Real',
];
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <h1 class="h2 mb-0">Empresas</h1>
    <?php if ($podeEscrever): ?>
        <a href="/Contabil/empresas/form.php" class="btn btn-primary">
            <i class="bi bi-building-add me-1" aria-hidden="true"></i>
            Nova Empresa
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Razão Social</th>
                        <th scope="col">CNPJ</th>
                        <th scope="col">Regime Tributário</th>
                        <th scope="col">Responsável</th>
                        <th scope="col">Status</th>
                        <?php if ($podeEscrever): ?>
                            <th scope="col" class="text-end">Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($empresas === []): ?>
                        <tr>
                            <td colspan="<?= $podeEscrever ? 6 : 5 ?>" class="text-center text-body-secondary py-4">
                                Nenhuma empresa cadastrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($empresas as $empresa): ?>
                            <?php
                            $idEmpresa = (int) ($empresa['id_empresa'] ?? 0);
                            $ativa = ($empresa['status'] ?? null) === 'ativo';
                            $regime = (string) ($empresa['regime_tributario'] ?? '');
                            ?>
                            <tr>
                                <td><?= $escapar((string) ($empresa['razao_social'] ?? '')) ?></td>
                                <td><?= $escapar((string) ($empresa['cnpj'] ?? '')) ?></td>
                                <td><?= $escapar($regimesTributarios[$regime] ?? $regime) ?></td>
                                <td><?= $escapar((string) ($empresa['responsavel'] ?? '')) ?></td>
                                <td>
                                    <span class="badge <?= $ativa ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $ativa ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                </td>
                                <?php if ($podeEscrever): ?>
                                    <td class="text-end">
                                        <a
                                            href="/Contabil/empresas/form.php?id=<?= $idEmpresa ?>"
                                            class="btn btn-outline-primary btn-sm"
                                            aria-label="Editar empresa <?= $escapar((string) ($empresa['razao_social'] ?? '')) ?>"
                                            title="Editar"
                                        >
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                            <span class="visually-hidden">Editar</span>
                                        </a>
                                        <?php if ($ativa): ?>
                                            <form
                                                action="/Contabil/empresas/inativar.php?id=<?= $idEmpresa ?>"
                                                method="post"
                                                class="d-inline"
                                            >
                                                <?= App\Core\Csrf::campo() ?>
                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-danger btn-sm btn-excluir"
                                                    aria-label="Inativar empresa <?= $escapar((string) ($empresa['razao_social'] ?? '')) ?>"
                                                    title="Inativar"
                                                >
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
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

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
