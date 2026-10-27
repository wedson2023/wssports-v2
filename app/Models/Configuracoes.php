<?php

namespace App\Models;

use App\Enums\LiquidacaoCriarAposta;
use App\Enums\MeioDeposito;
use App\Enums\ModoSaque;
use App\Enums\SituacaoRoboWhatsapp;
use App\Services\ArmazenamentoImagens;
use App\Services\IconesAplicativo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações gerais do sistema (registro único; antiga "configs").
 */
class Configuracoes extends Model
{
    use SoftDeletes;

    /**
     * Texto padrão das regras da banca (o mesmo do sistema antigo), em parágrafos HTML desde a spec
     * 021. Fica no código porque nem toda versão do MySQL aceita valor padrão em coluna text.
     */
    public const REGRAS_PADRAO = '<p>Prazo de pagamento até 2 dias úteis.</p>'
        .'<p>Não pagará jogos já realizados ou que já estejam rolando e, por falha, continuem no sistema, por erro de hora, cotação ou por jogo antecipado.</p>'
        .'<p>Todos os jogos são definidos ao final dos 90 minutos de jogo, incluindo acréscimos definidos pelos árbitros. Não valerá prorrogação nem disputa de pênaltis.</p>';

    protected $table = 'configuracoes';

    /**
     * O registro criado depois da migration já nasce com o texto padrão das regras.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'regras' => self::REGRAS_PADRAO,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'somente_cassino',
        'permitir_entrada_campeonatos',
        'sorteio_ambas_marcam_minimo',
        'sorteio_ambas_marcam_maximo',
        'sorteio_ambas_nao_marcam_minimo',
        'sorteio_ambas_nao_marcam_maximo',
        'ao_vivo_habilitado',
        'segundos_trava_ao_vivo',
        'minutos_permanencia_ao_vivo',
        'minuto_limite_ao_vivo',
        'cotacao_maxima_ao_vivo',
        'ao_vivo_travado',
        'ao_vivo_travado_em',
        'nome_sistema',
        'mensagem_bilhete',
        'logo',
        'regras',
        // pagamentos (spec 007); percentual_comissao_gateway fica de fora: só alterada direto no
        // banco (FR-028)
        'gateway_pagamento',
        'depositos_habilitados',
        'saques_habilitados',
        'deposito_minimo',
        'deposito_maximo',
        'saque_minimo',
        'rollover_deposito',
        'rollover_deposito_vezes',
        'cashback_percentual',
        'modo_saque',
        'saque_automatico_maximo',
        'meios_deposito',
        'parcelas_maximas',
        'minutos_expiracao_pix',
        'minutos_acesso_assistido',
        // Criar Aposta (spec 008)
        'criar_aposta_habilitado',
        'desconto_criar_aposta',
        'quantidade_minima_criar_aposta',
        'quantidade_maxima_criar_aposta',
        'liquidacao_criar_aposta',
        // apuração (spec 009)
        'remover_confronto_cancelado',
        'minutos_pagamento_premio',
        // encerramento de aposta (spec 010)
        'encerramento_habilitado',
        'encerramento_parcial_habilitado',
        'encerramento_automatico_habilitado',
        'encerramento_jogos_nao_iniciados',
        'margem_encerramento_pre_jogo',
        'margem_encerramento_ao_vivo',
        'valor_minimo_encerramento',
        'percentual_minimo_encerramento',
        'segundos_validade_oferta',
        'limite_encerramentos_parciais',
        // cassino (spec 011)
        'cassino_habilitado',
        'cassino_demo_habilitado',
        'data_prestacao_contas_cassino',
        'dias_registro_chamadas_cassino',
        // aviso de combinação de risco nova no WhatsApp do Admin (spec 014)
        'aviso_combinacao_whatsapp',
        'telefone_whatsapp_admin',
        'chave_callmebot',
        // limite diário das notificações push manuais (spec 019)
        'limite_notificacoes_manuais_dia',
        // dados da banca, contatos, tema e GTM (spec 021); pasta_icones e robo_whatsapp_* ficam de
        // fora: só os services as gravam
        'whatsapp',
        'mensagem_whatsapp',
        'botao_whatsapp',
        'instagram',
        'facebook',
        'twitter',
        'youtube',
        'jogo_responsavel',
        'cor_tema',
        'cor_fundo',
        'gtm_id',
    ];

    /**
     * Cores principais do tema (as 6 do sistema antigo), com o nome e a cor escura derivada de
     * cada uma (spec 021, research R-04).
     *
     * @var array<string, array{nome: string, escura: string}>
     */
    public const CORES_TEMA = [
        '#c40808' => ['nome' => 'Vermelho', 'escura' => '#a41f1a'],
        '#d0af01' => ['nome' => 'Amarelo', 'escura' => '#9f8601'],
        '#008000' => ['nome' => 'Verde', 'escura' => '#005400'],
        '#006eb1' => ['nome' => 'Azul', 'escura' => '#024b77'],
        '#fe6a00' => ['nome' => 'Laranja', 'escura' => '#b94e02'],
        '#b91552' => ['nome' => 'Rosa', 'escura' => '#930137'],
    ];

