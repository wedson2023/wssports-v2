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
        'clientes_promocoes.gerenciar',
        'clientes_promocoes.estornar',
    ];

    /**
     * Permissões de clientes que só Admin e Supervisor podem usar, mesmo que outra função as receba.
     */
    public const PERMISSOES_CLIENTES_RESTRITAS = [
        'clientes.excluir',
        'clientes.restaurar',
        'clientes_promocoes.estornar',
    ];

    /**
     * Todas as permissões de confrontos: cotações, campeonatos, confrontos, não permitidos e
     * configurações.
     */
    public const PERMISSOES_CONFRONTOS = [
        'porcentagens_vendedores.editar',
        'porcentagens_clientes.editar',
        'porcentagens_campeonatos.editar',
        'porcentagens_confrontos.editar',
        'confrontos_teto_cotacoes.editar',
        'campeonatos.listar',
        'campeonatos.cadastrar',
        'campeonatos.editar',
        'campeonatos.excluir',
        'campeonatos.alterar_situacao',
        'campeonatos.favoritar',
        'confrontos.listar',
        'confrontos.cadastrar',
        'confrontos.editar',
        'confrontos.excluir',
        'confrontos.alterar_situacao',
        'campeonatos_nao_permitidos.gerenciar',
        'confrontos_nao_permitidos.gerenciar',
        'confrontos_ao_vivo_nao_permitidos.gerenciar',
        'usuarios_configuracoes.editar',
        'visitantes_configuracoes.editar',
        'confrontos.alterar_limite',
    ];

    /**
     * Todas as permissões de apostas: apostar e validar código (só Vendedor), cancelar (todos)
     * e cancelar com jogo iniciado e editar palpites (só Gerente, Supervisor e Admin).
     */
    public const PERMISSOES_APOSTAS = [
        'apostas.criar',
        'apostas.validar',
        'apostas.cancelar',
        'apostas.cancelar_iniciada',
        'apostas.editar',
    ];

    /**
     * Permissões de apostas que só o Vendedor usa: quem aposta e valida código é só ele.
     */
    public const PERMISSOES_APOSTAS_VENDEDOR = [
        'apostas.criar',
        'apostas.validar',
    ];

    /**
     * Permissões de confrontos que o Gerente recebe e pode usar; as demais são só de Admin e
     * Supervisor.
     */
    public const PERMISSOES_CONFRONTOS_GERENTE = [
        'porcentagens_vendedores.editar',
        'porcentagens_campeonatos.editar',
        'porcentagens_confrontos.editar',
        'campeonatos.listar',
        'confrontos.listar',
        'campeonatos_nao_permitidos.gerenciar',
        'confrontos_nao_permitidos.gerenciar',
        'confrontos_ao_vivo_nao_permitidos.gerenciar',
        'usuarios_configuracoes.editar',
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
            return array_values(array_filter(self::PERMISSOES_APOSTAS, fn (string $permissao) => $this->pode_usar($permissao)));
        }

        // os gestores recebem todas as de apostas para poder repassá-las aos vendedores que
        // cadastram; o pode_usar impede que eles mesmos apostem ou validem códigos
        $permissoes_apostas = self::PERMISSOES_APOSTAS;

        $permissoes_clientes = array_filter(self::PERMISSOES_CLIENTES, fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_confrontos = array_filter(self::PERMISSOES_CONFRONTOS, fn (string $permissao) => $this->pode_usar($permissao));

        return [
            ...self::PERMISSOES_GESTAO,
            ...array_values($permissoes_clientes),
            ...array_values($permissoes_confrontos),
            ...$permissoes_apostas,
        ];
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
     * Se a função pode usar a permissão: Vendedor não acessa a gestão de clientes nem a de
     * confrontos; Gerente não usa as permissões de clientes restritas (excluir, restaurar e
     * estorno) e, das de confrontos, só as de PERMISSOES_CONFRONTOS_GERENTE. Nas apostas, criar e
     * validar código são só do Vendedor; cancelar, de todos; o resto, de Gerente para cima.
     */
    public function pode_usar(string $permissao): bool
    {
        if (in_array($permissao, self::PERMISSOES_APOSTAS, true)) {
            return match (true) {
                $permissao === 'apostas.cancelar' => true,
                in_array($permissao, self::PERMISSOES_APOSTAS_VENDEDOR, true) => $this === self::Vendedor,
                default => $this !== self::Vendedor,
            };
        }

        if (in_array($permissao, self::PERMISSOES_CONFRONTOS, true)) {
            return match ($this) {
                self::Admin, self::Supervisor => true,
                self::Gerente => in_array($permissao, self::PERMISSOES_CONFRONTOS_GERENTE, true),
                self::Vendedor => false,
            };
        }

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
