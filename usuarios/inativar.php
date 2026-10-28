<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirPerfil(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

App\Core\Csrf::exigir();

$idRecebido = $_GET['id'] ?? null;
if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
    App\Core\Flash::erro('O identificador do usuário é inválido.');
    header('Location: /Contabil/usuarios/listar.php');
    exit;
}

$idUsuario = (int) $idRecebido;
if ($idUsuario === $auth->id()) {
    App\Core\Flash::erro('Não é permitido inativar o próprio usuário.');
    header('Location: /Contabil/usuarios/listar.php');
    exit;
}

$usuarioRepository = new App\Repository\UsuarioRepository();
if ($usuarioRepository->buscar($idUsuario) === null) {
    App\Core\Flash::erro('O usuário informado não foi encontrado.');
    header('Location: /Contabil/usuarios/listar.php');
    exit;
}

$usuarioRepository->atualizar($idUsuario, ['status' => 'inativo']);
App\Core\Flash::sucesso('Usuário inativado com sucesso.');

header('Location: /Contabil/usuarios/listar.php');
exit;
