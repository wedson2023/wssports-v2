<?php

namespace App\Services;

use App\Exceptions\FalhaProvedorCassinoException;
use App\Models\CassinosJogos;
use App\Models\CassinosJogosCanais;
use App\Models\CassinosSessoes;
use App\Models\Clientes;
use App\Models\Configuracoes;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Abre os jogos do cassino (spec 011, FR-014 a FR-017, R-08, R-10, R-11): tenta os canais do jogo
 * pela prioridade e, quando o provedor recusa um canal, marca-o indisponível e passa ao próximo.
 */
class AberturaJogosCassino
{
    public function __construct(private ProvedorCassino $provedor) {}

    /**
     * Login do cliente no provedor: prefixo + id, estável e sem dados pessoais (R-12).
     */
    public static function login(Clientes $cliente): string
    {
        return config('services.provedor_cassino.prefixo_login').$cliente->id;
    }

    /**
     * Abre com dinheiro e registra a sessão de jogo.
     *
     * @return array{url: string, moldura: bool, sessao_id: int, jogo: array{id: int, nome: string}}
     *
     * @throws FalhaProvedorCassinoException provedor fora do ar ou erro que não é do canal
     * @throws HttpException 422 quando nenhum canal abre
     */
    public function abrir(Clientes $cliente, CassinosJogos $jogo): array
    {
        [$canal, $conteudo] = $this->abrir_por_algum_canal($jogo, self::login($cliente), false);

        $sessao = CassinosSessoes::create([
            'clientes_id' => $cliente->id,
            'cassinos_jogos_id' => $jogo->id,
            'cassinos_jogos_canais_id' => $canal->id,
            'codigo_provedor' => $canal->codigo_provedor,
            'sessao_provedor' => $this->sessao_provedor($conteudo),
            'bonus_primeiro' => $canal->bonus_primeiro,
            'aberta_em' => now(),
        ]);

        $jogo->increment('aberturas');

        return [...$this->resposta($jogo, $conteudo), 'sessao_id' => $sessao->id];
    }

    /**
     * Abre o demo, sem login do cliente, sem sessão e sem saldo (R-10).
     *
     * @return array{url: string, moldura: bool, sessao_id: null, jogo: array{id: int, nome: string}}
     */
    public function demo(CassinosJogos $jogo): array
    {
        abort_unless(
            Configuracoes::atual()->cassino_demo_habilitado && $jogo->canais()->abriveis()->where('demo', true)->exists(),
            422,
            'Demo indisponível para este jogo.',
        );

        [, $conteudo] = $this->abrir_por_algum_canal($jogo, config('services.provedor_cassino.prefixo_login').'demo', true);

        return [...$this->resposta($jogo, $conteudo), 'sessao_id' => null];
    }

    /**
     * @return array{0: CassinosJogosCanais, 1: array<string, mixed>}
     */
    private function abrir_por_algum_canal(CassinosJogos $jogo, string $login, bool $demo): array
    {
        $canais = $jogo->canais()->abriveis()->when($demo, fn ($c) => $c->where('demo', true))->porPrioridade()->get();

        foreach ($canais as $canal) {
            try {
                return [$canal, $this->provedor->abrir_jogo($login, $canal->codigo_provedor, $demo)];
            } catch (FalhaProvedorCassinoException $erro) {
                if (! $erro->recusou_canal()) {
                    throw $erro;
                }

                $this->marcar_indisponivel($canal, (string) $erro->codigo_provedor);
            }
        }

        throw new HttpException(422, 'Jogo indisponível no momento.');
    }

    /**
     * O provedor recusou o canal: fica indisponível até a próxima tentativa (R-08); crédito do hall
     * insuficiente indica canal pré-pago, que passa a "bônus primeiro" (R-06).
     */
    private function marcar_indisponivel(CassinosJogosCanais $canal, string $motivo): void
    {
        $canal->forceFill([
            'indisponivel_em' => now(),
            'motivo_indisponibilidade' => $motivo,
            ...($motivo === 'hall_balance_less_100' ? ['pre_pago' => true, 'bonus_primeiro' => true] : []),
        ])->save();

        ListagemCassino::limpar_cache();
    }

    /**
     * @param  array<string, mixed>  $conteudo
     * @return array{url: string, moldura: bool, jogo: array{id: int, nome: string}}
     */
    private function resposta(CassinosJogos $jogo, array $conteudo): array
    {
        $dados_jogo = $conteudo['game'] ?? [];

        return [
            'url' => (string) ($dados_jogo['url'] ?? ''),
            // withoutFrame = 1: o provedor não permite abrir dentro de moldura
            'moldura' => (string) ($dados_jogo['withoutFrame'] ?? '0') !== '1',
            'jogo' => ['id' => $jogo->id, 'nome' => $jogo->nome],
        ];
    }

    /**
     * @param  array<string, mixed>  $conteudo
     */
    private function sessao_provedor(array $conteudo): ?string
    {
        $sessao = trim((string) ($conteudo['gameRes']['sessionId'] ?? ''));

        if ($sessao === '' || CassinosSessoes::where('sessao_provedor', $sessao)->exists()) {
            return null;
        }

        return mb_substr($sessao, 0, 100);
    }
}
