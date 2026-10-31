<?php

namespace App\Services;

use App\Enums\AcaoConfiguracao;
use App\Models\ConfiguracoesHistoricos;
use App\Models\Usuarios;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Histórico da tela Configurações (spec 021, FR-003a, research R-16): uma linha por salvamento, só
 * com os campos que mudaram e o valor antes e depois; segredos aparecem só como "alterada".
 */
class HistoricoConfiguracoes
{
    /**
     * Campos cujo valor nunca vai para o histórico.
     */
    private const SEGREDOS = [];

    /**
     * Grava os dados no registro e registra os campos que mudaram (sem mudança, não registra nada).
     *
     * @param  array<string, mixed>  $dados
     */
    public function alterar(Usuarios $autor, string $secao, Model $registro, array $dados): Model
    {
        return DB::transaction(function () use ($autor, $secao, $registro, $dados) {
            $campos = array_keys($dados);
            $antes = $this->valores($registro, $campos);

            $registro->update($dados);

            $alteracoes = $this->diferencas($antes, $this->valores($registro, $campos));

            if ($alteracoes !== []) {
                $this->registrar($autor, $secao, AcaoConfiguracao::Alteração, null, $alteracoes);
            }

            return $registro;
        });
    }

    /**
     * Registra uma ação sem gravar campos (cadastro, exclusão, vínculo, conexão...).
     *
     * @param  array<string, array{antes: mixed, depois: mixed}>  $alteracoes
     */
    public function registrar(Usuarios $autor, string $secao, AcaoConfiguracao $acao, ?int $item_id = null, array $alteracoes = []): void
    {
        ConfiguracoesHistoricos::create([
            'usuarios_id' => $autor->id,
            'secao' => $secao,
            'acao' => $acao,
            'item_id' => $item_id,
            'alteracoes' => $alteracoes === [] ? null : $alteracoes,
        ]);
    }

    /**
     * Diferenças entre dois conjuntos de valores já normalizados, com os segredos mascarados.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @return array<string, array{antes: mixed, depois: mixed}>
     */
    public function diferencas(array $antes, array $depois): array
    {
        $alteracoes = [];

        foreach ($depois as $campo => $valor) {
            if (($antes[$campo] ?? null) === $valor) {
                continue;
            }

            $alteracoes[$campo] = in_array($campo, self::SEGREDOS, true)
                ? ['antes' => '***', 'depois' => 'alterada']
                : ['antes' => $antes[$campo] ?? null, 'depois' => $valor];
        }

        return $alteracoes;
    }

    /**
     * Valores dos campos já com os casts do model, em formato comparável e gravável em JSON.
     *
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    public function valores(Model $registro, array $campos): array
    {
        $valores = [];

        foreach ($campos as $campo) {
            $valores[$campo] = $this->normalizar($registro->getAttribute($campo));
        }

        return $valores;
    }

    private function normalizar(mixed $valor): mixed
    {
        return match (true) {
            $valor instanceof BackedEnum => $valor->value,
            $valor instanceof DateTimeInterface => $valor->format(DateTimeInterface::ATOM),
            is_array($valor) => json_encode($valor, JSON_UNESCAPED_UNICODE),
            is_float($valor), is_int($valor) => (string) $valor,
            default => $valor,
        };
    }
}
