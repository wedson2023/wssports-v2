<?php

namespace App\Models;

use App\Enums\SituacaoAvisoWhatsapp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Combinação de risco nova registrada pelo agendador (spec 014, R-11): origem dos avisos do painel e
 * do WhatsApp do Admin. Criada só pelo comando apostas:avisar_combinacoes_risco.
 */
class ApostasCombinacoesRisco extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_combinacoes_risco';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dia',
        'chave',
        'jogos',
        'opcoes',
        'codigos',
        'usuarios_ids',
        'quantidade_bilhetes',
        'valor_apostado',
        'premio',
        'situacao_whatsapp',
        'tentativas_whatsapp',
        'enviado_em',
        'erro_whatsapp',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia' => 'date',
            'jogos' => 'array',
            'opcoes' => 'array',
            'codigos' => 'array',
            'usuarios_ids' => 'array',
            'quantidade_bilhetes' => 'integer',
            'valor_apostado' => 'decimal:2',
            'premio' => 'decimal:2',
            'situacao_whatsapp' => SituacaoAvisoWhatsapp::class,
            'tentativas_whatsapp' => 'integer',
            'enviado_em' => 'datetime',
        ];
    }
}
