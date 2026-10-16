<?php

namespace App\Services;

use App\Enums\AparelhoCassino;
use App\Enums\DestaqueProvedorCassino;
use App\Enums\OrigemSincronizacaoCassino;
use App\Enums\SituacaoSincronizacaoCassino;
use App\Exceptions\FalhaProvedorCassinoException;
use App\Models\CassinosJogos;
use App\Models\CassinosJogosCanais;
use App\Models\CassinosProvedores;
use App\Models\CassinosSincronizacoes;
use App\Models\Usuarios;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carga do catálogo do provedor do cassino (spec 011, R-02 a R-06). O jogo é único por provedor real
 * (title) e nome; cada canal (label) é uma opção do jogo. Jogo recadastrado com ID novo atualiza o
 * canal; o que sai da lista é desativado sem perder histórico; resposta vazia ou com erro não
 * desativa nada.
 */
class SincronizacaoCassino
{
    /**
     * Minutos depois dos quais uma carga "Em andamento" é considerada abandonada.
     */
    private const MINUTOS_CARGA_PRESA = 30;

    /**
     * Contadores e falhas da carga em andamento.
     *
     * @var array<string, int>
     */
    private array $contadores = [];

    /**
     * @var list<array{item: string, motivo: string}>
     */
    private array $falhas = [];

    public function __construct(private ProvedorCassino $provedor) {}

    /**
     * Executa uma carga; devolve null quando já existe outra em andamento.
     */
    public function sincronizar(OrigemSincronizacaoCassino $origem, ?Usuarios $usuario = null): ?CassinosSincronizacoes
    {
        $em_andamento = CassinosSincronizacoes::where('situacao', SituacaoSincronizacaoCassino::EmAndamento)
            ->where('iniciada_em', '>', now()->subMinutes(self::MINUTOS_CARGA_PRESA))
            ->exists();

        if ($em_andamento) {
            return null;
        }

        $sincronizacao = CassinosSincronizacoes::create([
            'origem' => $origem,
            'usuarios_id' => $usuario?->id,
            'situacao' => SituacaoSincronizacaoCassino::EmAndamento,
            'iniciada_em' => now(),
        ]);

        try {
            $lista = $this->provedor->listar_jogos()['gameList'] ?? null;
        } catch (FalhaProvedorCassinoException $erro) {
            return $this->falhar($sincronizacao, 'Erro do provedor: '.($erro->codigo_provedor ?? 'sem resposta ou tempo esgotado').'.');
        }

        if (! is_array($lista) || $lista === []) {
            return $this->falhar($sincronizacao, 'O provedor não devolveu nenhum jogo.');
        }

        $this->contadores = ['recebidos' => count($lista), 'jogos_novos' => 0, 'canais_novos' => 0, 'alterados' => 0, 'desativados' => 0, 'reativados' => 0];
        $this->falhas = [];

        try {
            DB::transaction(fn () => $this->aplicar($lista));
        } catch (Throwable $erro) {
            Log::error('Cassino: falha ao gravar o catálogo ('.class_basename($erro).': '.$erro->getMessage().').');

            return $this->falhar($sincronizacao, 'Falha ao gravar o catálogo.');
        }

        $sincronizacao->forceFill([
            ...$this->contadores,
            'situacao' => SituacaoSincronizacaoCassino::Concluída,
            'falhas' => $this->falhas ?: null,
            'concluida_em' => now(),
        ])->save();

        ListagemCassino::limpar_cache();

        return $sincronizacao;
    }

