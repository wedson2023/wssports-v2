<?php

namespace App\Services;

use App\Models\ApostasCombinacoesRisco;
use Illuminate\Support\Collection;

/**
 * Texto das mensagens de aviso ao Admin (spec 014, R-11); o envio passou para DisparoMensagens na
 * spec 025.
 */
class AvisosWhatsapp
{
    /**
     * Texto com todas as combinações novas do minuto (contracts/api.md §4).
     *
     * @param  Collection<int, ApostasCombinacoesRisco>  $combinacoes
     */
    public function texto_combinacoes(Collection $combinacoes): string
    {
        $linhas = ["⚠️ Combinação de risco nova ({$combinacoes->count()})"];

        foreach ($combinacoes->values() as $indice => $combinacao) {
            $itens = array_map(
                fn (string $jogo, string $opcao) => "{$jogo}: {$opcao}",
                $combinacao->jogos,
                $combinacao->opcoes,
            );

            $linhas[] = '';
            $linhas[] = ($indice + 1).') '.implode(' | ', $itens);
            $linhas[] = sprintf(
                '   %d bilhetes · apostado %s · prêmio %s',
                $combinacao->quantidade_bilhetes,
                $this->real($combinacao->valor_apostado),
                $this->real($combinacao->premio),
            );
        }

        return implode("\n", $linhas);
    }

    /**
     * "2400.00" → "R$ 2.400,00", sem ponto flutuante.
     */
    public function real(string $valor): string
    {
        [$inteiro, $centavos] = explode('.', bcadd($valor, '0', 2));

        return 'R$ '.number_format((int) $inteiro, 0, ',', '.').','.$centavos;
    }
}
