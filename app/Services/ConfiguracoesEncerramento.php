<?php

namespace App\Services;

use App\Models\Clientes;
use App\Models\ClientesConfiguracoes;
use App\Models\Configuracoes;

/**
 * Configurações do encerramento de aposta (spec 010, R-18): as gerais, lidas uma vez por
 * instância, e a permissão do cliente.
 */
class ConfiguracoesEncerramento
{
    private ?Configuracoes $configuracoes = null;

    public function habilitado(): bool
    {
        return (bool) $this->geral()->encerramento_habilitado;
    }

    public function parcial_habilitado(): bool
    {
        return (bool) $this->geral()->encerramento_parcial_habilitado;
    }

    public function automatico_habilitado(): bool
    {
        return (bool) $this->geral()->encerramento_automatico_habilitado;
    }

    /**
     * Encerrar antes de qualquer jogo da aposta começar.
     */
    public function jogos_nao_iniciados(): bool
    {
        return (bool) $this->geral()->encerramento_jogos_nao_iniciados;
    }

    /**
     * Margem da casa em %: a do ao vivo quando algum jogo em aberto já começou.
     */
    public function margem(bool $ao_vivo): string
    {
        return (string) ($ao_vivo ? $this->geral()->margem_encerramento_ao_vivo : $this->geral()->margem_encerramento_pre_jogo);
    }

    public function valor_minimo(): string
    {
        return (string) $this->geral()->valor_minimo_encerramento;
    }

    public function percentual_minimo(): string
    {
        return (string) $this->geral()->percentual_minimo_encerramento;
    }

    public function segundos_validade(): int
    {
        return (int) $this->geral()->segundos_validade_oferta;
    }

    public function limite_parciais(): int
    {
        return (int) $this->geral()->limite_encerramentos_parciais;
    }

    /**
     * Janela de segurança da apuração (spec 009): o valor encerrado é creditado depois dela.
     */
    public function minutos_janela(): int
    {
        return (int) $this->geral()->minutos_pagamento_premio;
    }

    /**
     * Encerramento ligado na configuração do cliente (criada com os padrões, como em
     * Apostador::cliente).
     */
    public function cliente_pode(Clientes $cliente): bool
    {
        $configuracao = ClientesConfiguracoes::firstOrCreate(['clientes_id' => $cliente->id]);

        if ($configuracao->wasRecentlyCreated) {
            $configuracao->refresh();
        }

        return (bool) $configuracao->encerrar_aposta;
    }

    private function geral(): Configuracoes
    {
        return $this->configuracoes ??= Configuracoes::atual();
    }
}