    /**
     * Grava provedores, jogos e canais da lista recebida e desativa o que não veio.
     *
     * @param  list<array<string, mixed>>  $lista
     */
    private function aplicar(array $lista): void
    {
        $provedores = CassinosProvedores::all()->keyBy('codigo');
        $jogos = CassinosJogos::all()->keyBy(fn (CassinosJogos $jogo) => $jogo->cassinos_provedores_id.'|'.$jogo->nome_normalizado);
        $canais = CassinosJogosCanais::all()->keyBy(fn (CassinosJogosCanais $canal) => $canal->cassinos_jogos_id.'|'.$canal->canal);

        $ids_jogos_vistos = [];
        $ids_canais_vistos = [];

        foreach ($this->agrupar_por_jogo($lista) as $itens) {
            $principal = $itens[0];
            $provedor = $this->provedor_do_item($provedores, $principal['title']);
            $jogo = $this->gravar_jogo($jogos, $provedor, $principal);
            $ids_jogos_vistos[] = $jogo->id;

            foreach ($itens as $item) {
                $ids_canais_vistos[] = $this->gravar_canal($canais, $jogo, $item)->id;
            }
        }

        $this->contadores['desativados'] += CassinosJogos::whereNull('removido_em')->whereNotIn('id', $ids_jogos_vistos)->update(['removido_em' => now()]);
        $this->contadores['desativados'] += CassinosJogosCanais::whereNull('removido_em')->whereNotIn('id', $ids_canais_vistos)->update(['removido_em' => now()]);

        // recusas antigas do provedor voltam a ser tentadas (R-08)
        CassinosJogosCanais::where('indisponivel_em', '<', now()->subHours((int) config('cassino.horas_indisponibilidade_canal')))
            ->update(['indisponivel_em' => null, 'motivo_indisponibilidade' => null]);
    }

    /**
     * Valida os itens, resolve o mesmo nome e canal com dois IDs (fica o maior) e agrupa os canais de
     * cada jogo, do de maior prioridade para o de menor.
     *
     * @param  list<array<string, mixed>>  $lista
     * @return Collection<string, list<array<string, mixed>>>
     */
    private function agrupar_por_jogo(array $lista): Collection
    {
        $por_canal = [];

        foreach ($lista as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            $nome = trim((string) ($item['name'] ?? ''));
            $canal = trim((string) ($item['label'] ?? ''));
            $titulo = trim((string) ($item['title'] ?? ''));

            if ($id === '' || $nome === '' || $canal === '' || $titulo === '') {
                $this->falhas[] = ['item' => $id ?: $nome ?: '?', 'motivo' => 'Item sem id, nome, canal ou provedor.'];

                continue;
            }

            $chave = CassinosProvedores::normalizar_codigo($titulo).'|'.CassinosJogos::normalizar_nome($nome).'|'.$canal;
            $item = [...$item, 'id' => $id, 'name' => $nome, 'label' => $canal, 'title' => $titulo];

            if (isset($por_canal[$chave])) {
                [$fica, $sai] = (int) $id > (int) $por_canal[$chave]['id'] ? [$item, $por_canal[$chave]] : [$por_canal[$chave], $item];
                $this->falhas[] = ['item' => $sai['id'], 'motivo' => "ID duplicado para {$nome} no canal {$canal}; ficou o {$fica['id']}."];
                $por_canal[$chave] = $fica;

                continue;
            }

            $por_canal[$chave] = $item;
        }

        return collect($por_canal)
            ->groupBy(fn (array $item) => CassinosProvedores::normalizar_codigo($item['title']).'|'.CassinosJogos::normalizar_nome($item['name']))
            ->map(fn (Collection $itens) => $itens->sortBy(fn (array $item) => $this->prioridade_do_canal($item['label']))->values()->all());
    }

    /**
     * @param  Collection<string, CassinosProvedores>  $provedores
     */
    private function provedor_do_item(Collection $provedores, string $titulo): CassinosProvedores
    {
        $codigo = CassinosProvedores::normalizar_codigo($titulo);
        $provedor = $provedores->get($codigo);

        if ($provedor === null) {
            // provedor novo entra desativado: o administrador escolhe o que aparece (R-02)
            $provedor = CassinosProvedores::create([
                'codigo' => $codigo,
                'nome' => $titulo,
                'ativo' => false,
                'ordem' => 100,
                'titulos_provedor' => [$titulo],
            ]);
            $provedores->put($codigo, $provedor);

            return $provedor;
        }

        if (! in_array($titulo, $provedor->titulos_provedor ?? [], true)) {
            $provedor->titulos_provedor = [...($provedor->titulos_provedor ?? []), $titulo];
            $provedor->save();
        }

        return $provedor;
    }

