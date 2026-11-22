<?php

declare(strict_types=1);

// Executado na subida do container: se o banco estiver vazio (sem a tabela
// `usuarios`), aplica o schema e os dados iniciais de /opt/database.
// Defina DB_AUTO_INIT=false para desativar.

require '/var/www/html/Contabil/autoload.php';

if (!filter_var(getenv('DB_AUTO_INIT') ?: 'true', FILTER_VALIDATE_BOOLEAN)) {
    fwrite(STDERR, "[init-db] desativado (DB_AUTO_INIT=false)\n");
    exit(0);
}

$conexao = null;
for ($tentativa = 1; $tentativa <= 10; $tentativa++) {
    try {
        $conexao = App\Core\Database::getInstance()->getConnection();
        break;
    } catch (Throwable $erro) {
        fwrite(STDERR, "[init-db] banco indisponível (tentativa {$tentativa}): "
            . ($erro->getPrevious()?->getMessage() ?? $erro->getMessage()) . "\n");
        sleep(3);
    }
}

if ($conexao === null) {
    fwrite(STDERR, "[init-db] não foi possível conectar; seguindo sem inicializar.\n");
    exit(0);
}

$existe = $conexao->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
if ($existe !== false) {
    fwrite(STDERR, "[init-db] schema já existe, nada a fazer.\n");
    exit(0);
}

foreach (glob('/opt/database/*.sql') as $arquivo) {
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($arquivo));
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $comando) {
        if (trim($comando) !== '') {
            $conexao->exec($comando);
        }
    }
    fwrite(STDERR, '[init-db] aplicado ' . basename($arquivo) . "\n");
}
