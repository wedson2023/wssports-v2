<?php

namespace App\Services;

use App\Models\ApostasCombinacoesRisco;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mensagens no WhatsApp pelo CallMeBot (spec 014, R-11), o mesmo serviço do aviso do deploy (spec
 * 013), com o telefone e a chave guardados nas configurações do sistema.
 */
class AvisosWhatsapp
{
    private const URL = 'https://api.callmebot.com/whatsapp.php';

    /**
     * Envia o texto; lança exceção quando o CallMeBot recusa ou não responde.
     */
    public function enviar(string $telefone, string $chave, string $texto): void
    {
        $resposta = Http::timeout(30)->get(self::URL, [
            'phone' => $telefone,
            'text' => $texto,
            'apikey' => $chave,
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException("O CallMeBot recusou a mensagem (HTTP {$resposta->status()}).");
        }
    }

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
    private function real(string $valor): string
    {
        [$inteiro, $centavos] = explode('.', bcadd($valor, '0', 2));

        return 'R$ '.number_format((int) $inteiro, 0, ',', '.').','.$centavos;
    }
}