    /**
     * Cria ou atualiza o jogo com os dados do canal de maior prioridade.
     *
     * @param  Collection<string, CassinosJogos>  $jogos
     * @param  array<string, mixed>  $item
     */
    private function gravar_jogo(Collection $jogos, CassinosProvedores $provedor, array $item): CassinosJogos
    {
        $nome_normalizado = CassinosJogos::normalizar_nome($item['name']);
        $chave = $provedor->id.'|'.$nome_normalizado;
        $dados = [
            'nome' => mb_substr($item['name'], 0, 150),
            'tipo' => mb_substr(trim((string) ($item['categories'] ?? '')) ?: 'outros', 0, 30),
            'imagem' => ($item['img'] ?? null) ? mb_substr((string) $item['img'], 0, 500) : null,
            'aparelhos' => AparelhoCassino::do_provedor((string) ($item['device'] ?? '2')),
            'destaque_provedor' => DestaqueProvedorCassino::do_provedor($item['menu'] ?? null),
        ];

        $jogo = $jogos->get($chave);

        if ($jogo === null) {
            $jogo = CassinosJogos::create([
                ...$dados,
                'cassinos_provedores_id' => $provedor->id,
                'nome_normalizado' => mb_substr($nome_normalizado, 0, 150),
            ]);
            $jogos->put($chave, $jogo);
            $this->contadores['jogos_novos']++;

            return $jogo;
        }

        $jogo->fill($dados);

        if ($jogo->removido_em !== null) {
            $jogo->removido_em = null;
            $this->contadores['reativados']++;
        } elseif ($jogo->isDirty()) {
            $this->contadores['alterados']++;
        }

        $jogo->save();

        return $jogo;
    }

    /**
     * Cria o canal (com pré-pago, "bônus primeiro", ativo e prioridade pelo sufixo) ou atualiza o
     * código do provedor e os demais dados; o que o administrador definiu (ativo, prioridade,
     * pré-pago, "bônus primeiro") não é sobrescrito.
     *
     * @param  Collection<string, CassinosJogosCanais>  $canais
     * @param  array<string, mixed>  $item
     */
    private function gravar_canal(Collection $canais, CassinosJogos $jogo, array $item): CassinosJogosCanais
    {
        $chave = $jogo->id.'|'.$item['label'];
        $dados = [
            'codigo_provedor' => mb_substr($item['id'], 0, 30),
            'demo' => (string) ($item['demo'] ?? '0') === '1',
            'flash' => (string) ($item['flash'] ?? '0') === '1',
            'dados_provedor' => array_intersect_key($item, array_flip(['vertical', 'rewriterule', 'exitButton', 'disableReload', 'system_name2', 'lines', 'bm'])),
        ];

        $canal = $canais->get($chave);

        if ($canal === null) {
            $pre_pago = $this->canal_pre_pago($item['label']);
            $canal = CassinosJogosCanais::create([
                ...$dados,
                'cassinos_jogos_id' => $jogo->id,
                'canal' => mb_substr($item['label'], 0, 60),
                'pre_pago' => $pre_pago,
                'bonus_primeiro' => $pre_pago,
                'ativo' => ! $pre_pago,
                'prioridade' => $this->prioridade_do_canal($item['label']),
            ]);
            $canais->put($chave, $canal);
            $this->contadores['canais_novos']++;

            return $canal;
        }

        $canal->fill($dados);

        if ($canal->removido_em !== null) {
            $canal->removido_em = null;
            $this->contadores['reativados']++;
        } elseif ($canal->isDirty('codigo_provedor', 'demo', 'flash')) {
            $this->contadores['alterados']++;
        }

        $canal->save();

        return $canal;
    }

    /**
     * Canal pré-pago: algum trecho do nome está na lista de sufixos (ex.: spribe_sr, 3oaks_sz_kn).
     */
    private function canal_pre_pago(string $canal): bool
    {
        $trechos = array_map(fn (string $trecho) => '_'.$trecho, array_slice(explode('_', $canal), 1));

        return array_intersect($trechos, config('cassino.sufixos_pre_pagos')) !== [];
    }

    private function prioridade_do_canal(string $canal): int
    {
        if ($this->canal_pre_pago($canal)) {
            return (int) config('cassino.prioridade_pre_pago');
        }

        $sufixo = str_contains($canal, '_') ? '_'.substr(strrchr($canal, '_'), 1) : '';

        return (int) (config('cassino.prioridade_sufixos')[$sufixo] ?? config('cassino.prioridade_padrao'));
    }

    private function falhar(CassinosSincronizacoes $sincronizacao, string $erro): CassinosSincronizacoes
    {
        $sincronizacao->forceFill([
            'situacao' => SituacaoSincronizacaoCassino::Falha,
            'erro' => $erro,
            'concluida_em' => now(),
        ])->save();

        return $sincronizacao;
    }
}
