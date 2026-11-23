# Entrega — Sistema Contábil na Nuvem

## 1. Acesso

| | |
|---|---|
| **URL pública** | https://sistema-contabil-1b7w.onrender.com |
| **Código (deploy)** | https://github.com/balbino-z/sistema-contabil (privado) |
| **Código original do grupo** | https://github.com/EzzFelt/sistema-contabil |

Usuários de demonstração:

| Perfil | E-mail | Senha | O que pode fazer |
|---|---|---|---|
| Administrador | admin@contabil.com | Admin@123 | Tudo, inclusive gerenciar usuários |
| Contador | contador@contabil.com | Contador@123 | Cadastros, lançamentos e relatórios |
| Consulta | consulta@contabil.com | Consulta@123 | Só visualizar |

> O plano gratuito do Render "dorme" após 15 min sem acesso. O primeiro acesso depois disso pode levar uns 50 segundos. **Abram o site um minuto antes de gravar ou apresentar.**

## 2. O que é o sistema

Aplicação web em **PHP 8** com banco **MySQL**. Ela registra a contabilidade de empresas pelo método das partidas dobradas: todo lançamento tem um débito e um crédito de mesmo valor.

Funcionalidades:
- **Login com perfis** (admin, contador, consulta) e proteção CSRF nos formulários.
- **Empresas**: cadastro, edição e inativação (com CNPJ e regime tributário).
- **Plano de contas** hierárquico: contas sintéticas (agrupadoras) e analíticas (que recebem lançamentos), com natureza devedora ou credora.
- **Lançamentos**: data, histórico, documento, valor, conta débito, conta crédito e centro de custo. O lançamento é **estornado** (fica como cancelado), nunca apagado.
- **Dashboard** com total de ativos, passivos, receitas e despesas, mais os últimos lançamentos.
- **Relatórios**: Balancete, **DRE** (Demonstração do Resultado do Exercício) e **Balanço Patrimonial**, que confere automaticamente se *Ativo = Passivo + PL*.

## 3. Arquitetura na nuvem

```
Usuário (navegador)
      │ HTTPS
      ▼
┌──────────────────────────────┐        TLS/SSL        ┌──────────────────────────┐
│ Render (Web Service, Docker) │ ────────────────────▶ │ Aiven for MySQL 8.4      │
│ PHP 8.3 + Apache             │   porta 14611         │ banco gerenciado (DBaaS) │
│ deploy automático do GitHub  │                       │ backups automáticos      │
└──────────────────────────────┘                       └──────────────────────────┘
      ▲ git push na branch main
GitHub (balbino-z/sistema-contabil)
```

- **Aplicação: Render** (PaaS). Lê o `Dockerfile` do repositório, monta a imagem PHP + Apache e publica com HTTPS. Cada `git push` na `main` gera um novo deploy automático. Plano gratuito.
- **Banco: Aiven for MySQL** (DBaaS, ou seja, banco como serviço). MySQL 8.4 gerenciado, com backups e atualizações por conta do provedor e **conexão obrigatoriamente criptografada (SSL)**. Plano Developer, coberto pelos créditos de trial.
- **Por que MySQL:** o sistema já foi escrito com PDO + MySQL. Manter o MySQL evita reescrever as consultas. É um banco relacional, o que combina com dados contábeis: chaves estrangeiras garantem que todo lançamento aponte para contas, empresa e exercício que existem.
- **Segredos:** a senha do banco não fica no código. Ela é uma variável de ambiente (`DB_PASS`) configurada no painel do Render.

### O que foi adicionado ao projeto para ir para a nuvem
| Arquivo | Para quê |
|---|---|
| `database/01_schema.sql` | Criação das 6 tabelas (usuarios, empresas, exercicios, contas_contabeis, centros_custo, lancamentos) com chaves e restrições. O projeto não tinha isso. |
| `database/02_seed.sql` | Dados de demonstração: 2 empresas, plano de contas, exercícios 2025/2026, 11 lançamentos e 3 usuários. |
| `Dockerfile` | Empacota o app (PHP 8.3 + Apache + PDO MySQL). |
| `docker/init-db.php` | Na primeira subida, se o banco estiver vazio, cria as tabelas e os dados sozinho. |
| `docker/aiven-ca.pem` | Certificado da autoridade do Aiven, para validar a conexão SSL. |
| `render.yaml` | "Blueprint": a infraestrutura do Render descrita como código. |
| `docker-compose.yml` | Sobe app + MySQL na máquina local com um comando. |
| `tests/` | Testes automatizados de ponta a ponta com **Playwright** (Microsoft). |

Correções feitas no código original:
1. O dashboard e a lista de lançamentos usavam o exercício com **id fixo `2`**. Agora usam o exercício **aberto** da empresa.
2. A conexão PDO ganhou suporte a **SSL**, que o Aiven exige.
3. Abrir uma página protegida sem login dava **erro 500**. Agora redireciona para a tela de login.

## 4. Testes (Playwright)

São 8 testes automatizados que abrem um navegador Chromium de verdade e usam o sistema como um usuário faria:

