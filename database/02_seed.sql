-- Dados iniciais de demonstração do Sistema Contábil.
-- Usuários de teste (troque as senhas em produção):
--   admin@contabil.com    / Admin@123     (perfil admin)
--   contador@contabil.com / Contador@123  (perfil contador)
--   consulta@contabil.com / Consulta@123  (perfil consulta)

SET NAMES utf8mb4;

INSERT INTO usuarios (id_usuario, nome, email, senha, perfil, status) VALUES
(1, 'Administrador',      'admin@contabil.com',    '$2y$10$.E1EJYbwQhIMsDXtl.QjA.LA3N1RPtCIRBDcxUBMfNf2VoCXHr4tS', 'admin',    'ativo'),
(2, 'Contador Responsável','contador@contabil.com', '$2y$10$hzoWWWw8gGhknLhIJoeSmemHOwBaWFArFzmCIAO.sybK8G1OYlIeK', 'contador', 'ativo'),
(3, 'Usuário Consulta',   'consulta@contabil.com', '$2y$10$abyk8jITgWxjn1fu/MQRxeF3LJtNScygJLUl4V9Loged9CbmJZ0z.', 'consulta', 'ativo');

INSERT INTO empresas (id_empresa, razao_social, nome_fantasia, cnpj, endereco, regime_tributario, responsavel, status) VALUES
(1, 'Alfa Consultoria Empresarial Ltda', 'Alfa Consultoria', '12.345.678/0001-90', 'Av. Paulista, 1000 - São Paulo/SP', 'simples_nacional', 'Paulo (Diretor Geral)', 'ativo'),
(2, 'Beta Comércio de Equipamentos Ltda', 'Beta Equipamentos', '98.765.432/0001-10', 'Rua das Flores, 250 - Curitiba/PR', 'lucro_presumido', 'Paulo (Diretor Geral)', 'ativo');

-- Exercício 1 = 2025 (fechado), exercício 2 = 2026 (aberto) para a empresa 1.
INSERT INTO exercicios (id_exercicio, id_empresa, ano, data_inicio, data_fim, status) VALUES
(1, 1, 2025, '2025-01-01', '2025-12-31', 'fechado'),
(2, 1, 2026, '2026-01-01', '2026-12-31', 'aberto'),
(3, 2, 2026, '2026-01-01', '2026-12-31', 'aberto');

INSERT INTO centros_custo (id_centro, id_empresa, nome, status) VALUES
(1, 1, 'Administrativo', 'ativo'),
(2, 1, 'Operacional', 'ativo'),
(3, 1, 'Comercial', 'ativo'),
(4, 2, 'Administrativo', 'ativo');

