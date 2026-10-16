<?php

namespace Database\Seeders;

use App\Enums\EfeitoAcaoCassino;
use App\Enums\Funcao;
use App\Models\CassinosAcoes;
use App\Models\CassinosProvedores;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cassino (spec 011): permissões do painel, os provedores do sistema antigo (ativos, com logo e
 * porcentagem de comissão) e os tipos de ação do provedor. Pode rodar mais de uma vez: não desfaz o
 * que o administrador alterou nos provedores e nas ações já existentes.
 */
class CassinoSeeder extends Seeder
{
    /**
     * Provedores do sistema antigo, pelo código normalizado do "title" (R-02): nome, ordem e
     * porcentagem de comissão (Clarifications da spec).
     */
    private const PROVEDORES = [
        'pgsoft' => ['PG Soft', 1, '15.50', true],
        'spribe' => ['Spribe', 2, '14.00', true],
        'evolution' => ['Evolution', 3, '18.50', true],
        'pragmatic' => ['Pragmatic', 4, '12.00', true],
        'galaxsys' => ['Galaxsys', 5, '0.00', true],
        'novomatic' => ['Novomatic', 6, '14.00', true],
        'redtiger' => ['Red Tiger', 7, '17.50', true],
        'amatic' => ['Amatic', 8, '12.00', true],
        'booming' => ['Booming', 9, '17.50', true],
        'netent' => ['NetEnt', 10, '12.00', true],
        // o Aviator vem no title "fast_games" (canal aviator_op)
        'fastgames' => ['Aviator', 11, '14.00', false],
    ];

    /**
     * Ações conhecidas do lançamento de rodada (R-16).
     */
    private const ACOES = [
        'BET' => EfeitoAcaoCassino::Aposta,
        'SPIN' => EfeitoAcaoCassino::Aposta,
        'RESPIN' => EfeitoAcaoCassino::Aposta,
        'BET_AND_WINS' => EfeitoAcaoCassino::ApostaComPrêmio,
        'WIN' => EfeitoAcaoCassino::Prêmio,
        'FREESPIN' => EfeitoAcaoCassino::RodadaGrátis,
        'FREERESPIN' => EfeitoAcaoCassino::RodadaGrátis,
        'CANCEL' => EfeitoAcaoCassino::Cancelamento,
        'REFUND' => EfeitoAcaoCassino::Reembolso,
    ];

    public function run(): void
    {
        $this->permissoes();

        foreach (self::PROVEDORES as $codigo => [$nome, $ordem, $porcentagem, $tem_logo]) {
            CassinosProvedores::firstOrCreate(['codigo' => $codigo], [
                'nome' => $nome,
                'imagem' => $tem_logo ? "/images/cassino/provedores/{$codigo}.png" : null,
                'ativo' => true,
                'ordem' => $ordem,
                'porcentagem_comissao' => $porcentagem,
                'titulos_provedor' => [],
            ]);
        }

        foreach (self::ACOES as $acao => $efeito) {
            CassinosAcoes::firstOrCreate(['acao' => $acao], ['efeito' => $efeito, 'ativo' => true]);
        }
    }

    /**
     * Garante as permissões do cassino e as distribui aos usuários conforme o padrão da função, sem
     * tirar nenhuma que eles já tenham (padrão do EncerramentoSeeder).
     */
    private function permissoes(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $novas = Funcao::PERMISSOES_CASSINO;

        foreach ($novas as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) use ($novas) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], $novas);
            $usuario->givePermissionTo(array_values($permissoes));
        });
    }
}