    /**
     * Cores de fundo padrão do site: escuro ou claro.
     *
     * @var list<string>
     */
    public const CORES_FUNDO = ['#000000', '#FFFFFF'];

    /**
     * A chave do CallMeBot nunca sai nas respostas (spec 014, FR-048).
     *
     * @var list<string>
     */
    protected $hidden = ['chave_callmebot'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'somente_cassino' => 'boolean',
            'permitir_entrada_campeonatos' => 'boolean',
            'sorteio_ambas_marcam_minimo' => 'decimal:2',
            'sorteio_ambas_marcam_maximo' => 'decimal:2',
            'sorteio_ambas_nao_marcam_minimo' => 'decimal:2',
            'sorteio_ambas_nao_marcam_maximo' => 'decimal:2',
            'ao_vivo_habilitado' => 'boolean',
            'segundos_trava_ao_vivo' => 'integer',
            'minutos_permanencia_ao_vivo' => 'integer',
            'minuto_limite_ao_vivo' => 'integer',
            'cotacao_maxima_ao_vivo' => 'decimal:2',
            'ao_vivo_travado' => 'boolean',
            'ao_vivo_travado_em' => 'datetime',
            'percentual_comissao_gateway' => 'decimal:2',
            'depositos_habilitados' => 'boolean',
            'saques_habilitados' => 'boolean',
            'deposito_minimo' => 'decimal:2',
            'deposito_maximo' => 'decimal:2',
            'saque_minimo' => 'decimal:2',
            'rollover_deposito' => 'boolean',
            'rollover_deposito_vezes' => 'integer',
            'cashback_percentual' => 'decimal:2',
            'modo_saque' => ModoSaque::class,
            'saque_automatico_maximo' => 'decimal:2',
            'meios_deposito' => 'array',
            'parcelas_maximas' => 'integer',
            'minutos_expiracao_pix' => 'integer',
            'minutos_acesso_assistido' => 'integer',
            // Criar Aposta (spec 008)
            'criar_aposta_habilitado' => 'boolean',
            'desconto_criar_aposta' => 'decimal:2',
            'quantidade_minima_criar_aposta' => 'integer',
            'quantidade_maxima_criar_aposta' => 'integer',
            'liquidacao_criar_aposta' => LiquidacaoCriarAposta::class,
            // apuração (spec 009)
            'remover_confronto_cancelado' => 'boolean',
            'minutos_pagamento_premio' => 'integer',
            // encerramento de aposta (spec 010)
            'encerramento_habilitado' => 'boolean',
            'encerramento_parcial_habilitado' => 'boolean',
            'encerramento_automatico_habilitado' => 'boolean',
            'encerramento_jogos_nao_iniciados' => 'boolean',
            'margem_encerramento_pre_jogo' => 'decimal:2',
            'margem_encerramento_ao_vivo' => 'decimal:2',
            'valor_minimo_encerramento' => 'decimal:2',
            'percentual_minimo_encerramento' => 'decimal:2',
            'segundos_validade_oferta' => 'integer',
            'limite_encerramentos_parciais' => 'integer',
            // cassino (spec 011)
            'cassino_habilitado' => 'boolean',
            'cassino_demo_habilitado' => 'boolean',
            'data_prestacao_contas_cassino' => 'date',
            'dias_registro_chamadas_cassino' => 'integer',
            // aviso de combinação de risco (spec 014)
            'aviso_combinacao_whatsapp' => 'boolean',
            'chave_callmebot' => 'encrypted',
            // notificações manuais (spec 019)
            'limite_notificacoes_manuais_dia' => 'integer',
            // contatos e robô do WhatsApp (spec 021)
            'botao_whatsapp' => 'boolean',
            'robo_whatsapp_situacao' => SituacaoRoboWhatsapp::class,
            'robo_whatsapp_atualizado_em' => 'datetime',
        ];
    }

    /**
     * Cores do tema no formato das props do site (temas, letter e cor_fundo) e a logo.
     *
     * @return array<string, mixed>
     */
    public function tema(): array
    {
        $cor = isset(self::CORES_TEMA[$this->cor_tema]) ? $this->cor_tema : array_key_first(self::CORES_TEMA);

        return [
            'temas' => $cor,
            'letter' => self::CORES_TEMA[$cor]['escura'],
            'cor_fundo' => $this->cor_fundo ?: self::CORES_FUNDO[0],
            'logo' => $this->url_logo(),
            'icones' => $this->icones(),
        ];
    }

    /**
     * Ícones do aplicativo gerados da logo (spec 021, research R-05): favicon, ícones da Apple e a
     * lista do manifest; sem logo enviada, os ícones padrão do projeto.
     *
     * @return array{favicon: string, apple: array<int, string>, manifest: list<array{src: string, sizes: string, type: string}>}
     */
    public function icones(): array
    {
        $apple = [];

        foreach (IconesAplicativo::TAMANHOS_APPLE as $tamanho) {
            $apple[$tamanho] = $this->url_icone_apple($tamanho);
        }

        return [
            'favicon' => $this->pasta_icones ? $this->url_icone(32) : '/images/icones/icon-favicon.png',
            'apple' => $apple,
            'manifest' => array_map(fn (int $tamanho) => [
                'src' => $this->url_icone($tamanho),
                'sizes' => "{$tamanho}x{$tamanho}",
                'type' => 'image/png',
            ], IconesAplicativo::TAMANHOS),
        ];
    }

    /**
     * Endereço de um ícone do manifest (32 a 512).
     */
    public function url_icone(int $tamanho): string
    {
        return $this->pasta_icones
            ? ArmazenamentoImagens::url("{$this->pasta_icones}/icon-{$tamanho}.png")
            : "/images/icones/icon-{$tamanho}.png";
    }

    /**
     * Endereço de um ícone da Apple; os padrão mantêm os nomes do sistema antigo.
     */
    private function url_icone_apple(int $tamanho): string
    {
        if ($this->pasta_icones) {
            return ArmazenamentoImagens::url("{$this->pasta_icones}/touch-icon-{$tamanho}.png");
        }

        $padrao = [152 => 'touch-icon-ipad', 167 => 'touch-icon-ipad-retina', 180 => 'touch-icon-iphone-retina'];

        return "/images/icones/{$padrao[$tamanho]}.png";
    }

    /**
     * Telefone do WhatsApp e redes sociais da banca (null = não aparece no site).
     *
     * @return array<string, mixed>
     */
    public function contatos(): array
    {
        return [
            'whatsapp' => $this->whatsapp,
            'mensagem_whatsapp' => (string) $this->mensagem_whatsapp,
            'botao_whatsapp' => (bool) $this->botao_whatsapp,
            'instagram' => $this->instagram,
            'youtube' => $this->youtube,
            'twitter' => $this->twitter,
            'facebook' => $this->facebook,
            'jogo_responsavel' => $this->jogo_responsavel,
        ];
    }

    /**
     * Meios de depósito oferecidos ao cliente; sem configuração, Pix e cartão.
     *
     * @return list<string>
     */
    public function meios_habilitados(): array
    {
        return $this->meios_deposito ?? [MeioDeposito::Pix->value, MeioDeposito::CartãoCrédito->value];
    }

    public static function atual(): self
    {
        return static::query()->firstOrFail();
    }

    /**
     * Endereço da logo da banca; sem logo enviada, a logo padrão do projeto.
     */
    public function url_logo(): string
    {
        return $this->logo ? ArmazenamentoImagens::url($this->logo) : '/images/logo_padrao.png';
    }

    /**
     * Intervalo de sorteio (mínimo e máximo) de odd4 ou odd7; null quando o código não tem
     * sorteio ou o intervalo é inválido (mínimo maior que o máximo).
     *
     * @return array{0: float, 1: float}|null
     */
    public function intervalo_sorteio(string $codigo): ?array
    {
        [$minimo, $maximo] = match ($codigo) {
            'odd4' => [$this->sorteio_ambas_marcam_minimo, $this->sorteio_ambas_marcam_maximo],
            'odd7' => [$this->sorteio_ambas_nao_marcam_minimo, $this->sorteio_ambas_nao_marcam_maximo],
            default => [null, null],
        };

        if ($minimo === null || $maximo === null || (float) $minimo <= 0 || (float) $minimo > (float) $maximo) {
            return null;
        }

        return [(float) $minimo, (float) $maximo];
    }
}
