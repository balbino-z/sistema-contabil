<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Validador;
use App\Repository\ContaContabilRepository;
use App\Repository\ExercicioRepository;
use App\Repository\LancamentoRepository;

$auth = new Auth();
$auth->exigirLogin();
$auth->exigirEscrita();

$id = null;
if (isset($_GET['id'])) {
    $idRecebido = $_GET['id'];
    if (!is_string($idRecebido) || !ctype_digit($idRecebido) || (int) $idRecebido < 1) {
        http_response_code(400);
        exit('Identificador de lançamento inválido.');
    }

    $id = (int) $idRecebido;
}

$idEmpresa = $auth->empresaId();
if ($idEmpresa === null) {
    Flash::erro('Nenhuma empresa ativa está selecionada.');
    header('Location: /Contabil/lancamentos/listar.php');
    exit;
}

$repo = new LancamentoRepository();
$lancamento = null;
if ($id !== null) {
    $lancamento = $repo->buscar($id);
    if (
        $lancamento === null
        || (int) ($lancamento['id_empresa'] ?? 0) !== $idEmpresa
        || ($lancamento['status'] ?? null) === 'cancelado'
    ) {
        Flash::erro('Lançamentos estornados/cancelados não podem ser editados.');
        header('Location: /Contabil/lancamentos/listar.php');
        exit;
    }
}

$exercicio = (new ExercicioRepository())->buscarAbertoPorEmpresa($idEmpresa);
if ($exercicio === null) {
    Flash::erro('Não há exercício contábil aberto para realizar lançamentos.');
    header('Location: /Contabil/lancamentos/listar.php');
    exit;
}

if (
    $lancamento !== null
    && (int) ($lancamento['id_exercicio'] ?? 0) !== (int) ($exercicio['id_exercicio'] ?? 0)
) {
    Flash::erro('Não é possível editar lançamentos de um exercício encerrado.');
    header('Location: /Contabil/lancamentos/listar.php');
    exit;
}

$contaRepository = new ContaContabilRepository();
$contasDisponiveis = $contaRepository->listarAnaliticasAtivasPorEmpresa($idEmpresa);

$conexao = Database::getInstance()->getConnection();
$statementCentros = $conexao->prepare(
    "SELECT id_centro AS id_centro_custo, nome
     FROM centros_custo
     WHERE id_empresa = :id_empresa
       AND status = 'ativo'
     ORDER BY nome ASC"
);
$statementCentros->execute(['id_empresa' => $idEmpresa]);
$centrosCusto = $statementCentros->fetchAll(\PDO::FETCH_ASSOC);

