<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

$auth = new App\Core\Auth();
$auth->exigirLogin();
$auth->exigirEscrita();

$idConta = null;
if (isset($_GET['id'])) {
    $idRecebido = $_GET['id'];
    if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
        http_response_code(400);
        exit('Identificador de conta inválido.');
    }

    $idConta = (int) $idRecebido;
}

$id_empresa = $auth->empresaId();
if ($id_empresa === null) {
    App\Core\Flash::aviso('Selecione uma empresa ativa antes de cadastrar contas.');
    header('Location: /Contabil/plano_contas/listar.php');
    exit;
}

$repo = new App\Repository\ContaContabilRepository();
$conta = null;
if ($idConta !== null) {
    $conta = $repo->buscar($idConta);
    if ($conta === null || (int) ($conta['id_empresa'] ?? 0) !== $id_empresa) {
        http_response_code(404);
        exit('Conta contábil não encontrada.');
    }
}

$contasPai = $repo->listarSinteticasPorEmpresa($id_empresa);
$contasPaiPorId = [];
foreach ($contasPai as $contaPai) {
    $contasPaiPorId[(int) ($contaPai['id_conta'] ?? 0)] = $contaPai;
}

if ($idConta !== null) {
    $contasPai = array_values(array_filter(
        $contasPai,
        static function (array $possivelPai) use ($idConta, $contasPaiPorId): bool {
            $idPossivelPai = (int) ($possivelPai['id_conta'] ?? 0);
            $visitados = [];

            while ($idPossivelPai > 0 && !isset($visitados[$idPossivelPai])) {
                if ($idPossivelPai === $idConta) {
                    return false;
                }

                $visitados[$idPossivelPai] = true;
                $idPossivelPai = (int) ($contasPaiPorId[$idPossivelPai]['conta_pai'] ?? 0);
            }

            return true;
        }
    ));
}

$contasPaiFormularioPorId = [];
foreach ($contasPai as $contaPai) {
    $contasPaiFormularioPorId[(int) ($contaPai['id_conta'] ?? 0)] = $contaPai;
}

$codigoSalvo = (string) ($conta['codigo'] ?? '');
$idContaPaiAtual = isset($conta['conta_pai']) ? (int) $conta['conta_pai'] : 0;
$prefixoAtual = isset($contasPaiFormularioPorId[$idContaPaiAtual])
    ? (string) ($contasPaiFormularioPorId[$idContaPaiAtual]['codigo'] ?? '') . '.'
    : '';
$sufixoAtual = $prefixoAtual !== '' && str_starts_with($codigoSalvo, $prefixoAtual)
    ? substr($codigoSalvo, strlen($prefixoAtual))
    : $codigoSalvo;

