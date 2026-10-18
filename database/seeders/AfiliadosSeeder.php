<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Enums\OrigemCadastroAfiliado;
use App\Enums\SituacaoAfiliado;
use App\Enums\TipoChavePix;
use App\Enums\TipoGanhoAfiliado;
use App\Models\Afiliados;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissões de afiliados (spec 012) e afiliados de exemplo para a validação manual (quickstart).
 */
class AfiliadosSeeder extends Seeder
{
    /**
     * Senha dos afiliados de exemplo.
     */
    private const SENHA_EXEMPLO = 'afiliado123';

    public function run(): void
    {
        $this->permissoes();

        $gerente = Usuarios::role(Funcao::Gerente->value)->first();

        // ativo, com gerente e regra "Qualquer depósito" 10%
        $this->afiliado([
            'nome' => 'Afiliado Exemplo',
            'login' => 'afiliado.exemplo',
            'email' => 'afiliado.exemplo@exemplo.com',
            'cpf' => '52998224725',
            'telefone' => '81999990001',
            'usuarios_id' => $gerente?->id,
            'origem_cadastro' => OrigemCadastroAfiliado::Painel,
        ], SituacaoAfiliado::Ativo, TipoGanhoAfiliado::QualquerDepósito, '10.00');

        // cadastro público aguardando a banca
        $this->afiliado([
            'nome' => 'Afiliado Em Análise',
            'login' => 'afiliado.analise',
            'email' => 'afiliado.analise@exemplo.com',
            'cpf' => '11144477735',
            'telefone' => '81999990002',
            'origem_cadastro' => OrigemCadastroAfiliado::Público,
        ], SituacaoAfiliado::EmAnálise, TipoGanhoAfiliado::SaldoLíquido, '0.00');

        // código igual a um login antigo, para conferir os links ?user=LOGIN (SC-006)
        $this->afiliado([
            'nome' => 'Maria Souza',
            'login' => 'maria.souza',
            'email' => 'maria.souza@exemplo.com',
            'cpf' => '39053344705',
            'telefone' => '81999990003',
            'codigo' => 'MARIA.SOUZA',
            'origem_cadastro' => OrigemCadastroAfiliado::Painel,
        ], SituacaoAfiliado::Ativo, TipoGanhoAfiliado::SaldoLíquido, '30.00');
    }

    /**
     * Garante as permissões de afiliados e as distribui aos usuários conforme o padrão da função,
     * sem tirar nenhuma que eles já tenham (padrão do CassinoSeeder). O Gerente não recebe
     * afiliados.editar_comissao, que é liberada caso a caso.
     */
    private function permissoes(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $novas = Funcao::PERMISSOES_AFILIADOS;

        foreach ($novas as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) use ($novas) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], $novas);
            $usuario->givePermissionTo(array_values($permissoes));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cria o afiliado de exemplo (se ainda não existir) com a regra de comissão vigente e o Pix.
     *
     * @param  array<string, mixed>  $dados
     */
    private function afiliado(array $dados, SituacaoAfiliado $situacao, TipoGanhoAfiliado $tipo_ganho, string $percentual): void
    {
        if (Afiliados::withTrashed()->where('login', $dados['login'])->exists()) {
            return;
        }

        $afiliado = new Afiliados([
            ...$dados,
            'password' => self::SENHA_EXEMPLO,
            'codigo' => $dados['codigo'] ?? Afiliados::gerar_codigo(),
        ]);
        $afiliado->forceFill(['situacao' => $situacao])->save();

        $afiliado->comissoes()->create([
            'tipo_ganho' => $tipo_ganho,
            'percentual' => $percentual,
            'vigente_desde' => now(),
        ]);

        $afiliado->meio_pagamento()->create([
            'pix_tipo_chave' => TipoChavePix::Cpf,
            'pix_chave' => $dados['cpf'],
        ]);
    }
}
