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
    exit('Identificador de lançamento inválido.');
}

$idEmpresa = $auth->empresaId();
if ($idEmpresa === null) {
    App\Core\Flash::erro('Nenhuma empresa ativa está selecionada.');
    header('Location: /Contabil/lancamentos/listar.php');
    exit;
}

$idLancamento = (int) $idRecebido;
$lancamentoRepository = new App\Repository\LancamentoRepository();
$lancamento = $lancamentoRepository->buscar($idLancamento);
if ($lancamento === null || (int) ($lancamento['id_empresa'] ?? 0) !== $idEmpresa) {
    http_response_code(404);
    exit('Lançamento não encontrado.');
}

$exercicioAberto = (new App\Repository\ExercicioRepository())
    ->buscarAbertoPorEmpresa($idEmpresa);
if (
    $exercicioAberto === null
    || (int) ($lancamento['id_exercicio'] ?? 0) !== (int) ($exercicioAberto['id_exercicio'] ?? 0)
) {
    App\Core\Flash::erro('Não é possível estornar lançamentos de um exercício encerrado.');
    header('Location: /Contabil/lancamentos/listar.php');
    exit;
}

if (($lancamento['status'] ?? null) !== 'ativo') {
    App\Core\Flash::erro('Somente lançamentos ativos podem ser estornados.');
    header('Location: /Contabil/lancamentos/listar.php');
    exit;
}

if ($lancamentoRepository->estornar($idLancamento, $idEmpresa)) {
    App\Core\Flash::sucesso('Lançamento estornado com sucesso!');
} else {
    App\Core\Flash::erro('Não foi possível estornar o lançamento.');
}

header('Location: /Contabil/lancamentos/listar.php');
exit;
