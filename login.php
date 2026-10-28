<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !session_start()) {
    throw new RuntimeException('Não foi possível iniciar a sessão.');
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'config.php';

$auth = new App\Core\Auth();

if ($auth->logado()) {
    header('Location: dashboard.php');
    exit;
}

$erroLogin = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    App\Core\Csrf::exigir();

    $emailEnviado = $_POST['email'] ?? '';
    $senhaEnviada = $_POST['senha'] ?? '';
    $email = is_string($emailEnviado) ? trim($emailEnviado) : '';
    $senha = is_string($senhaEnviada) ? $senhaEnviada : '';

    $erroLogin = $auth->entrar($email, $senha);

    if ($erroLogin === null) {
        header('Location: dashboard.php');
        exit;
    }
}

$mensagensFlash = App\Core\Flash::consumir();
$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | <?= $escapar(APP_NAME) ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
</head>
<body class="bg-light">
<main class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h3 text-center mb-2"><?= $escapar(APP_NAME) ?></h1>
                        <p class="text-body-secondary text-center mb-4">Acesse sua conta</p>

                        <?php if ($erroLogin !== null): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= $escapar($erroLogin) ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach (['sucesso' => 'success', 'erro' => 'danger', 'aviso' => 'warning'] as $tipo => $classeAlerta): ?>
                            <?php foreach ($mensagensFlash[$tipo] ?? [] as $mensagem): ?>
                                <?php if (is_string($mensagem)): ?>
                                    <div class="alert alert-<?= $classeAlerta ?>" role="alert">
                                        <?= $escapar($mensagem) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <form method="post" action="login.php">
                            <?= App\Core\Csrf::campo() ?>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    value="<?= $escapar($email) ?>"
                                    autocomplete="username"
                                    required
                                    autofocus
                                >
                            </div>

                            <div class="mb-4">
                                <label for="senha" class="form-label">Senha</label>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="senha"
                                    name="senha"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Entrar
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
</body>
</html>
