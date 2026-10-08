<?php

namespace App\Support;

use App\Enums\Funcao;
use App\Models\Clientes;
use App\Models\ClientesConfiguracoes;
use App\Models\Configuracoes;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use App\Models\VisitantesConfiguracoes;
use App\Services\Publico;

/**
 * Quem está apostando (visitante, cliente ou vendedor) e as regras de aposta do seu público, lidas
 * só da configuração dele (FR-002).
 */
final readonly class Apostador
{
    private function __construct(
        public Publico $publico,
        public UsuariosConfiguracoes|ClientesConfiguracoes|VisitantesConfiguracoes $configuracao,
    ) {}

    public static function vendedor(Usuarios $usuario): self
    {
        return new self(
            new Publico(Publico::VENDEDOR, usuario: $usuario),
            UsuariosConfiguracoes::do_vendedor($usuario->id),
        );
    }

    public static function cliente(Clientes $cliente): self
    {
        $configuracao = ClientesConfiguracoes::firstOrCreate(['clientes_id' => $cliente->id]);

        return new self(
            new Publico(Publico::CLIENTE, cliente: $cliente),
            $configuracao->wasRecentlyCreated ? $configuracao->refresh() : $configuracao,
        );
    }

    public static function visitante(): self
    {
        return new self(Publico::visitante(), VisitantesConfiguracoes::atual());
    }

    public function e_vendedor(): bool
    {
        return $this->publico->e_vendedor();
    }

    public function e_cliente(): bool
    {
        return $this->publico->e_cliente();
    }

    public function e_visitante(): bool
    {
        return $this->publico->e_visitante();
    }

    public function usuario(): ?Usuarios
    {
        return $this->publico->usuario;
    }

    public function cliente_logado(): ?Clientes
    {
        return $this->publico->cliente;
    }

    /**
     * Login ativo (o visitante não tem login).
     */
    public function ativo(): bool
    {
        return match (true) {
            $this->e_vendedor() => $this->usuario()->pode_acessar() && $this->usuario()->funcao() === Funcao::Vendedor,
            $this->e_cliente() => $this->cliente_logado()->pode_acessar(),
            default => true,
        };
    }

    public function realizar_aposta(): bool
    {
        return $this->e_visitante() || (bool) $this->configuracao->realizar_aposta;
    }

    /**
     * Permissão de ao vivo do apostador (o visitante nunca aposta no ao vivo).
     */
    public function apostar_ao_vivo(): bool
    {
        return match (true) {
            $this->e_vendedor() => (bool) $this->configuracao->ao_vivo_habilitado,
            $this->e_cliente() => (bool) $this->configuracao->apostar_ao_vivo,
            default => false,
        };
    }

    public function quantidade_minima_opcoes(): int
    {
        return (int) $this->configuracao->quantidade_minima_opcoes;
    }

    public function quantidade_maxima_opcoes(): int
    {
        return (int) $this->configuracao->quantidade_maxima_opcoes;
    }

    public function valor_minimo_aposta(): string
    {
        return (string) $this->configuracao->valor_minimo_aposta;
    }

    public function valor_maximo_aposta(): string
    {
        return (string) $this->configuracao->valor_maximo_aposta;
    }

    public function odd_minima(): string
    {
        return (string) $this->configuracao->odd_minima;
    }

    /**
     * Cotação total máxima; só o cliente tem (spec 002).
     */
    public function odd_maxima(): ?string
    {
        return $this->e_cliente() ? (string) $this->configuracao->odd_maxima : null;
    }

    public function premio_maximo(): string
    {
        return (string) $this->configuracao->premio_maximo;
    }

    public function multiplicador(): int
    {
        return (int) $this->configuracao->multiplicador;
    }

    public function ganho_multiplo_palpites(): string
    {
        return (string) $this->configuracao->ganho_multiplo_palpites;
    }

    /**
     * Segundos de espera da aposta com ao vivo (o visitante não aposta no ao vivo).
     */
    public function delay_ao_vivo(): int
    {
        return $this->e_visitante() ? 0 : (int) $this->configuracao->delay_ao_vivo;
    }

    /**
     * Mensagem do bilhete: a do vendedor ou, sem vendedor, a do site.
     */
    public function mensagem_bilhete(): string
    {
        return $this->e_vendedor() ? (string) $this->configuracao->mensagem_bilhete : (string) Configuracoes::atual()->mensagem_bilhete;
    }

    /**
     * Mesmo apostador de uma aposta já gravada (idempotência).
     */
    public function e_dono(?int $usuarios_id, ?int $clientes_id): bool
    {
        return match (true) {
            $this->e_vendedor() => $usuarios_id === $this->usuario()->id && $clientes_id === null,
            $this->e_cliente() => $clientes_id === $this->cliente_logado()->id,
            default => $usuarios_id === null && $clientes_id === null,
        };
    }
}