$valores = [
    'data' => isset($lancamento['data']) ? substr((string) $lancamento['data'], 0, 10) : '',
    'historico' => (string) ($lancamento['historico'] ?? ''),
    'documento' => (string) ($lancamento['documento'] ?? ''),
    'valor' => (string) ($lancamento['valor'] ?? ''),
    'id_conta_debito' => isset($lancamento['id_conta_debito'])
        ? (string) $lancamento['id_conta_debito']
        : '',
    'id_conta_credito' => isset($lancamento['id_conta_credito'])
        ? (string) $lancamento['id_conta_credito']
        : '',
    'id_centro_custo' => isset($lancamento['id_centro_custo'])
        ? (string) $lancamento['id_centro_custo']
        : '',
];
$errosValidacao = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::exigir();

    foreach (array_keys($valores) as $campo) {
        $valorEnviado = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valorEnviado) ? trim($valorEnviado) : '';
    }

    $validador = new Validador($valores);
    $validador
        ->obrigatorio('data', 'Data')
        ->obrigatorio('historico', 'Histórico')
        ->obrigatorio('valor', 'Valor')
        ->obrigatorio('id_conta_debito', 'Conta Débito')
        ->obrigatorio('id_conta_credito', 'Conta Crédito');
    $errosValidacao = $validador->erros();

    $dataValida = false;
    if ($valores['data'] !== '') {
        $dataLancamento = DateTimeImmutable::createFromFormat('!Y-m-d', $valores['data']);
        $dataValida = $dataLancamento !== false
            && $dataLancamento->format('Y-m-d') === $valores['data'];
        if (!$dataValida) {
            $errosValidacao['data'][] = 'Informe uma data válida.';
        } else {
            $inicioExercicio = (string) ($exercicio['data_inicio'] ?? '');
            $fimExercicio = (string) ($exercicio['data_fim'] ?? '');
            if (
                ($inicioExercicio !== '' && $valores['data'] < $inicioExercicio)
                || ($fimExercicio !== '' && $valores['data'] > $fimExercicio)
            ) {
                $errosValidacao['data'][] = 'A data deve estar dentro do exercício contábil aberto.';
            }
        }
    }

    if (
        $valores['valor'] !== ''
        && (
            !is_numeric($valores['valor'])
            || !is_finite((float) $valores['valor'])
            || (float) $valores['valor'] <= 0
        )
    ) {
        $errosValidacao['valor'][] = 'O valor do lançamento deve ser maior que zero.';
    }

    $idContaDebito = ctype_digit($valores['id_conta_debito'])
        ? (int) $valores['id_conta_debito']
        : 0;
    $idContaCredito = ctype_digit($valores['id_conta_credito'])
        ? (int) $valores['id_conta_credito']
        : 0;
    if ($idContaDebito > 0 && $idContaDebito === $idContaCredito) {
        $errosValidacao['id_conta_credito'][] = 'A conta de débito e a conta de crédito não podem ser a mesma.';
    }

    $idsContasDisponiveis = [];
    foreach ($contasDisponiveis as $contaDisponivel) {
        $idsContasDisponiveis[(int) ($contaDisponivel['id_conta'] ?? 0)] = true;
    }
    if ($idContaDebito > 0 && !isset($idsContasDisponiveis[$idContaDebito])) {
        $errosValidacao['id_conta_debito'][] = 'Selecione uma conta de débito válida e ativa.';
    }
    if ($idContaCredito > 0 && !isset($idsContasDisponiveis[$idContaCredito])) {
        $errosValidacao['id_conta_credito'][] = 'Selecione uma conta de crédito válida e ativa.';
    }

    $idCentroCusto = null;
    if ($valores['id_centro_custo'] !== '') {
        if (!ctype_digit($valores['id_centro_custo'])) {
            $errosValidacao['id_centro_custo'][] = 'Selecione um centro de custo válido.';
        } else {
            $idCentroCusto = (int) $valores['id_centro_custo'];
            $centroValido = false;
            foreach ($centrosCusto as $centro) {
                if ((int) ($centro['id_centro_custo'] ?? 0) === $idCentroCusto) {
                    $centroValido = true;
                    break;
                }
            }
            if (!$centroValido) {
                $errosValidacao['id_centro_custo'][] = 'Selecione um centro de custo válido e ativo.';
            }
        }
    }

    if ($errosValidacao === []) {
        try {
            $dados = [
                'id_empresa' => $idEmpresa,
                'data' => $valores['data'],
                'historico' => $valores['historico'],
                'documento' => $valores['documento'] !== '' ? $valores['documento'] : null,
                'valor' => (float) $valores['valor'],
                'id_conta_debito' => $idContaDebito,
                'id_conta_credito' => $idContaCredito,
                'id_centro_custo' => $idCentroCusto,
            ];

            if ($id === null) {
                $dados['id_exercicio'] = (int) $exercicio['id_exercicio'];
                $dados['id_usuario'] = $auth->id();
                $dados['status'] = 'ativo';
                $repo->salvar($dados);
                Flash::sucesso('Lançamento realizado com sucesso!');
            } else {
                $repo->atualizar($id, $dados);
                Flash::sucesso('Lançamento atualizado com sucesso!');
            }

            header('Location: /Contabil/lancamentos/listar.php');
            exit;
        } catch (\PDOException $exception) {
            $errosValidacao['geral'][] = 'Não foi possível salvar o lançamento. Verifique os dados e tente novamente.';
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
    <h1 class="h2 mb-1"><?= $id === null ? 'Novo Lançamento' : 'Editar Lançamento' ?></h1>
    <p class="text-body-secondary mb-0">
        Exercício <?= $escapar((string) ($exercicio['ano'] ?? '')) ?>
    </p>
</div>

<form
    action="/Contabil/lancamentos/form.php<?= $id === null ? '' : '?id=' . $id ?>"
    method="post"
    class="card border-0 shadow-sm"
>
    <div class="card-body p-4">
        <?= Csrf::campo() ?>

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

            <div class="col-12 col-md-4">
                <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                <input
                    type="date"
                    class="form-control<?= isset($errosValidacao['data']) ? ' is-invalid' : '' ?>"
                    id="data"
                    name="data"
                    value="<?= $escapar($valores['data']) ?>"
                    required
                >
            </div>

            <div class="col-12 col-md-8" id="campo-conta-debito">
                <label for="documento" class="form-label">Número do Documento</label>
                <input
                    type="text"
                    class="form-control"
                    id="documento"
                    name="documento"
                    value="<?= $escapar($valores['documento']) ?>"
                >
            </div>

            <div class="col-12">
                <label for="historico" class="form-label">Histórico <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control<?= isset($errosValidacao['historico']) ? ' is-invalid' : '' ?>"
                    id="historico"
                    name="historico"
                    value="<?= $escapar($valores['historico']) ?>"
                    required
                >
            </div>

            <div class="col-12 col-md-4">
                <label for="valor" class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                <input
                    type="number"
                    class="form-control<?= isset($errosValidacao['valor']) ? ' is-invalid' : '' ?>"
                    id="valor"
                    name="valor"
                    value="<?= $escapar($valores['valor']) ?>"
                    min="0.01"
                    step="0.01"
                    required
                >
            </div>

            <div class="col-12 col-md-8">
                <label for="id_conta_debito" class="form-label">Conta Débito <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['id_conta_debito']) ? ' is-invalid' : '' ?>"
                    id="id_conta_debito"
                    name="id_conta_debito"
                    required
                >
                    <option value="">Selecione uma conta</option>
                    <?php foreach ($contasDisponiveis as $contaDisponivel): ?>
                        <?php $idContaOpcao = (int) ($contaDisponivel['id_conta'] ?? 0); ?>
                        <option
                            value="<?= $idContaOpcao ?>"
                            <?= $valores['id_conta_debito'] === (string) $idContaOpcao ? 'selected' : '' ?>
                        >
                            <?= $escapar((string) ($contaDisponivel['codigo'] ?? '')) ?>
                            — <?= $escapar((string) ($contaDisponivel['descricao'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-8" id="campo-conta-credito">
                <label for="id_conta_credito" class="form-label">Conta Crédito <span class="text-danger">*</span></label>
                <select
                    class="form-select<?= isset($errosValidacao['id_conta_credito']) ? ' is-invalid' : '' ?>"
                    id="id_conta_credito"
                    name="id_conta_credito"
                    required
                >
                    <option value="">Selecione uma conta</option>
                    <?php foreach ($contasDisponiveis as $contaDisponivel): ?>
                        <?php $idContaOpcao = (int) ($contaDisponivel['id_conta'] ?? 0); ?>
                        <option
                            value="<?= $idContaOpcao ?>"
                            <?= $valores['id_conta_credito'] === (string) $idContaOpcao ? 'selected' : '' ?>
                        >
                            <?= $escapar((string) ($contaDisponivel['codigo'] ?? '')) ?>
                            — <?= $escapar((string) ($contaDisponivel['descricao'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-4">
                <label for="id_centro_custo" class="form-label">Centro de Custo</label>
                <select
                    class="form-select<?= isset($errosValidacao['id_centro_custo']) ? ' is-invalid' : '' ?>"
                    id="id_centro_custo"
                    name="id_centro_custo"
                >
                    <option value="">Nenhum</option>
                    <?php foreach ($centrosCusto as $centro): ?>
                        <?php $idCentroOpcao = (int) ($centro['id_centro_custo'] ?? 0); ?>
                        <option
                            value="<?= $idCentroOpcao ?>"
                            <?= $valores['id_centro_custo'] === (string) $idCentroOpcao ? 'selected' : '' ?>
                        >
                            <?= $escapar((string) ($centro['nome'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="/Contabil/lancamentos/listar.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
            <span id="texto-botao-submit">
                <?= $id === null ? 'Realizar Lançamento' : 'Salvar Alterações' ?>
            </span>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const contaDebito = document.getElementById('id_conta_debito');
    const contaCredito = document.getElementById('id_conta_credito');
    const formulario = contaDebito ? contaDebito.form : null;

    if (!contaDebito || !contaCredito || !formulario) {
        return;
    }

    const botaoSubmit = formulario.querySelector('button[type="submit"]');
    const campoCredito = document.getElementById('campo-conta-credito');

    if (!botaoSubmit || !campoCredito) {
        return;
    }

    const alertaContasIguais = document.createElement('div');
    alertaContasIguais.className = 'invalid-feedback d-block';
    alertaContasIguais.setAttribute('role', 'alert');
    alertaContasIguais.textContent = 'A conta de débito não pode ser igual à conta de crédito.';
    alertaContasIguais.hidden = true;
    campoCredito.appendChild(alertaContasIguais);

    function validarContas() {
        const contasIguais = contaDebito.value !== ''
            && contaCredito.value !== ''
            && contaDebito.value === contaCredito.value;

        alertaContasIguais.hidden = !contasIguais;
        contaCredito.classList.toggle('is-invalid', contasIguais);
        botaoSubmit.disabled = contasIguais;
    }

    contaDebito.addEventListener('change', validarContas);
    contaCredito.addEventListener('change', validarContas);
    validarContas();
});
</script>

<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'footer.php';
