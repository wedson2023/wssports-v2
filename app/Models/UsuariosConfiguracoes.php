<?php

namespace App\Models;

use App\Enums\PeriodoJogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações de um vendedor (o único que aposta). Sem tabela padrão: os valores iniciais
 * são os padrões das colunas.
 */
class UsuariosConfiguracoes extends Model
{
    use SoftDeletes;

    public const CAMPOS = [
        'esportes_permitidos',
        'apostar_outros_esportes',
        'ao_vivo_habilitado',
        'minuto_limite_ao_vivo',
        'cotacao_maxima_ao_vivo',
        ...self::CAMPOS_APOSTA,
        ...self::COMISSOES_PRE_JOGO,
        ...self::COMISSOES_AO_VIVO,
    ];

    /**
     * Regras de aposta do vendedor (spec 004).
     */
    public const CAMPOS_APOSTA = [
        'realizar_aposta',
        'cancelar_aposta',
        'tempo_cancelamento_aposta',
        'apostar_jogadores',
        'periodo_jogos',
        'data_travamento_sistema',
        'mensagem_bilhete',
        'delay_ao_vivo',
        'quantidade_minima_opcoes',
        'quantidade_maxima_opcoes',
        'valor_minimo_aposta',
        'valor_maximo_aposta',
        'odd_minima',
        'premio_maximo',
        'multiplicador',
        'ganho_multiplo_palpites',
        'comissao_por_premio',
        'limite_simples',
        'limite_duplo',
        'limite_geral',
    ];

    /**
     * Saldos disponíveis de venda: diminuem a cada aposta, por isso o vendedor novo nunca os copia
     * de um colega (nascem com o padrão da coluna).
     */
    public const LIMITES_VENDA = [
        'limite_simples',
        'limite_duplo',
        'limite_geral',
    ];

    /**
     * Comissão por quantidade de jogos no pré-jogo; a faixa 12 vale para 12 ou mais palpites.
     */
    public const COMISSOES_PRE_JOGO = [
        'comissao_pre_jogo_1', 'comissao_pre_jogo_2', 'comissao_pre_jogo_3', 'comissao_pre_jogo_4',
        'comissao_pre_jogo_5', 'comissao_pre_jogo_6', 'comissao_pre_jogo_7', 'comissao_pre_jogo_8',
        'comissao_pre_jogo_9', 'comissao_pre_jogo_10', 'comissao_pre_jogo_11', 'comissao_pre_jogo_12',
    ];

    /**
     * Comissão por quantidade de jogos nas apostas com ao vivo; a faixa 12 vale para 12 ou mais.
     */
    public const COMISSOES_AO_VIVO = [
        'comissao_ao_vivo_1', 'comissao_ao_vivo_2', 'comissao_ao_vivo_3', 'comissao_ao_vivo_4',
        'comissao_ao_vivo_5', 'comissao_ao_vivo_6', 'comissao_ao_vivo_7', 'comissao_ao_vivo_8',
        'comissao_ao_vivo_9', 'comissao_ao_vivo_10', 'comissao_ao_vivo_11', 'comissao_ao_vivo_12',
    ];

    protected $table = 'usuarios_configuracoes';

    /**
     * @var list<string>
     */
    protected $fillable = [...self::CAMPOS, 'usuarios_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'esportes_permitidos' => 'array',
            'apostar_outros_esportes' => 'boolean',
            'ao_vivo_habilitado' => 'boolean',
            'minuto_limite_ao_vivo' => 'integer',
            'cotacao_maxima_ao_vivo' => 'decimal:2',
            'realizar_aposta' => 'boolean',
            'cancelar_aposta' => 'boolean',
            'tempo_cancelamento_aposta' => 'integer',
            'apostar_jogadores' => 'boolean',
            'periodo_jogos' => PeriodoJogos::class,
            'data_travamento_sistema' => 'datetime',
            'delay_ao_vivo' => 'integer',
            'quantidade_minima_opcoes' => 'integer',
            'quantidade_maxima_opcoes' => 'integer',
            'valor_minimo_aposta' => 'decimal:2',
            'valor_maximo_aposta' => 'decimal:2',
            'odd_minima' => 'decimal:2',
            'premio_maximo' => 'decimal:2',
            'multiplicador' => 'integer',
            'ganho_multiplo_palpites' => 'decimal:2',
            'comissao_por_premio' => 'decimal:2',
            'limite_simples' => 'decimal:2',
            'limite_duplo' => 'decimal:2',
            'limite_geral' => 'decimal:2',
            ...array_fill_keys([...self::COMISSOES_PRE_JOGO, ...self::COMISSOES_AO_VIVO], 'decimal:2'),
        ];
    }

    /**
     * Percentual de comissão da faixa da quantidade de palpites (12 ou mais usa a faixa 12),
     * da série do pré-jogo ou do ao vivo.
     */
    public function percentual_comissao(int $quantidade_palpites, bool $ao_vivo): string
    {
        $faixa = max(1, min($quantidade_palpites, count(self::COMISSOES_PRE_JOGO)));
        $coluna = ($ao_vivo ? 'comissao_ao_vivo_' : 'comissao_pre_jogo_').$faixa;

        return (string) $this->{$coluna};
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    /**
     * Configuração do vendedor; se ainda não existir, é criada só com o usuário e relida, para
     * trazer os valores padrão das colunas (não existe tabela padrão).
     */
    public static function do_vendedor(int $usuarios_id): self
    {
        $configuracao = static::firstOrCreate(['usuarios_id' => $usuarios_id]);

        return $configuracao->wasRecentlyCreated ? $configuracao->refresh() : $configuracao;
    }

    /**
     * Esportes exibidos na listagem: só Futebol quando outros esportes estão desligados.
     *
     * @return list<string>
     */
    public function esportes_visiveis(): array
    {
        return $this->apostar_outros_esportes ? array_values($this->esportes_permitidos ?? []) : ['FUTEBOL'];
    }
}