-- Plano de contas da empresa 1
INSERT INTO contas_contabeis (id_conta, id_empresa, codigo, descricao, tipo, natureza, classificacao, conta_pai, nivel, status) VALUES
(1,  1, '1',      'ATIVO',                          'sintetica', 'devedora', 'ativo',              NULL, 1, 'ativo'),
(2,  1, '1.1',    'Ativo Circulante',               'sintetica', 'devedora', 'ativo',              1,    2, 'ativo'),
(3,  1, '1.1.01', 'Caixa',                          'analitica', 'devedora', 'ativo',              2,    3, 'ativo'),
(4,  1, '1.1.02', 'Bancos Conta Movimento',         'analitica', 'devedora', 'ativo',              2,    3, 'ativo'),
(5,  1, '1.1.03', 'Clientes',                       'analitica', 'devedora', 'ativo',              2,    3, 'ativo'),
(6,  1, '1.2',    'Ativo Não Circulante',           'sintetica', 'devedora', 'ativo',              1,    2, 'ativo'),
(7,  1, '1.2.01', 'Imobilizado',                    'analitica', 'devedora', 'ativo',              6,    3, 'ativo'),
(8,  1, '2',      'PASSIVO',                        'sintetica', 'credora',  'passivo',            NULL, 1, 'ativo'),
(9,  1, '2.1',    'Passivo Circulante',             'sintetica', 'credora',  'passivo',            8,    2, 'ativo'),
(10, 1, '2.1.01', 'Fornecedores',                   'analitica', 'credora',  'passivo',            9,    3, 'ativo'),
(11, 1, '2.1.02', 'Impostos a Recolher',            'analitica', 'credora',  'passivo',            9,    3, 'ativo'),
(12, 1, '2.2',    'Passivo Não Circulante',         'sintetica', 'credora',  'passivo',            8,    2, 'ativo'),
(13, 1, '2.2.01', 'Empréstimos de Longo Prazo',     'analitica', 'credora',  'passivo',            12,   3, 'ativo'),
(14, 1, '3',      'PATRIMÔNIO LÍQUIDO',             'sintetica', 'credora',  'patrimonio_liquido', NULL, 1, 'ativo'),
(15, 1, '3.1',    'Capital Social',                 'analitica', 'credora',  'patrimonio_liquido', 14,   2, 'ativo'),
(16, 1, '4',      'RECEITAS',                       'sintetica', 'credora',  'receita',            NULL, 1, 'ativo'),
(17, 1, '4.1',    'Receita de Serviços',            'analitica', 'credora',  'receita',            16,   2, 'ativo'),
(18, 1, '5',      'CUSTOS E DESPESAS',              'sintetica', 'devedora', 'despesa',            NULL, 1, 'ativo'),
(19, 1, '5.1',    'Custos',                         'sintetica', 'devedora', 'custo',              18,   2, 'ativo'),
(20, 1, '5.1.01', 'Custo dos Serviços Prestados',   'analitica', 'devedora', 'custo',              19,   3, 'ativo'),
(21, 1, '5.2',    'Despesas Operacionais',          'sintetica', 'devedora', 'despesa',            18,   2, 'ativo'),
(22, 1, '5.2.01', 'Salários e Encargos',            'analitica', 'devedora', 'despesa',            21,   3, 'ativo'),
(23, 1, '5.2.02', 'Aluguel',                        'analitica', 'devedora', 'despesa',            21,   3, 'ativo');

-- Lançamentos do exercício 2026 da empresa 1 (partidas dobradas: débito = crédito)
INSERT INTO lancamentos (id_empresa, id_exercicio, data, historico, documento, valor, id_conta_debito, id_conta_credito, id_centro_custo, id_usuario, status) VALUES
(1, 2, '2026-01-02', 'Integralização do capital social pelos sócios',    'CONTRATO-001', 50000.00, 4,  15, 1,    1, 'ativo'),
(1, 2, '2026-02-10', 'Compra de computadores e móveis',                  'NF-1020',      12000.00, 7,  4,  1,    2, 'ativo'),
(1, 2, '2026-03-05', 'Prestação de serviços de consultoria à vista',     'NFS-0001',     18000.00, 4,  17, 3,    2, 'ativo'),
(1, 2, '2026-04-15', 'Prestação de serviços a prazo - Cliente XPTO',     'NFS-0002',      9500.00, 5,  17, 3,    2, 'ativo'),
(1, 2, '2026-05-05', 'Pagamento de aluguel do escritório',               'REC-0505',      3000.00, 23, 4,  1,    2, 'ativo'),
(1, 2, '2026-06-05', 'Pagamento da folha de salários',                   'FOLHA-06',      7000.00, 22, 4,  1,    2, 'ativo'),
(1, 2, '2026-07-10', 'Custo de terceiros nos serviços prestados',        'NF-2231',       4200.00, 20, 10, 2,    2, 'ativo'),
(1, 2, '2026-08-20', 'Captação de empréstimo bancário de longo prazo',   'CONTR-BNK',    20000.00, 4,  13, 1,    2, 'ativo'),
(1, 2, '2026-09-12', 'Recebimento parcial do Cliente XPTO',              'REC-0912',      5000.00, 4,  5,  3,    2, 'ativo'),
(1, 2, '2026-10-01', 'Saque para fundo de caixa',                        'CHQ-0110',      1500.00, 3,  4,  1,    2, 'ativo'),
(1, 2, '2026-11-03', 'Lançamento digitado com valor errado (estornado)', 'NF-9999',        999.00, 23, 4,  1,    2, 'cancelado');

-- Plano de contas mínimo da empresa 2
INSERT INTO contas_contabeis (id_empresa, codigo, descricao, tipo, natureza, classificacao, conta_pai, nivel, status) VALUES
(2, '1', 'ATIVO', 'sintetica', 'devedora', 'ativo', NULL, 1, 'ativo');
