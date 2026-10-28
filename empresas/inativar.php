<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

App\Core\Csrf::exigir();

$idRecebido = $_GET['id'] ?? null;
if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
    App\Core\Flash::erro('O identificador da empresa é inválido.');
    header('Location: /Contabil/empresas/listar.php');
    exit;
}

$empresaRepository = new App\Repository\EmpresaRepository();
$empresa = $empresaRepository->buscar((int) $idRecebido);

if ($empresa === null) {
    App\Core\Flash::erro('A empresa informada não foi encontrada.');
    header('Location: /Contabil/empresas/listar.php');
    exit;
}

if (($empresa['status'] ?? null) !== 'inativo') {
    $empresaRepository->atualizar((int) $idRecebido, ['status' => 'inativo']);
}

App\Core\Flash::sucesso('Empresa inativada com sucesso!');
header('Location: /Contabil/empresas/listar.php');
exit;
