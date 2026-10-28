<?php

declare(strict_types=1);

namespace App\Core;

final class Validador
{
    // Iniciando array privado para armazenar os dados a serem validados e os erros encontrados durante a validação.
    private array $dados;

    /** @var array<string, list<string>> */
    private array $erros = [];

    // Função construtora para receber os dados a serem validados e armazenar na propriedade privada $dados.
    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    // Função para validar se o campo é obrigatório, verificando se o valor do campo é nulo, vazio ou um array vazio, e adicionando uma mensagem de erro caso a validação falhe.
    public function obrigatorio(string $campo, string $rotulo): self
    {
        $valor = $this->dados[$campo] ?? null;

        if (
            $valor === null
            || (is_string($valor) && trim($valor) === '')
            || (is_array($valor) && $valor === [])
        ) {
            $this->adicionarErro($campo, sprintf('O campo %s é obrigatório.', $rotulo));
        }

        return $this;
    }

    // Função para validar o tamanho máximo do campo, verificando se o valor do campo é maior que o limite especificado, e adicionando uma mensagem de erro caso a validação falhe.
    public function tamanhoMax(string $campo, int $limite, ?string $rotulo = null): self
    {
        $valor = $this->valorTexto($campo);

        if ($valor !== null && $this->tamanho($valor) > $limite) {
            $this->adicionarErro(
                $campo,
                sprintf('O campo %s deve ter no máximo %d caracteres.', $rotulo ?? $campo, $limite)
            );
        }

        return $this;
    }

    // Função para validar o tamanho mínimo do campo, verificando se o valor do campo é menor que o limite especificado, e adicionando uma mensagem de erro caso a validação falhe.
    public function tamanhoMin(string $campo, int $limite, ?string $rotulo = null): self
    {
        $valor = $this->valorTexto($campo);

        // Se o valor do campo não for nulo e o tamanho do valor for menor que o limite definido, será adicionado um erro ao array de erros.
        if ($valor !== null && $this->tamanho($valor) < $limite) {
            $this->adicionarErro(
                $campo,
                sprintf('O campo %s deve ter no mínimo %d caracteres.', $rotulo ?? $campo, $limite)
            );
        }

        return $this;
    }

    // Função para validar se o campo é um e-mail válido, verificando se o valor do campo é um e-mail válido usando a função filter_var, e adicionando uma mensagem de erro caso a validação falhe.
    public function email(string $campo, ?string $rotulo = null): self
    {
        $valor = $this->valorTexto($campo);

        if ($valor !== null && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->adicionarErro($campo, sprintf('O campo %s deve conter um e-mail válido.', $rotulo ?? $campo));
        }

        return $this;
    }

    // Função para verificar se houve falha na validação, retornando true se houver erros e false caso contrário.
    public function falhou(): bool
    {
        return $this->erros !== [];
    }

    // Função para retornar os erros encontrados durante a validação, retornando um array associativo onde a chave é o nome do campo e o valor é um array de mensagens de erro.
    /** @return array<string, list<string>> */
    public function erros(): array
    {
        return $this->erros;
    }

    // Função privada para adicionar uma mensagem de erro ao array de erros, associando a mensagem ao campo correspondente.
    private function adicionarErro(string $campo, string $mensagem): void
    {
        $this->erros[$campo][] = $mensagem;
    }

    // Função privada para obter o valor do campo como texto, retornando null se o valor não for escalar ou se for uma string vazia.
    private function valorTexto(string $campo): ?string
    {
        $valor = $this->dados[$campo] ?? null;

        if (!is_scalar($valor)) {
            return null;
        }

        $texto = (string) $valor;

        return trim($texto) === '' ? null : $texto;
    }

    // Função privada para calcular o tamanho do valor do campo.
    private function tamanho(string $valor): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($valor, 'UTF-8');
        }

        $quantidade = preg_match_all('/./us', $valor, $correspondencias);

        return $quantidade !== false ? $quantidade : strlen($valor);
    }
}