$valores = [
    'codigo' => $sufixoAtual,
    'descricao' => (string) ($conta['descricao'] ?? ''),
    'tipo' => (string) ($conta['tipo'] ?? ''),
    'natureza' => (string) ($conta['natureza'] ?? ''),
    'classificacao' => (string) ($conta['classificacao'] ?? ''),
    'conta_pai' => $idContaPaiAtual > 0 ? (string) $idContaPaiAtual : '',
];
$errosValidacao = [];
$tiposPermitidos = ['sintetica', 'analitica'];
$naturezasPermitidas = ['devedora', 'credora'];
$classificacoesPermitidas = [
    'ativo' => 'Ativo',
    'passivo' => 'Passivo',
    'patrimonio_liquido' => 'Patrimônio Líquido',
    'receita' => 'Receita',
    'custo' => 'Custo',
    'despesa' => 'Despesa',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    App\Core\Csrf::exigir();

    foreach (array_keys($valores) as $campo) {
        $valorEnviado = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valorEnviado) ? trim($valorEnviado) : '';
    }

    $idContaPai = null;
    $contaPaiSelecionada = null;
    $nivel = 1;
    $codigoCompleto = $valores['codigo'];

    if ($valores['conta_pai'] !== '') {
        if (!ctype_digit($valores['conta_pai'])) {
            $errosValidacao['conta_pai'][] = 'Selecione uma conta pai válida.';
        } else {
            $idContaPai = (int) $valores['conta_pai'];
            foreach ($contasPai as $possivelPai) {
                if ((int) ($possivelPai['id_conta'] ?? 0) === $idContaPai) {
                    $contaPaiSelecionada = $possivelPai;
                    break;
                }
            }

            if ($contaPaiSelecionada === null) {
                $errosValidacao['conta_pai'][] = 'A conta pai selecionada não é válida para esta empresa.';
            } else {
                $codigoPai = (string) ($contaPaiSelecionada['codigo'] ?? '');
                $codigoCompleto = $codigoPai . '.' . $valores['codigo'];
                $valores['natureza'] = (string) ($contaPaiSelecionada['natureza'] ?? '');
                $valores['classificacao'] = (string) ($contaPaiSelecionada['classificacao'] ?? '');
                $nivel = (int) ($contaPaiSelecionada['nivel'] ?? 1) + 1;
            }
        }
    }

    $validador = new App\Core\Validador($valores);
    $validador
        ->obrigatorio('codigo', 'Código')
        ->obrigatorio('descricao', 'Descrição')
        ->obrigatorio('tipo', 'Tipo')
        ->obrigatorio('natureza', 'Natureza')
        ->obrigatorio('classificacao', 'Classificação');
    $errosValidacao = $validador->erros();

    if (!in_array($valores['tipo'], $tiposPermitidos, true)) {
        $errosValidacao['tipo'][] = 'Selecione um tipo de conta válido.';
    }
    if (!in_array($valores['natureza'], $naturezasPermitidas, true)) {
        $errosValidacao['natureza'][] = 'Selecione uma natureza válida.';
    }
    if (!array_key_exists($valores['classificacao'], $classificacoesPermitidas)) {
        $errosValidacao['classificacao'][] = 'Selecione uma classificação válida.';
    }

    if ($valores['codigo'] !== '' && $codigoCompleto !== '') {
        $contaComCodigo = $repo->buscarPorCodigo($id_empresa, $codigoCompleto, $idConta);
        if ($contaComCodigo !== null) {
            $errosValidacao['codigo'][] = 'O código ' . $codigoCompleto
                . ' já está em uso por outra conta nesta empresa.';
        }
    }

    if ($errosValidacao === []) {
        $dados = [
            'id_empresa' => $id_empresa,
            'codigo' => $codigoCompleto,
            'descricao' => $valores['descricao'],
            'tipo' => $valores['tipo'],
            'natureza' => $valores['natureza'],
            'classificacao' => $valores['classificacao'],
            'conta_pai' => $idContaPai,
            'nivel' => $nivel,
        ];

        try {
            if ($idConta === null) {
                $dados['status'] = 'ativo';
                $repo->salvar($dados);
                App\Core\Flash::sucesso('Conta cadastrada com sucesso.');
            } else {
                $repo->atualizar($idConta, $dados);
                App\Core\Flash::sucesso('Conta atualizada com sucesso.');
            }

            header('Location: /Contabil/plano_contas/listar.php');
            exit;
        } catch (PDOException $exception) {
            $errosValidacao['geral'][] = 'Não foi possível salvar a conta. Verifique os dados e tente novamente.';
        }
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
    <h1 class="h2 mb-1"><?= $idConta === null ? 'Nova Conta' : 'Editar Conta' ?></h1>
    <p class="text-body-secondary mb-0">
        <?= $idConta === null ? 'Preencha os dados para cadastrar uma conta.' : 'Atualize os dados da conta.' ?>
    </p>
</div>

<form
    action="/Contabil/plano_contas/form.php<?= $idConta === null ? '' : '?id=' . $idConta ?>"
    method="post"
    class="card border-0 shadow-sm"
>
    <div class="card-body p-4">
        <?= App\Core\Csrf::campo() ?>
        <?php if ($idConta !== null): ?>
            <input type="hidden" name="id" value="<?= $idConta ?>">
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($errosValidacao !== []): ?>
                <div class="col-12">
                    <div class="alert alert-danger mb-0" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errosValidacao as $errosCampo): ?>
                                <?php foreach ($errosCampo as $erro): ?>
                                    <li><?= $escapar($erro) ?></li>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-12">
                <label for="conta_pai" class="form-label">Conta Pai</label>
                <select
                    class="form-select<?= isset($errosValidacao['conta_pai']) ? ' is-invalid' : '' ?>"
                    id="conta_pai"
                    name="conta_pai"
                >
                    <option value="">Nenhuma</option>
                    <?php foreach ($contasPai as $contaPai): ?>
                        <?php $idContaPaiOpcao = (int) ($contaPai['id_conta'] ?? 0); ?>
                        <option
                            value="<?= $idContaPaiOpcao ?>"
                            data-codigo="<?= $escapar((string) ($contaPai['codigo'] ?? '')) ?>"
                            data-natureza="<?= $escapar((string) ($contaPai['natureza'] ?? '')) ?>"
                            data-classificacao="<?= $escapar((string) ($contaPai['classificacao'] ?? '')) ?>"
                            <?= $valores['conta_pai'] === (string) $idContaPaiOpcao ? 'selected' : '' ?>
                        >
                            <?= $escapar((string) ($contaPai['codigo'] ?? '')) ?>
                            — <?= $escapar((string) ($contaPai['descricao'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errosValidacao['conta_pai'])): ?>
                    <div class="invalid-feedback">
                        <?= $escapar(implode(' ', $errosValidacao['conta_pai'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-4">
                <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text" id="prefixo_codigo"><?= $escapar($prefixoAtual) ?></span>
                    <input
                        type="text"
                        class="form-control<?= isset($errosValidacao['codigo']) ? ' is-invalid' : '' ?>"
                        id="codigo"
                        name="codigo"
                        value="<?= $escapar($valores['codigo']) ?>"
                        placeholder="Ex.: 3"
                        required
                    >
                </div>
                <?php if (isset($errosValidacao['codigo'])): ?>
                    <div class="invalid-feedback d-block">
                        <?= $escapar(implode(' ', $errosValidacao['codigo'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-8">
                <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control<?= isset($errosValidacao['descricao']) ? ' is-invalid' : '' ?>"
                    id="descricao"
                    name="descricao"
                    value="<?= $escapar($valores['descricao']) ?>"
                    required
                >
            </div>

            <div class="col-12 col-md-6">
                <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['tipo']) ? ' is-invalid' : '' ?>"
                    id="tipo"
                    name="tipo"
                    required
                >
                    <option value="">Selecione</option>
                    <option value="sintetica" <?= $valores['tipo'] === 'sintetica' ? 'selected' : '' ?>>Sintética</option>
                    <option value="analitica" <?= $valores['tipo'] === 'analitica' ? 'selected' : '' ?>>Analítica</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label for="natureza" class="form-label">Natureza <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['natureza']) ? ' is-invalid' : '' ?>"
                    id="natureza"
                    name="natureza"
                    required
                >
                    <option value="">Selecione</option>
                    <option value="devedora" <?= $valores['natureza'] === 'devedora' ? 'selected' : '' ?>>Devedora</option>
                    <option value="credora" <?= $valores['natureza'] === 'credora' ? 'selected' : '' ?>>Credora</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label for="classificacao" class="form-label">Classificação <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['classificacao']) ? ' is-invalid' : '' ?>"
                    id="classificacao"
                    name="classificacao"
                    required
                >
                    <option value="">Selecione</option>
                    <?php foreach ($classificacoesPermitidas as $codigo => $rotulo): ?>
                        <option value="<?= $escapar($codigo) ?>" <?= $valores['classificacao'] === $codigo ? 'selected' : '' ?>>
                            <?= $escapar($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="/Contabil/plano_contas/listar.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
            <?= $idConta === null ? 'Cadastrar Conta' : 'Salvar Alterações' ?>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const contaPai = document.getElementById('conta_pai');
    const codigo = document.getElementById('codigo');
    const prefixoCodigo = document.getElementById('prefixo_codigo');
    const natureza = document.getElementById('natureza');
    const classificacao = document.getElementById('classificacao');

    if (!contaPai || !codigo || !prefixoCodigo || !natureza || !classificacao) {
        return;
    }

    function atualizarContaPai(limparCodigo) {
        const opcaoSelecionada = contaPai.options[contaPai.selectedIndex];
        const codigoPai = contaPai.value !== '' ? opcaoSelecionada.dataset.codigo || '' : '';
        const possuiContaPai = codigoPai !== '';

        prefixoCodigo.textContent = possuiContaPai ? codigoPai + '.' : '';
        natureza.disabled = possuiContaPai;
        classificacao.disabled = possuiContaPai;

        if (possuiContaPai) {
            natureza.value = opcaoSelecionada.dataset.natureza || '';
            classificacao.value = opcaoSelecionada.dataset.classificacao || '';
        }

        if (limparCodigo) {
            codigo.value = '';
            codigo.focus();
        }
    }

    contaPai.addEventListener('change', function () {
        atualizarContaPai(true);
    });
    atualizarContaPai(false);
});
</script>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
