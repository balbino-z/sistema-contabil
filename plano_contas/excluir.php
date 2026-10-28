<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();
$auth->exigirEscrita();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

App\Core\Csrf::exigir();

$idRecebido = $_GET['id'] ?? null;
if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
    http_response_code(400);
    exit('Identificador de conta inválido.');
}

$id = (int) $idRecebido;
$idEmpresa = $auth->empresaId();
if ($idEmpresa === null) {
    App\Core\Flash::erro('Nenhuma empresa ativa está selecionada.');
    header('Location: /Contabil/plano_contas/listar.php');
    exit;
}

$repo = new App\Repository\ContaContabilRepository();
$conta = $repo->buscar($id);
if ($conta === null || (int) ($conta['id_empresa'] ?? 0) !== $idEmpresa) {
    http_response_code(404);
    exit('Conta contábil não encontrada.');
}

try {
    if ($repo->excluir($id)) {
        App\Core\Flash::sucesso('Conta excluída com sucesso!');
    } else {
        App\Core\Flash::erro('A conta não foi encontrada ou já foi excluída.');
    }
} catch (App\Core\RegraNegocioException $e) {
    App\Core\Flash::erro($e->getMessage());
}

header('Location: /Contabil/plano_contas/listar.php');
exit;
