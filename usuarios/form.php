<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirPerfil(['admin']);

$idUsuario = null;
if (isset($_GET['id'])) {
    $idRecebido = $_GET['id'];
    if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
        http_response_code(400);
        exit('Identificador de usuário inválido.');
    }

    $idUsuario = (int) $idRecebido;
}

$usuarioRepository = null;
$usuario = null;
if ($idUsuario !== null) {
    $usuarioRepository = new App\Repository\UsuarioRepository();
    $usuario = $usuarioRepository->buscar($idUsuario);
}

if ($idUsuario !== null && $usuario === null) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}

$valores = [
    'nome' => (string) ($usuario['nome'] ?? ''),
    'email' => (string) ($usuario['email'] ?? ''),
    'perfil' => (string) ($usuario['perfil'] ?? ''),
    'status' => (string) ($usuario['status'] ?? 'ativo'),
];
$errosValidacao = [];
$perfisPermitidos = ['admin', 'contador', 'consulta'];
$statusPermitidos = ['ativo', 'inativo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    App\Core\Csrf::exigir();

    foreach (array_keys($valores) as $campo) {
        $valorEnviado = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valorEnviado) ? trim($valorEnviado) : '';
    }

    $senhaEnviada = $_POST['senha'] ?? '';
    $senha = is_string($senhaEnviada) ? $senhaEnviada : '';

    $validador = new App\Core\Validador($valores);
    $validador
        ->obrigatorio('nome', 'Nome')
        ->obrigatorio('email', 'E-mail')
        ->email('email', 'E-mail');
    $errosValidacao = $validador->erros();

    if (!in_array($valores['perfil'], $perfisPermitidos, true)) {
        $errosValidacao['perfil'][] = 'Selecione um perfil válido.';
    }

    if (!in_array($valores['status'], $statusPermitidos, true)) {
        $errosValidacao['status'][] = 'Selecione um status válido.';
    }

    if ($idUsuario === null && trim($senha) === '') {
        $errosValidacao['senha'][] = 'A senha é obrigatória para cadastrar um usuário.';
    }

    if ($errosValidacao === []) {
        $usuarioRepository ??= new App\Repository\UsuarioRepository();
        $usuarioComEmail = $usuarioRepository->buscarPorEmail($valores['email']);

        if (
            $usuarioComEmail !== null
            && (int) ($usuarioComEmail['id_usuario'] ?? 0) !== $idUsuario
        ) {
            $errosValidacao['email'][] = 'Este e-mail já está cadastrado.';
        }
    }

    foreach ($errosValidacao as $errosCampo) {
        foreach ($errosCampo as $erro) {
            App\Core\Flash::erro($erro);
        }
    }

    if ($errosValidacao === []) {
        $usuarioRepository ??= new App\Repository\UsuarioRepository();
        $dados = $valores;

        if (trim($senha) !== '') {
            $dados['senha'] = password_hash($senha, PASSWORD_DEFAULT);
        }

        if ($idUsuario === null) {
            $usuarioRepository->salvar($dados);
            App\Core\Flash::sucesso('Usuário cadastrado com sucesso.');
        } else {
            $usuarioRepository->atualizar($idUsuario, $dados);
            App\Core\Flash::sucesso('Usuário atualizado com sucesso.');
        }

        header('Location: /Contabil/usuarios/listar.php');
        exit;
    }
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'header.php';

$escapar = static fn (string $valor): string => htmlspecialchars(
    $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>

<div class="mb-4">
    <h1 class="h2 mb-1"><?= $idUsuario === null ? 'Novo Usuário' : 'Editar Usuário' ?></h1>
    <p class="text-body-secondary mb-0">
        <?= $idUsuario === null ? 'Preencha os dados para cadastrar um usuário.' : 'Atualize os dados do usuário.' ?>
    </p>
</div>

<form
    action="/Contabil/usuarios/form.php<?= $idUsuario === null ? '' : '?id=' . $idUsuario ?>"
    method="post"
    class="card border-0 shadow-sm"
>
    <div class="card-body p-4">
        <?= App\Core\Csrf::campo() ?>
        <?php if ($idUsuario !== null): ?>
            <input type="hidden" name="id" value="<?= $idUsuario ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control<?= isset($errosValidacao['nome']) ? ' is-invalid' : '' ?>"
                    id="nome"
                    name="nome"
                    value="<?= $escapar($valores['nome']) ?>"
                    maxlength="255"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                <input
                    type="email"
                    class="form-control<?= isset($errosValidacao['email']) ? ' is-invalid' : '' ?>"
                    id="email"
                    name="email"
                    value="<?= $escapar($valores['email']) ?>"
                    maxlength="255"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="senha" class="form-label">Senha <span class="text-danger"><?= $idUsuario === null ? '*' : '' ?></span></label>
                <?php if ($idUsuario !== null): ?>
                    <div class="form-text mb-2">Deixe em branco para não alterar.</div>
                <?php endif; ?>
                <input
                    type="password"
                    class="form-control<?= isset($errosValidacao['senha']) ? ' is-invalid' : '' ?>"
                    id="senha"
                    name="senha"
                    autocomplete="new-password"
                    <?= $idUsuario === null ? 'required' : '' ?>
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="perfil" class="form-label">Perfil <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['perfil']) ? ' is-invalid' : '' ?>"
                    id="perfil"
                    name="perfil"
                    required
                >
                    <option value="">Selecione...</option>
                    <?php foreach ($perfisPermitidos as $perfil): ?>
                        <option
                            value="<?= $escapar($perfil) ?>"
                            <?= $valores['perfil'] === $perfil ? 'selected' : '' ?>
                        >
                            <?= $escapar(ucfirst($perfil)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label for="status" class="form-label">Status</label>
                <select
                    class="form-select<?= isset($errosValidacao['status']) ? ' is-invalid' : '' ?>"
                    id="status"
                    name="status"
                >
                    <option value="ativo" <?= $valores['status'] === 'ativo' ? 'selected' : '' ?>>
                        Ativo
                    </option>
                    <option value="inativo" <?= $valores['status'] === 'inativo' ? 'selected' : '' ?>>
                        Inativo
                    </option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
        <a href="/Contabil/usuarios/listar.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $idUsuario === null ? 'Cadastrar Usuário' : 'Salvar Alterações' ?>
        </button>
    </div>
</form>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
