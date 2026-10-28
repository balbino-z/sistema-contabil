<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();

$idEmpresa = null;
if (isset($_GET['id'])) {
    $idRecebido = $_GET['id'];
    if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
        http_response_code(400);
        exit('Identificador de empresa inválido.');
    }

    $idEmpresa = (int) $idRecebido;
}

$empresaRepository = null;
$empresa = null;
if ($idEmpresa !== null) {
    $empresaRepository = new App\Repository\EmpresaRepository();
    $empresa = $empresaRepository->buscar($idEmpresa);
}

if ($idEmpresa !== null && $empresa === null) {
    http_response_code(404);
    exit('Empresa não encontrada.');
}

$valores = [
    'razao_social' => (string) ($empresa['razao_social'] ?? ''),
    'nome_fantasia' => (string) ($empresa['nome_fantasia'] ?? ''),
    'cnpj' => (string) ($empresa['cnpj'] ?? ''),
    'endereco' => (string) ($empresa['endereco'] ?? ''),
    'regime_tributario' => (string) ($empresa['regime_tributario'] ?? ''),
    'responsavel' => (string) ($empresa['responsavel'] ?? ''),
    'status' => (string) ($empresa['status'] ?? 'ativo'),
];
$errosValidacao = [];
$regimesPermitidos = [
    'mei' => 'MEI',
    'simples_nacional' => 'Simples Nacional',
    'lucro_presumido' => 'Lucro Presumido',
    'lucro_real' => 'Lucro Real',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    App\Core\Csrf::exigir();

    foreach (array_keys($valores) as $campo) {
        $valorEnviado = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valorEnviado) ? trim($valorEnviado) : '';
    }

    $validador = new App\Core\Validador($valores);
    $validador
        ->obrigatorio('razao_social', 'Razão Social')
        ->obrigatorio('cnpj', 'CNPJ')
        ->obrigatorio('regime_tributario', 'Regime Tributário');
    $errosValidacao = $validador->erros();

    if (!array_key_exists($valores['regime_tributario'], $regimesPermitidos)) {
        $errosValidacao['regime_tributario'][] = 'Selecione um regime tributário válido.';
    }

    $statusPermitidos = ['ativo', 'inativo'];
    if (!in_array($valores['status'], $statusPermitidos, true)) {
        $errosValidacao['status'][] = 'Selecione um status válido.';
    }

    foreach ($errosValidacao as $errosCampo) {
        foreach ($errosCampo as $erro) {
            App\Core\Flash::erro($erro);
        }
    }

    if ($errosValidacao === []) {
        $empresaRepository ??= new App\Repository\EmpresaRepository();

        if ($idEmpresa === null) {
            $empresaRepository->salvar($valores);
            App\Core\Flash::sucesso('Empresa cadastrada com sucesso.');
        } else {
            $empresaRepository->atualizar($idEmpresa, $valores);
            App\Core\Flash::sucesso('Empresa atualizada com sucesso.');
        }

        header('Location: /Contabil/empresas/listar.php');
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
    <h1 class="h2 mb-1"><?= $idEmpresa === null ? 'Nova Empresa' : 'Editar Empresa' ?></h1>
    <p class="text-body-secondary mb-0">
        <?= $idEmpresa === null ? 'Preencha os dados para cadastrar uma empresa.' : 'Atualize os dados da empresa.' ?>
    </p>
</div>

<form
    action="/Contabil/empresas/form.php<?= $idEmpresa === null ? '' : '?id=' . $idEmpresa ?>"
    method="post"
    class="card border-0 shadow-sm"
>
    <div class="card-body p-4">
        <?= App\Core\Csrf::campo() ?>
        <?php if ($idEmpresa !== null): ?>
            <input type="hidden" name="id" value="<?= $idEmpresa ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="razao_social" class="form-label">Razão Social <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control<?= isset($errosValidacao['razao_social']) ? ' is-invalid' : '' ?>"
                    id="razao_social"
                    name="razao_social"
                    value="<?= $escapar($valores['razao_social']) ?>"
                    maxlength="255"
                    required
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                <input
                    type="text"
                    class="form-control"
                    id="nome_fantasia"
                    name="nome_fantasia"
                    value="<?= $escapar($valores['nome_fantasia']) ?>"
                    maxlength="255"
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="cnpj" class="form-label">CNPJ <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control<?= isset($errosValidacao['cnpj']) ? ' is-invalid' : '' ?>"
                    id="cnpj"
                    name="cnpj"
                    value="<?= $escapar($valores['cnpj']) ?>"
                    maxlength="18"
                    required
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="regime_tributario" class="form-label">Regime Tributário <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['regime_tributario']) ? ' is-invalid' : '' ?>"
                    id="regime_tributario"
                    name="regime_tributario"
                    required
                >
                    <?php foreach ($regimesPermitidos as $codigoRegime => $nomeRegime): ?>
                        <option
                            value="<?= $escapar($codigoRegime) ?>"
                            <?= $valores['regime_tributario'] === $codigoRegime ? 'selected' : '' ?>
                        >
                            <?= $escapar($nomeRegime) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label for="endereco" class="form-label">Endereço</label>
                <input
                    type="text"
                    class="form-control"
                    id="endereco"
                    name="endereco"
                    value="<?= $escapar($valores['endereco']) ?>"
                    maxlength="255"
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="responsavel" class="form-label">Responsável</label>
                <input
                    type="text"
                    class="form-control"
                    id="responsavel"
                    name="responsavel"
                    value="<?= $escapar($valores['responsavel']) ?>"
                    maxlength="255"
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
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
        <a href="/Contabil/empresas/listar.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $idEmpresa === null ? 'Cadastrar Empresa' : 'Salvar Alterações' ?>
        </button>
    </div>
</form>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
