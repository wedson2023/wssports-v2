<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Support\Facades\DB;

/**
 * Configurações dos vendedores: criação no cadastro (cópia de um colega) e alteração em massa
 * pelo alcance escolhido (supervisão, gerente ou vendedor).
 */
class ConfiguracoesVendedores
{
    private const MENSAGEM_SEM_PERMISSAO = 'Você não tem permissão para esta ação.';

    /**
     * Configuração do vendedor novo: cópia da de outro vendedor do mesmo gerente; se for o
     * primeiro, de um vendedor da mesma supervisão; se não houver, os valores padrão das colunas.
     */
    public function criar_para(Usuarios $vendedor): UsuariosConfiguracoes
    {
        $modelo = $this->configuracao_de_colega($vendedor);

        $configuracao = UsuariosConfiguracoes::firstOrCreate(
            ['usuarios_id' => $vendedor->id],
            $modelo?->only(UsuariosConfiguracoes::CAMPOS) ?? [],
        );

        return $configuracao->refresh();
    }

    /**
     * Grava só os campos enviados em todos os vendedores do alcance e devolve quantos foram
     * alterados.
     *
     * @param  array<string, mixed>  $campos
     */
    public function alterar(Usuarios $solicitante, Usuarios $alvo, array $campos): int
    {
        $this->garantir_alcance($solicitante, $alvo);

        $vendedores = $alvo->funcao() === Funcao::Vendedor
            ? [$alvo->id]
            : Usuarios::role(Funcao::Vendedor->value)->whereIn('id', $alvo->ids_sub_hierarquia())->pluck('id')->all();

        if ($vendedores === []) {
            return 0;
        }

        DB::transaction(function () use ($vendedores, $campos) {
            // vendedores sem linha recebem a configuração com os valores padrão antes da alteração
            $existentes = UsuariosConfiguracoes::whereIn('usuarios_id', $vendedores)->pluck('usuarios_id')->all();

            foreach (array_diff($vendedores, $existentes) as $usuarios_id) {
                UsuariosConfiguracoes::create(['usuarios_id' => $usuarios_id]);
            }

            if (array_key_exists('esportes_permitidos', $campos)) {
                $campos['esportes_permitidos'] = json_encode(array_values($campos['esportes_permitidos']));
            }

            UsuariosConfiguracoes::whereIn('usuarios_id', $vendedores)->toBase()->update([...$campos, 'updated_at' => now()]);
        });

        return count($vendedores);
    }

    /**
     * Gerente → um vendedor dele; Supervisor → um gerente ou vendedor dele; Admin → supervisor,
     * gerente ou vendedor abaixo dele. Fora disso, sem permissão.
     */
    public function garantir_alcance(Usuarios $solicitante, Usuarios $alvo): void
    {
        $funcoes_permitidas = match ($solicitante->funcao()) {
            Funcao::Admin => [Funcao::Supervisor, Funcao::Gerente, Funcao::Vendedor],
            Funcao::Supervisor => [Funcao::Gerente, Funcao::Vendedor],
            Funcao::Gerente => [Funcao::Vendedor],
            default => [],
        };

        abort_unless(
            in_array($alvo->funcao(), $funcoes_permitidas, true) && $solicitante->gerencia($alvo),
            403,
            self::MENSAGEM_SEM_PERMISSAO,
        );
    }

    private function configuracao_de_colega(Usuarios $vendedor): ?UsuariosConfiguracoes
    {
        $gerente = $vendedor->superior;

        if ($gerente === null) {
            return null;
        }

        $de_gerente = UsuariosConfiguracoes::whereIn('usuarios_id', $gerente->subordinados()->where('id', '!=', $vendedor->id)->pluck('id'))
            ->orderBy('id')
            ->first();

        if ($de_gerente !== null || $gerente->superior === null) {
            return $de_gerente;
        }

        $da_supervisao = array_diff($gerente->superior->ids_sub_hierarquia(), [$vendedor->id]);

        return UsuariosConfiguracoes::whereIn('usuarios_id', $da_supervisao)->orderBy('id')->first();
    }
}
