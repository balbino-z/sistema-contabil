<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

// Chamando o arquivo de configuração para chamar as variáveis de ambiente e configurações do banco de dados.
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'config.php';

final class Database
{

    // Nomeando a instância como nula para que seja possível criar uma nova instância do banco de dados.
    private static ?self $instance = null;

    // Chamando a variável de conexão como privada para que não seja possível acessar diretamente a conexão do banco de dados.
    private PDO $connection;


    //Função construtora para que não seja possível criar outra instância do banco de dados, garantindo que seja uma classe única.
    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST
            . ';port=' . DB_PORT
            . ';dbname=' . DB_NAME
            . ';charset=' . DB_CHARSET;

        // Criando a conexão com o banco usando PDO e tratando possíveis erros de conexão.    
        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Não foi possível conectar ao banco de dados. Verifique as configurações e tente novamente.',
                0,
                $exception
            );
        }
    }

    // Chamando a função getInstance para que seja possível acessar a conexão do banco de dados de forma única.
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Realizando a conexão com o banco de dados e retornando a instância da conexão.
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
