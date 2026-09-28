<?php

namespace App\Enums;

/**
 * Funções da hierarquia de usuários, do nível mais alto (Admin) ao mais baixo (Vendedor).
 * O valor de cada caso é também o nome do papel (role) no spatie/laravel-permission.
 */
enum Funcao: string
{
    case Admin = 'Admin';
    case Supervisor = 'Supervisor';
    case Gerente = 'Gerente';
    case Vendedor = 'Vendedor';

    /**
     * Todas as permissões de gestão de usuários existentes no sistema.
     */
    public const PERMISSOES_GESTAO = [
        'usuarios.listar',
        'usuarios.consultar',
        'usuarios.cadastrar',
        'usuarios.editar',
        'usuarios.alterar_situacao',
        'usuarios.excluir',
        'usuarios.gerenciar_permissoes',
    ];

    /**
     * Nível hierárquico: 1 é o topo (Admin) e 4 a base (Vendedor).
     */
    public function nivel(): int
    {
        return match ($this) {
            self::Admin => 1,
            self::Supervisor => 2,
            self::Gerente => 3,
            self::Vendedor => 4,
        };
    }

    /**
     * Função que este nível pode cadastrar; null quando não possui subordinados.
     */
    public function funcao_abaixo(): ?self
    {
        return match ($this) {
            self::Admin => self::Supervisor,
            self::Supervisor => self::Gerente,
            self::Gerente => self::Vendedor,
            self::Vendedor => null,
        };
    }

    /**
     * Função exigida do superior; null para o Admin, que fica no topo.
     */
    public function funcao_acima(): ?self
    {
        return match ($this) {
            self::Admin => null,
            self::Supervisor => self::Admin,
            self::Gerente => self::Supervisor,
            self::Vendedor => self::Gerente,
        };
    }

    /**
     * Permissões que o usuário desta função recebe ao ser cadastrado.
     *
     * @return list<string>
     */
    public function permissoes_padrao(): array
    {
        return $this === self::Vendedor ? [] : self::PERMISSOES_GESTAO;
    }
}
