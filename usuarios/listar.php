<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();
$auth->exigirPerfil(['admin']);

$usuarios = (new App\Repository\UsuarioRepository())->listar();

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <h1 class="h2 mb-0">Usuários</h1>
    <a href="/Contabil/usuarios/form.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
        Novo Usuário
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Nome</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Perfil</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($usuarios === []): ?>
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">
                                Nenhum usuário cadastrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <?php
                            $idUsuario = (int) ($usuario['id_usuario'] ?? 0);
                            $ativo = ($usuario['status'] ?? null) === 'ativo';
                            ?>
                            <tr>
                                <td><?= $idUsuario ?></td>
                                <td><?= $escapar((string) ($usuario['nome'] ?? '')) ?></td>
                                <td><?= $escapar((string) ($usuario['email'] ?? '')) ?></td>
                                <td><?= $escapar((string) ($usuario['perfil'] ?? '')) ?></td>
                                <td>
                                    <span class="badge <?= $ativo ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $ativo ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a
                                        href="/Contabil/usuarios/form.php?id=<?= $idUsuario ?>"
                                        class="btn btn-outline-primary btn-sm"
                                        aria-label="Editar usuário <?= $escapar((string) ($usuario['nome'] ?? '')) ?>"
                                        title="Editar"
                                    >
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <?php if ($ativo): ?>
                                        <form
                                            action="/Contabil/usuarios/inativar.php?id=<?= $idUsuario ?>"
                                            method="post"
                                            class="d-inline"
                                        >
                                            <?= App\Core\Csrf::campo() ?>
                                            <button
                                                type="submit"
                                                class="btn btn-outline-secondary btn-sm btn-excluir"
                                                aria-label="Inativar usuário <?= $escapar((string) ($usuario['nome'] ?? '')) ?>"
                                                title="Inativar"
                                            >
                                                <i class="bi bi-person-dash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
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
