<?php

namespace Database\Seeders;

use App\Enums\CategoriaPromocao;
use App\Enums\ModalidadePromocao;
use App\Enums\TipoGanho;
use App\Models\ClientesPromocoes;
use Illuminate\Database\Seeder;

/**
 * Uma promoção padrão de cada categoria (spec 006, FR-020 e FR-021), criada INATIVA: promoção
 * ativa e vigente é aplicada sozinha na ação do cliente, então só o administrador ativa, depois de
 * revisar os valores. Rodar de novo não duplica (procura pela categoria e pelo nome).
 */
class ClientesPromocoesSeeder extends Seeder
{
    public function run(): void
    {
        // regras de uso comuns às quatro (research.md, R-15)
        $comuns = [
            'modalidade' => ModalidadePromocao::Esportes,
            'valor_minimo_aposta' => '1.00',
            'valor_maximo_aposta' => '100.00',
            'odd_minima_aposta_simples' => '1.50',
            'odd_minima_aposta_multipla' => '1.30',
            'ativa' => false,
        ];

        $promocoes = [
            [
                'categoria' => CategoriaPromocao::PrimeiroCadastro,
                'nome' => 'Bônus de primeiro cadastro',
                'descricao' => 'Ganhe um bônus ao criar sua conta.',
                'tipo_ganho' => TipoGanho::Fixo,
                'valor' => '10.00',
                'rollover' => 10,
                'valor_maximo_deposito' => null,
                'valor_maximo_conversao' => '100.00',
            ],
            [
                'categoria' => CategoriaPromocao::PrimeiroDepósito,
                'nome' => 'Bônus de primeiro depósito',
                'descricao' => 'Ganhe um bônus no seu primeiro depósito.',
                'tipo_ganho' => TipoGanho::Percentual,
                'valor' => '100.00',
                'rollover' => 10,
                'valor_maximo_deposito' => '100.00',
                'valor_maximo_conversao' => '500.00',
            ],
            [
                'categoria' => CategoriaPromocao::QualquerDepósito,
                'nome' => 'Bônus em qualquer depósito',
                'descricao' => 'Ganhe um bônus a cada depósito.',
                'tipo_ganho' => TipoGanho::Percentual,
                'valor' => '10.00',
                'rollover' => 5,
                'valor_maximo_deposito' => '500.00',
                'valor_maximo_conversao' => '200.00',
            ],
            [
                'categoria' => CategoriaPromocao::Indicação,
                'nome' => 'Bônus por indicação',
                'descricao' => 'Ganhe um bônus ao indicar um amigo.',
                'tipo_ganho' => TipoGanho::Fixo,
                'valor' => '10.00',
                'rollover' => 10,
                'valor_maximo_deposito' => null,
                'valor_maximo_conversao' => '100.00',
            ],
        ];

        foreach ($promocoes as $promocao) {
            ClientesPromocoes::firstOrCreate(
                ['categoria' => $promocao['categoria'], 'nome' => $promocao['nome']],
                [...$comuns, ...$promocao, 'data_inicio' => now(), 'data_fim' => null],
            );
        }
    }
}
