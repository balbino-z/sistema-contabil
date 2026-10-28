<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !session_start()) {
    throw new RuntimeException('Não foi possível iniciar a sessão.');
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'config.php';

$auth = new \App\Core\Auth();
$usuarioLogado = $auth->logado();
$usuarioAtual = $usuarioLogado ? $auth->usuario() : null;
$mensagensFlash = \App\Core\Flash::consumir();
$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$tiposAlerta = [
    'sucesso' => 'success',
    'erro' => 'danger',
    'aviso' => 'warning',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escapar(APP_NAME) ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >
</head>
<body>
<?php if ($usuarioLogado && $usuarioAtual !== null): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/Contabil/dashboard.php"><?= $escapar(APP_NAME) ?></a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal"
                aria-expanded="false"
                aria-label="Alternar navegação"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/Contabil/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/Contabil/empresas/listar.php">Empresas</a></li>
                    <?php if ($auth->perfil() === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/Contabil/usuarios/listar.php">Usuários</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="/Contabil/plano_contas/listar.php">Plano de Contas</a></li>
                    <li class="nav-item"><a class="nav-link" href="/Contabil/lancamentos/listar.php">Lançamentos</a></li>
                    <li class="nav-item"><a class="nav-link" href="/Contabil/relatorios/balancete.php">Relatórios</a></li>
                </ul>

                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                    <span class="navbar-text">
                        <?= $escapar($usuarioAtual['nome']) ?>
                    </span>
                    <form action="/Contabil/logout.php" method="post" class="m-0">
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= $escapar(\App\Core\Csrf::token()) ?>"
                        >
                        <button type="submit" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

<div class="container mt-4">
    <?php foreach ($tiposAlerta as $tipo => $classeAlerta): ?>
        <?php foreach ($mensagensFlash[$tipo] ?? [] as $mensagem): ?>
            <?php if (is_string($mensagem)): ?>
                <div
                    class="alert alert-<?= $classeAlerta ?> alert-dismissible fade show"
                    role="alert"
                >
                    <?= $escapar($mensagem) ?>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Fechar"
                    ></button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
