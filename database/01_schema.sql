-- Schema do Sistema Contábil (MySQL 8 / compatível)
-- Gerado a partir das consultas dos repositórios em src/Repository.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(120) NOT NULL,
    email          VARCHAR(160) NOT NULL,
    senha          VARCHAR(255) NOT NULL,
    perfil         ENUM('admin', 'contador', 'consulta') NOT NULL DEFAULT 'consulta',
    status         ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    data_cadastro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empresas (
    id_empresa         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razao_social       VARCHAR(160) NOT NULL,
    nome_fantasia      VARCHAR(160) NULL,
    cnpj               VARCHAR(18) NOT NULL,
    endereco           VARCHAR(255) NULL,
    regime_tributario  VARCHAR(30) NOT NULL,
    responsavel        VARCHAR(120) NULL,
    status             ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    UNIQUE KEY uk_empresas_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercicios (
    id_exercicio  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_empresa    INT UNSIGNED NOT NULL,
    ano           SMALLINT UNSIGNED NOT NULL,
    data_inicio   DATE NOT NULL,
    data_fim      DATE NOT NULL,
    status        ENUM('aberto', 'fechado') NOT NULL DEFAULT 'aberto',
    UNIQUE KEY uk_exercicios_empresa_ano (id_empresa, ano),
    CONSTRAINT fk_exercicios_empresa FOREIGN KEY (id_empresa) REFERENCES empresas (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_contabeis (
    id_conta       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_empresa     INT UNSIGNED NOT NULL,
    codigo         VARCHAR(30) NOT NULL,
    descricao      VARCHAR(160) NOT NULL,
    tipo           ENUM('sintetica', 'analitica') NOT NULL,
    natureza       ENUM('devedora', 'credora') NOT NULL,
    classificacao  ENUM('ativo', 'passivo', 'patrimonio_liquido', 'receita', 'custo', 'despesa') NOT NULL,
    conta_pai      INT UNSIGNED NULL,
    nivel          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status         ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    UNIQUE KEY uk_contas_empresa_codigo (id_empresa, codigo),
    CONSTRAINT fk_contas_empresa FOREIGN KEY (id_empresa) REFERENCES empresas (id_empresa),
    CONSTRAINT fk_contas_pai FOREIGN KEY (conta_pai) REFERENCES contas_contabeis (id_conta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS centros_custo (
    id_centro   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_empresa  INT UNSIGNED NOT NULL,
    nome        VARCHAR(120) NOT NULL,
    status      ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    CONSTRAINT fk_centros_empresa FOREIGN KEY (id_empresa) REFERENCES empresas (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lancamentos (
    id_lancamento     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_empresa        INT UNSIGNED NOT NULL,
    id_exercicio      INT UNSIGNED NOT NULL,
    data              DATE NOT NULL,
    historico         VARCHAR(255) NOT NULL,
    documento         VARCHAR(60) NULL,
    valor             DECIMAL(15, 2) NOT NULL,
    id_conta_debito   INT UNSIGNED NOT NULL,
    id_conta_credito  INT UNSIGNED NOT NULL,
    id_centro_custo   INT UNSIGNED NULL,
    id_usuario        INT UNSIGNED NOT NULL,
    status            ENUM('ativo', 'cancelado') NOT NULL DEFAULT 'ativo',
    KEY idx_lancamentos_empresa_exercicio (id_empresa, id_exercicio, data),
    CONSTRAINT fk_lanc_empresa   FOREIGN KEY (id_empresa)       REFERENCES empresas (id_empresa),
    CONSTRAINT fk_lanc_exercicio FOREIGN KEY (id_exercicio)     REFERENCES exercicios (id_exercicio),
    CONSTRAINT fk_lanc_debito    FOREIGN KEY (id_conta_debito)  REFERENCES contas_contabeis (id_conta),
    CONSTRAINT fk_lanc_credito   FOREIGN KEY (id_conta_credito) REFERENCES contas_contabeis (id_conta),
    CONSTRAINT fk_lanc_centro    FOREIGN KEY (id_centro_custo)  REFERENCES centros_custo (id_centro),
    CONSTRAINT fk_lanc_usuario   FOREIGN KEY (id_usuario)       REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_lanc_valor     CHECK (valor > 0),
    CONSTRAINT ck_lanc_contas    CHECK (id_conta_debito <> id_conta_credito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