1. A raiz do site redireciona para o login.
2. Uma página protegida sem login volta para o login.
3. Login com senha errada é recusado.
4. O dashboard mostra os totais certos (Ativo R$ 87.500, Passivo R$ 24.200, Receitas R$ 27.500, Despesas R$ 14.200).
5. As listagens de empresas, usuários e plano de contas aparecem.
6. Cria um lançamento e depois o estorna.
7. Relatórios: a DRE dá lucro de R$ 13.300 e o Balanço confirma a igualdade patrimonial.
8. O perfil "consulta" não vê o menu de usuários nem consegue lançar.

Resultado: **todos passando no ambiente local e na nuvem.** As telas capturadas estão em `docs/evidencias/`.

Como rodar:
```powershell
cd tests
npm install
npx playwright install chromium
$env:BASE_URL="https://sistema-contabil-1b7w.onrender.com"; npx playwright test
```

## 5. Rodar localmente

```powershell
docker compose up -d --build
# abrir http://localhost:8088
```

## 6. Dados de demonstração (para explicar no vídeo)

Empresa **Alfa Consultoria Empresarial Ltda**, responsável **Paulo (Diretor Geral)**, exercício 2026:

| Data | Fato | Débito | Crédito | Valor |
|---|---|---|---|---|
| 02/01 | Sócios integralizam capital | Bancos | Capital Social | 50.000 |
| 10/02 | Compra de computadores | Imobilizado | Bancos | 12.000 |
| 05/03 | Serviço à vista | Bancos | Receita de Serviços | 18.000 |
| 15/04 | Serviço a prazo | Clientes | Receita de Serviços | 9.500 |
| 05/05 | Aluguel | Aluguel | Bancos | 3.000 |
| 05/06 | Salários | Salários | Bancos | 7.000 |
| 10/07 | Custo de terceiros | Custo dos Serviços | Fornecedores | 4.200 |
| 20/08 | Empréstimo bancário | Bancos | Empréstimos LP | 20.000 |
| 12/09 | Cliente paga parte | Bancos | Clientes | 5.000 |
| 01/10 | Fundo de caixa | Caixa | Bancos | 1.500 |
| 03/11 | Lançamento errado | — | — | 999 (**estornado**) |

**DRE:** Receitas 27.500 − Custos 4.200 = Lucro bruto 23.300 − Despesas 10.000 = **Lucro líquido 13.300**
**Balanço:** Ativo **87.500** = Passivo 24.200 + PL (Capital 50.000 + Lucro 13.300) **87.500** ✔

## 7. Roteiro sugerido para o vídeo

Recomendação: **opção 2** (reunião no Teams com gravação). Cada pessoa compartilha a tela na sua parte, tudo fica numa gravação só e a edição se resume a cortar começo e fim. Com mini takes separados, o vídeo fica mais bonito, mas dá bem mais trabalho para quem edita.

Duração total: cerca de 8 a 10 minutos. Os nomes abaixo são papéis; distribuam entre o grupo.

| # | Cena | Quem | Tempo | O que mostrar / falar |
|---|---|---|---|---|
| 1 | Abertura | **Paulo (Diretor Geral)** | 0:45 | Apresenta o grupo e a empresa fictícia. O problema: pequenas empresas precisam controlar a contabilidade. A proposta: um sistema web na nuvem. |
| 2 | Arquitetura | Pessoa 2 | 1:30 | Mostrar o diagrama da seção 3. Explicar PaaS (Render) e DBaaS (Aiven MySQL), por que um banco relacional, SSL, variável de ambiente para a senha, e deploy automático a cada push no GitHub. Mostrar rapidamente os painéis do Render e do Aiven. |
| 3 | Login e perfis | Pessoa 3 | 1:00 | Abrir a URL, errar a senha (aparece o erro), entrar como admin. Explicar os 3 perfis. |
| 4 | Cadastros | Pessoa 3 | 1:00 | Empresas (CNPJ, regime tributário) e plano de contas (sintética × analítica, devedora × credora). |
| 5 | Lançamentos | Pessoa 4 | 1:30 | Criar um lançamento ao vivo, por exemplo "Venda de serviço à vista R$ 2.000, D Bancos / C Receita". Depois estornar outro e explicar por que não se apaga lançamento contábil. |
| 6 | Relatórios | Pessoa 5 | 2:00 | Dashboard, Balancete (débitos = créditos), DRE (receitas − custos − despesas = lucro) e Balanço (Ativo = Passivo + PL, com a mensagem de igualdade confirmada). |
| 7 | Qualidade | Pessoa 2 | 0:45 | Rodar `npx playwright test` contra a URL da nuvem e mostrar os testes passando. |
| 8 | Encerramento | **Paulo** | 0:30 | Resumo, aprendizados e agradecimento. |

Dicas: abram o site 1 minuto antes (por causa do "sono" do Render). Usem o login de contador para lançar e o de consulta para mostrar a restrição. Se criarem lançamentos de teste, estornem depois para os relatórios continuarem batendo com a tabela acima.

## 8. Pontos de atenção

- **Aiven:** o banco roda com os créditos de trial (US$ 50, 30 dias, sem cartão). Depois disso o serviço é desligado se não houver forma de pagamento. Apresentem dentro desse prazo ou migrem para outro banco.
- **Render gratuito:** dorme sem uso, e o primeiro acesso demora.
- As senhas de demonstração estão públicas neste documento. Para uso real, troquem-nas na tela de Usuários.
