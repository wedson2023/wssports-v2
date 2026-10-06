<?php

namespace App\Services;

use App\Models\Clientes;
use App\Models\Usuarios;

/**
 * Quem está vendo a listagem pública: visitante, cliente logado, vendedor ou gestor (Gerente,
 * Supervisor ou Admin) logado no painel.
 */
final readonly class Publico
{
    public const VISITANTE = 'visitante';

    public const CLIENTE = 'cliente';

    public const VENDEDOR = 'vendedor';

    public const GESTOR = 'gestor';

    public function __construct(
        public string $tipo,
        public ?Clientes $cliente = null,
        public ?Usuarios $usuario = null,
        public bool $token_recusado = false,
    ) {}

    public static function visitante(bool $token_recusado = false): self
    {
        return new self(self::VISITANTE, token_recusado: $token_recusado);
    }

    public function e_visitante(): bool
    {
        return $this->tipo === self::VISITANTE;
    }

    public function e_cliente(): bool
    {
        return $this->tipo === self::CLIENTE;
    }

    public function e_vendedor(): bool
    {
        return $this->tipo === self::VENDEDOR;
    }

    public function e_gestor(): bool
    {
        return $this->tipo === self::GESTOR;
    }

    /**
     * Visitante ou cliente: o público do site.
     */
    public function e_site(): bool
    {
        return $this->e_visitante() || $this->e_cliente();
    }

    /**
     * Ids do usuário do painel e de todos os superiores (vazio para o site).
     *
     * @return list<int>
     */
    public function ids_hierarquia_acima(): array
    {
        return $this->usuario?->ids_hierarquia_acima() ?? [];
    }
}
