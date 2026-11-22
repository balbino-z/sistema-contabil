// @ts-check
const { test, expect } = require('@playwright/test');

const USUARIOS = {
  admin: { email: 'admin@contabil.com', senha: 'Admin@123' },
  contador: { email: 'contador@contabil.com', senha: 'Contador@123' },
  consulta: { email: 'consulta@contabil.com', senha: 'Consulta@123' },
};

async function entrar(page, perfil = 'admin') {
  const { email, senha } = USUARIOS[perfil];
  await page.goto('/Contabil/login.php');
  await page.getByLabel('E-mail').fill(email);
  await page.getByLabel('Senha').fill(senha);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/dashboard\.php/);
}

async function valorDaOpcao(page, campo, texto) {
  return page.locator(`[name="${campo}"] option`, { hasText: texto }).first().getAttribute("value");
}

test.describe.configure({ mode: 'serial' });

test('raiz redireciona para a tela de login', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/Contabil\/login\.php/);
  await expect(page.getByLabel('E-mail')).toBeVisible();
});

test('login com senha errada é recusado', async ({ page }) => {
  await page.goto('/Contabil/login.php');
  await page.getByLabel('E-mail').fill('admin@contabil.com');
  await page.getByLabel('Senha').fill('senha-errada');
  await page.locator('button[type="submit"]').click();
  await expect(page.getByText('E-mail ou senha inválidos.')).toBeVisible();
});

test('dashboard mostra os totais do exercício aberto', async ({ page }) => {
  await entrar(page);
  await expect(page.getByRole('heading', { name: 'Dashboard Contábil' })).toBeVisible();
  const corpo = page.locator('main, body');
  await expect(corpo).toContainText('87.500,00'); // ativos
  await expect(corpo).toContainText('24.200,00'); // passivos
  await expect(corpo).toContainText('27.500,00'); // receitas
  await expect(corpo).toContainText('14.200,00'); // custos + despesas
  await page.screenshot({ path: 'evidencias/01-dashboard.png', fullPage: true });
});

test('listagens de empresas, usuários e plano de contas', async ({ page }) => {
  await entrar(page);
  await page.getByRole('link', { name: 'Empresas' }).click();
  await expect(page.getByText('Alfa Consultoria Empresarial Ltda')).toBeVisible();
  await page.screenshot({ path: 'evidencias/02-empresas.png', fullPage: true });

  await page.getByRole('link', { name: 'Usuários' }).click();
  await expect(page.getByText('contador@contabil.com')).toBeVisible();
  await page.screenshot({ path: 'evidencias/03-usuarios.png', fullPage: true });

  await page.getByRole('link', { name: 'Plano de Contas' }).click();
  await expect(page.getByText('Bancos Conta Movimento')).toBeVisible();
  await page.screenshot({ path: 'evidencias/04-plano-contas.png', fullPage: true });
});

test('cria um lançamento e depois estorna', async ({ page }) => {
  const historico = `Teste E2E Playwright ${Date.now()}`;
  await entrar(page, 'contador');
  await page.getByRole('link', { name: 'Lançamentos' }).click();
  await page.screenshot({ path: 'evidencias/05-lancamentos.png', fullPage: true });
  await page.goto('/Contabil/lancamentos/form.php');

  await page.locator('[name="data"]').fill('2026-11-20');
  await page.locator('[name="documento"]').fill('E2E-001');
  await page.locator('[name="historico"]').fill(historico);
  await page.locator('[name="valor"]').fill('123.45');
  await page.locator('[name="id_conta_debito"]').selectOption(await valorDaOpcao(page, "id_conta_debito", "Caixa"));
  await page.locator('[name="id_conta_credito"]').selectOption(await valorDaOpcao(page, "id_conta_credito", "Bancos"));
  await page.screenshot({ path: 'evidencias/06-novo-lancamento.png', fullPage: true });
  await page.locator('form:has([name="historico"]) button[type="submit"]').click();

  await expect(page).toHaveURL(/lancamentos\/listar\.php/);
  const linha = page.locator('tr', { hasText: historico });
  await expect(linha).toBeVisible();

  page.once('dialog', (d) => d.accept());
  await linha.getByRole('button', { name: /Estornar/ }).click();
  await expect(page.locator('tr', { hasText: historico })).toContainText(/cancelado|estornado/i);
});

test('relatórios: balancete, DRE e balanço fecham', async ({ page }) => {
  await entrar(page);
  await page.goto('/Contabil/relatorios/balancete.php');
  await expect(page.getByRole('heading', { name: 'Balancete' })).toBeVisible();
  await page.screenshot({ path: 'evidencias/07-balancete.png', fullPage: true });

  await page.goto('/Contabil/relatorios/dre.php');
  await expect(page.locator('body')).toContainText('13.300,00'); // resultado líquido
  await page.screenshot({ path: 'evidencias/08-dre.png', fullPage: true });

  await page.goto('/Contabil/relatorios/balanco.php');
  await expect(page.getByText('Igualdade patrimonial confirmada')).toBeVisible();
  await page.screenshot({ path: 'evidencias/09-balanco.png', fullPage: true });
});

test('perfil consulta não vê menu de usuários nem botão de novo lançamento', async ({ page }) => {
  await entrar(page, 'consulta');
  await expect(page.getByRole('link', { name: 'Usuários' })).toHaveCount(0);
  await page.getByRole('link', { name: 'Lançamentos' }).click();
  await expect(page.locator('a[href$="lancamentos/form.php"]')).toHaveCount(0);
});
