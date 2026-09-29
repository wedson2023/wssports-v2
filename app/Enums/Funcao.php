<?php

namespace App\Enums;

use App\Models\Usuarios;

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
     * Todas as permissões de gestão de clientes e promoções existentes no sistema.
     */
    public const PERMISSOES_CLIENTES = [
        'clientes.listar',
        'clientes.ver_dados_completos',
        'clientes.editar',
        'clientes.editar_configuracoes',
        'clientes.movimentar_saldo',
        'clientes.excluir',
        'clientes.restaurar',
        'clientes.editar_configuracoes_padrao',
        'clientes_promocoes.gerenciar',
        'clientes_promocoes.estornar',
    ];

    /**
     * Permissões de clientes que só Admin e Supervisor podem usar, mesmo que outra função as receba.
     */
    public const PERMISSOES_CLIENTES_RESTRITAS = [
        'clientes.excluir',
        'clientes.restaurar',
        'clientes.editar_configuracoes_padrao',
        'clientes_promocoes.estornar',
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
        if ($this === self::Vendedor) {
            return [];
        }

        $permissoes_clientes = array_filter(self::PERMISSOES_CLIENTES, fn (string $permissao) => $this->pode_usar($permissao));

        return [...self::PERMISSOES_GESTAO, ...array_values($permissoes_clientes)];
    }

    /**
     * O usuário tem a permissão direta E a função dele pode usá-la.
     */
    public static function usuario_pode(Usuarios $usuario, string $permissao): bool
    {
        return $usuario->checkPermissionTo($permissao)
            && (bool) $usuario->funcao()?->pode_usar($permissao);
    }

    /**
     * Se a função pode usar a permissão: Vendedor não acessa a gestão de clientes e Gerente não
     * usa as permissões de clientes restritas (excluir, restaurar, configurações padrão e estorno).
     */
    public function pode_usar(string $permissao): bool
    {
        if (! in_array($permissao, self::PERMISSOES_CLIENTES, true)) {
            return true;
        }

        return match ($this) {
            self::Admin, self::Supervisor => true,
            self::Gerente => ! in_array($permissao, self::PERMISSOES_CLIENTES_RESTRITAS, true),
            self::Vendedor => false,
        };
    }
}
