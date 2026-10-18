<?php

namespace App\Models;

use App\Enums\LiquidacaoCriarAposta;
use App\Enums\MeioDeposito;
use App\Enums\ModoSaque;
use App\Enums\TipoGanhoAfiliado;
use App\Services\ArmazenamentoImagens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações gerais do sistema (registro único; antiga "configs").
 */
class Configuracoes extends Model
{
    use SoftDeletes;

    /**
     * Texto padrão das regras da banca, um parágrafo por linha (o mesmo do sistema antigo). Fica
     * no código porque nem toda versão do MySQL aceita valor padrão em coluna text.
     */
    public const REGRAS_PADRAO = "Prazo de pagamento até 2 dias úteis.\n"
        ."Não pagará jogos já realizados ou que já estejam rolando e, por falha, continuem no sistema, por erro de hora, cotação ou por jogo antecipado.\n"
        .'Todos os jogos são definidos ao final dos 90 minutos de jogo, incluindo acréscimos definidos pelos árbitros. Não valerá prorrogação nem disputa de pênaltis.';

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
        // regra de comissão padrão do cadastro público de afiliados (spec 012)
        'afiliados_tipo_ganho_padrao',
        'afiliados_percentual_padrao',
    ];

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
            // afiliados (spec 012)
            'afiliados_tipo_ganho_padrao' => TipoGanhoAfiliado::class,
            'afiliados_percentual_padrao' => 'decimal:2',
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
     * Texto das regras da banca quebrado em parágrafos (uma linha cada), sem linhas vazias.
     *
     * @return list<string>
     */
    public function paragrafos_regras(): array
    {
        $linhas = array_map('trim', preg_split('/\R/u', (string) $this->regras));

        return array_values(array_filter($linhas, fn (string $linha) => $linha !== ''));
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
