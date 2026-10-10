<?php

namespace App\Models;

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
        ];
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
