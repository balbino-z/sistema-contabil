# Sistema Contábil

Aplicação web em PHP para registrar e consultar informações contábeis de empresas. A interface e as mensagens estão em português do Brasil.

## Funcionalidades

- Autenticação de usuários e controle de acesso por perfil.
- Cadastro e inativação de empresas.
- Manutenção do plano de contas hierárquico, com contas sintéticas e analíticas.
- Registro, edição e estorno de lançamentos de débito e crédito.
- Dashboard com resumo financeiro e lançamentos recentes.
- Relatórios de balancete, Demonstração do Resultado do Exercício (DRE) e balanço patrimonial.
- Proteção CSRF nos formulários e mensagens temporárias (flash) na sessão.

## Tecnologias e requisitos

- PHP 8.0 ou superior.
- Extensões PHP `PDO` e `pdo_mysql`.
- MySQL, com o banco e as tabelas esperados pela aplicação já criados.
- Apache ou outro servidor web configurado para executar PHP.
- Acesso à internet para carregar Bootstrap e Bootstrap Icons via CDN.

O projeto não inclui um gerenciador de dependências, migrações, script de criação do banco ou dados iniciais. Portanto, é necessário preparar o schema e os registros de referência antes de usar a aplicação.

## Executar localmente com XAMPP

1. Coloque o projeto em `C:\xampp\htdocs\Contabil`.
2. Inicie Apache e MySQL pelo painel do XAMPP.
3. Crie o banco de dados e configure as tabelas e os dados iniciais descritos em [Banco de dados](#banco-de-dados).
4. Configure as variáveis de ambiente do PHP/Apache descritas em [Configuração](#configuração) e reinicie o Apache após alterá-las.
5. Acesse `http://localhost/Contabil/` no navegador.

Os caminhos de navegação e de recursos estáticos da interface usam `/Contabil`; para executar o projeto em outro caminho, esses caminhos também precisam ser ajustados.

## Configuração

As configurações são lidas por `getenv()` em `config/config.php`. O projeto não carrega automaticamente um arquivo `.env`; defina as variáveis no ambiente em que o PHP/Apache é executado.

| Variável | Padrão | Descrição |
| --- | --- | --- |
| `DB_HOST` | `localhost` | Host do MySQL |
| `DB_PORT` | `3306` | Porta do MySQL |
| `DB_NAME` | `sistema_contabil` | Nome do banco de dados |
| `DB_USER` | `root` | Usuário do banco |
| `DB_PASS` | vazio | Senha do banco |
| `DB_CHARSET` | `utf8mb4` | Charset da conexão |
| `APP_DEBUG` | `false` | Indicador booleano de depuração da aplicação |

Altere as credenciais padrão para valores apropriados ao seu ambiente, especialmente fora de uma instalação local.

## Banco de dados

As consultas dos repositórios pressupõem estas tabelas e colunas (os nomes refletem o código da aplicação):

| Tabela | Colunas utilizadas |
| --- | --- |
| `usuarios` | `id_usuario`, `nome`, `email`, `senha`, `perfil`, `status`, `data_cadastro` |
| `empresas` | `id_empresa`, `razao_social`, `nome_fantasia`, `cnpj`, `endereco`, `regime_tributario`, `responsavel`, `status` |
| `exercicios` | `id_exercicio`, `id_empresa`, `ano`, `data_inicio`, `data_fim`, `status` |
| `contas_contabeis` | `id_conta`, `id_empresa`, `codigo`, `descricao`, `tipo`, `natureza`, `classificacao`, `conta_pai`, `nivel`, `status` |
| `lancamentos` | `id_lancamento`, `id_empresa`, `id_exercicio`, `data`, `historico`, `documento`, `valor`, `id_conta_debito`, `id_conta_credito`, `id_centro_custo`, `id_usuario`, `status` |
| `centros_custo` | `id_centro`, `id_empresa`, `nome`, `status` |

Os registros devem respeitar as relações entre empresa, exercício, contas, lançamentos e centros de custo. Para começar, cadastre ou importe pelo menos um usuário ativo, uma empresa ativa, um exercício contábil aberto para essa empresa, contas contábeis e, quando aplicável, centros de custo. As senhas armazenadas em `usuarios.senha` devem ser hashes compatíveis com `password_verify()` do PHP, não texto puro.

Valores reconhecidos pelo código incluem:

- Perfil de usuário: `admin`, `contador` ou `consulta`. A administração de usuários é restrita ao perfil `admin`; `admin` e `contador` são os perfis considerados gravadores pela aplicação.
- Status de usuário e empresa: `ativo` ou `inativo`.
- Exercício: `aberto` para permitir lançamentos.
- Tipo de conta: `sintetica` ou `analitica`; natureza: `devedora` ou `credora`.
- Status do lançamento: `ativo` ou `cancelado`.
- Classificações de conta: `ativo`, `passivo`, `patrimonio_liquido`, `receita`, `custo` ou `despesa`.

Os relatórios também dependem da estrutura dos códigos contábeis: a DRE agrupa receitas em `4`, custos em `5.1` e despesas em `5.2`; o balanço usa `1.1` e `1.2` para grupos do ativo, `2.1` e `2.2` para grupos do passivo e a classificação `patrimonio_liquido` para o patrimônio líquido.

O dashboard e a listagem de lançamentos usam atualmente o identificador de exercício `2` fixo no código. Garanta que esse exercício exista e seja compatível com as empresas cadastradas, ou adapte essas páginas para selecionar o exercício dinamicamente.

## Organização do código

```text
.
├── config/                 Configuração de conexão e aplicação
├── src/
│   ├── Core/               Autenticação, sessão, CSRF, validação e conexão PDO
│   └── Repository/         Consultas e persistência de dados
├── empresas/               Cadastro, listagem e inativação de empresas
├── lancamentos/            Cadastro, listagem e estorno de lançamentos
├── plano_contas/           Cadastro, listagem e exclusão de contas
├── relatorios/             Balancete, DRE e balanço patrimonial
├── usuarios/               Administração de usuários
├── includes/               Cabeçalho e rodapé compartilhados
├── js/                     Interações da interface
├── autoload.php            Autoloader das classes com namespace App\\
├── index.php               Redirecionamento para login ou dashboard
├── login.php               Autenticação
├── logout.php              Encerramento da sessão
└── dashboard.php           Resumo contábil
```

## Páginas principais

- `/Contabil/login.php` — autenticação.
- `/Contabil/dashboard.php` — indicadores financeiros e lançamentos recentes.
- `/Contabil/empresas/listar.php` — empresas.
- `/Contabil/usuarios/listar.php` — usuários, disponível para administradores.
- `/Contabil/plano_contas/listar.php` — plano de contas da empresa ativa.
- `/Contabil/lancamentos/listar.php` — lançamentos da empresa e exercício configurados.
- `/Contabil/relatorios/balancete.php`, `/Contabil/relatorios/dre.php` e `/Contabil/relatorios/balanco.php` — relatórios por exercício.

## Observações

- O sistema seleciona uma empresa ativa na sessão; quando ainda não há uma selecionada, utiliza a primeira empresa ativa encontrada.
- Lançamentos só podem ser criados ou editados em um exercício aberto e com data dentro do período do exercício.
- Estornar um lançamento altera seu status para `cancelado`; ele não é apagado do banco.
- Não há suíte de testes ou comandos de build definidos no repositório.
## Deploy na nuvem (Render + Aiven)

A aplicação está publicada em **https://sistema-contabil-1b7w.onrender.com**. O app roda no Render (Docker) e o banco é um MySQL 8.4 gerenciado no Aiven, com conexão SSL. Veja [ENTREGA.md](ENTREGA.md) para arquitetura, usuários de demonstração, testes e roteiro de apresentação.

Execução local com Docker: `docker compose up -d --build` e acesse `http://localhost:8088`. Testes E2E: `cd tests && npm install && npx playwright test`.

Variáveis extras: `DB_SSL` (ativa TLS), `DB_SSL_CA` / `DB_SSL_CA_PEM` (certificado da CA) e `DB_AUTO_INIT` (cria schema e dados iniciais em banco vazio; padrão `true`).
