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
     * Todas as permissões de gestão de usuários existentes no sistema. Cadastrar, editar e excluir
     * são por função alvo (spec 015); as demais valem para qualquer usuário da rede.
     */
    public const PERMISSOES_GESTAO = [
        'usuarios.listar',
        'usuarios.consultar',
        'usuarios.cadastrar_supervisor',
        'usuarios.cadastrar_gerente',
        'usuarios.cadastrar_vendedor',
        'usuarios.editar_supervisor',
        'usuarios.editar_gerente',
        'usuarios.editar_vendedor',
        'usuarios.alterar_situacao',
        'usuarios.excluir_supervisor',
        'usuarios.excluir_gerente',
        'usuarios.excluir_vendedor',
        'usuarios.gerenciar_permissoes',
    ];

    /**
     * Permissões de cadastrar, editar e excluir por função alvo (spec 015).
     *
     * @var array<string, list<string>>
     */
    public const PERMISSOES_USUARIOS_POR_FUNCAO = [
        'Supervisor' => ['usuarios.cadastrar_supervisor', 'usuarios.editar_supervisor', 'usuarios.excluir_supervisor'],
        'Gerente' => ['usuarios.cadastrar_gerente', 'usuarios.editar_gerente', 'usuarios.excluir_gerente'],
        'Vendedor' => ['usuarios.cadastrar_vendedor', 'usuarios.editar_vendedor', 'usuarios.excluir_vendedor'],
    ];

    /**
     * Nome da permissão de uma ação (cadastrar, editar ou excluir) sobre a função alvo.
     */
    public static function permissao_usuarios(string $acao, self $alvo): string
    {
        return 'usuarios.'.$acao.'_'.mb_strtolower($alvo->value);
    }

    /**
     * Todas as permissões de gestão de clientes e promoções existentes no sistema; as quatro últimas
     * de clientes são da tela Clientes do painel (spec 019).
     */
    public const PERMISSOES_CLIENTES = [
        'clientes.listar',
        'clientes.ver_dados_completos',
        'clientes.editar',
        'clientes.editar_configuracoes',
        'clientes.movimentar_saldo',
        'clientes.excluir',
        'clientes.restaurar',
        'clientes.ver_ranking',
        'clientes.exportar',
        'clientes.enviar_notificacao',
        'clientes.antecipar_pendentes',
        'clientes_promocoes.gerenciar',
        'clientes_promocoes.estornar',
    ];

    /**
     * Permissões de clientes que só Admin e Supervisor podem usar, mesmo que outra função as receba:
     * excluir, restaurar, estornar promoção e, na tela Clientes (spec 019), exportar, enviar
     * notificação e antecipar pendentes.
     */
    public const PERMISSOES_CLIENTES_RESTRITAS = [
        'clientes.excluir',
        'clientes.restaurar',
        'clientes.exportar',
        'clientes.enviar_notificacao',
        'clientes.antecipar_pendentes',
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
        // resultado de jogo (spec 009): só Admin (spec 023)
        'confrontos.lancar_resultado',
    ];

    /**
     * Todas as permissões de apostas: apostar e validar código (só Vendedor); cancelar, listar,
     * exportar e imprimir 2ª via (todos, spec 018); cancelar com jogo iniciado, editar palpites e
     * restaurar aposta cancelada (só Gerente, Supervisor e Admin).
     */
    public const PERMISSOES_APOSTAS = [
        'apostas.criar',
        'apostas.validar',
        'apostas.cancelar',
        'apostas.cancelar_iniciada',
        'apostas.editar',
        'apostas.listar',
        'apostas.exportar',
        'apostas.imprimir_segunda_via',
        'apostas.restaurar',
    ];

    /**
     * Permissões de apostas que todas as funções usam: o vendedor cancela, consulta, exporta e
     * imprime a 2ª via das próprias apostas; os níveis de cima, das da rede.
     */
    public const PERMISSOES_APOSTAS_TODOS = [
        'apostas.cancelar',
        'apostas.listar',
        'apostas.exportar',
        'apostas.imprimir_segunda_via',
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
     * Permissões de especiais: listar, gerenciar (categorias e opções) e encerrar/cancelar.
     * Só Admin e Supervisor; corrigir o resultado (spec 024), só Admin.
     */
    public const PERMISSOES_ESPECIAIS = [
        'especiais.listar',
        'especiais.gerenciar',
        'especiais.encerrar',
        'especiais.corrigir_resultado',
    ];

    /**
     * Permissões dos recursos do site: avisos, banners e configurações (logo e texto das regras).
     * Só Admin e Supervisor; conectar o robô do WhatsApp (spec 021), só Admin.
     */
    public const PERMISSOES_SITE = [
        'avisos.gerenciar',
        'banners.gerenciar',
        'configuracoes.editar',
        'robo_whatsapp.conectar',
    ];

    /**
     * Permissões de pagamentos (spec 007): acessar a conta de um cliente e gerenciar saques (Admin
     * e Supervisor) e vincular a conta do gateway de pagamento (só Admin).
     */
    public const PERMISSOES_PAGAMENTOS = [
        'clientes.acessar_conta',
        'saques.gerenciar',
        'gateway_pagamento.vincular',
    ];

    /**
     * Permissões do encerramento de aposta (spec 010): listar os encerramentos no painel. Só Admin e
     * Supervisor.
     */
    public const PERMISSOES_ENCERRAMENTOS = [
        'apostas_encerramentos.listar',
    ];

    /**
     * Permissões do cassino (spec 011): gerenciar o catálogo (jogos, provedores, canais, tipos de
     * ação e sincronização) e consultar o movimento (relatório, histórico e chamadas do provedor).
     * Só Admin e Supervisor.
     */
    public const PERMISSOES_CASSINO = [
        'cassinos_jogos.gerenciar',
        'cassinos_rodadas.listar',
    ];

    /**
     * Permissões de afiliados (spec 012): gestão, situação, comissão e prestação de contas.
     */
    public const PERMISSOES_AFILIADOS = [
        'afiliados.listar',
        'afiliados.consultar',
        'afiliados.cadastrar',
        'afiliados.editar',
        'afiliados.alterar_situacao',
        'afiliados.prestar_contas',
        'afiliados.editar_comissao',
        'afiliados.excluir',
        'afiliados.restaurar',
    ];

    /**
     * Permissões de afiliados que só Admin e Supervisor podem usar, mesmo que o Gerente as receba.
     */
    public const PERMISSOES_AFILIADOS_RESTRITAS = [
        'afiliados.excluir',
        'afiliados.restaurar',
    ];

    /**
     * Permissão de afiliados que o Gerente pode usar, mas não recebe ao ser cadastrado: a regra de
     * comissão mexe no custo da banca e é liberada caso a caso.
     */
    public const PERMISSAO_AFILIADOS_SOB_PEDIDO = 'afiliados.editar_comissao';

    /**
     * Permissões da Dashboard do painel (spec 014): uma por seção, na ordem da tela.
     */
    public const PERMISSOES_DASHBOARD = [
        'dashboard.ver_apurado',
        'dashboard.ver_situacao_jogos',
        'dashboard.ver_apostas',
        'dashboard.ver_combinacao_risco',
        'dashboard.ver_apostas_um_jogo',
        'dashboard.ver_vendedores',
        'dashboard.ver_jogos_mais_apostados',
        'dashboard.ver_opcoes_mais_apostadas',
        'dashboard.ver_caixa_online',
    ];

    /**
     * Seções da Dashboard que o Vendedor pode ver: só o que ele mesmo fez, sem as de gestão.
     */
    public const PERMISSOES_DASHBOARD_VENDEDOR = [
        'dashboard.ver_apurado',
        'dashboard.ver_situacao_jogos',
        'dashboard.ver_apostas',
        'dashboard.ver_apostas_um_jogo',
    ];

    /**
     * Permissões do caixa do painel (spec 017): consultar a rede e os clientes, lançar, excluir
     * lançamento, prestar contas, exportar e editar a comissão do gerente.
     */
    public const PERMISSOES_CAIXA = [
        'caixa.ver',
        'caixa.ver_clientes',
        'caixa.lancar',
        'caixa.excluir_lancamento',
        'caixa.prestar_contas',
        'caixa.exportar',
        'caixa.editar_comissao_gerente',
    ];

    /**
     * Permissões do caixa que o Gerente não pode usar: o caixa dos clientes é da banca toda e a
     * comissão do gerente é definida pelos superiores dele.
     */
    public const PERMISSOES_CAIXA_RESTRITAS_GERENTE = [
        'caixa.ver_clientes',
        'caixa.editar_comissao_gerente',
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
        $permissoes_dashboard = array_values(array_filter(self::PERMISSOES_DASHBOARD, fn (string $permissao) => $this->pode_usar($permissao)));
        $permissoes_caixa = array_values(array_filter(self::PERMISSOES_CAIXA, fn (string $permissao) => $this->pode_usar($permissao)));

        if ($this === self::Vendedor) {
            return [
                ...array_values(array_filter(self::PERMISSOES_APOSTAS, fn (string $permissao) => $this->pode_usar($permissao))),
                ...$permissoes_dashboard,
                ...$permissoes_caixa,
            ];
        }

        // os gestores recebem todas as de apostas para poder repassá-las aos vendedores que
        // cadastram; o pode_usar impede que eles mesmos apostem ou validem códigos
        $permissoes_apostas = self::PERMISSOES_APOSTAS;

        $permissoes_clientes = array_filter(self::PERMISSOES_CLIENTES, fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_confrontos = array_filter(self::PERMISSOES_CONFRONTOS, fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_site = array_filter([...self::PERMISSOES_ESPECIAIS, ...self::PERMISSOES_SITE], fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_pagamentos = array_filter(self::PERMISSOES_PAGAMENTOS, fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_encerramentos = array_filter(self::PERMISSOES_ENCERRAMENTOS, fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_cassino = array_filter(self::PERMISSOES_CASSINO, fn (string $permissao) => $this->pode_usar($permissao));
        $permissoes_afiliados = array_filter(
            self::PERMISSOES_AFILIADOS,
            fn (string $permissao) => $this->pode_usar($permissao)
                && ! ($this === self::Gerente && $permissao === self::PERMISSAO_AFILIADOS_SOB_PEDIDO),
        );

        return [
            ...array_values(array_filter(self::PERMISSOES_GESTAO, fn (string $permissao) => $this->pode_usar($permissao))),
            ...array_values($permissoes_clientes),
            ...array_values($permissoes_confrontos),
            ...$permissoes_apostas,
            ...array_values($permissoes_site),
            ...array_values($permissoes_pagamentos),
            ...array_values($permissoes_encerramentos),
            ...array_values($permissoes_cassino),
            ...array_values($permissoes_afiliados),
            ...$permissoes_dashboard,
            ...$permissoes_caixa,
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
     * estorno) e, das de confrontos, só as de PERMISSOES_CONFRONTOS_GERENTE; lançar resultado de
     * jogo é só do Admin (spec 023). Nas apostas, criar e
     * validar código são só do Vendedor; cancelar, listar, exportar e imprimir 2ª via, de todos
     * (PERMISSOES_APOSTAS_TODOS); o resto, de Gerente para cima.
     * Especiais, recursos do site, pagamentos, encerramentos de aposta e cassino são só de Admin e
     * Supervisor (vincular o gateway e corrigir o resultado de especiais, só Admin). Afiliados: Vendedor nenhuma; Gerente todas menos
     * excluir e restaurar. Dashboard: Admin e Supervisor todas; Gerente todas menos a Caixa online;
     * Vendedor só as do que ele mesmo fez (PERMISSOES_DASHBOARD_VENDEDOR). Usuários (spec 015):
     * cadastrar, editar e excluir só sobre funções abaixo (o Vendedor nunca cadastra vendedor); as
     * demais de usuários, de Gerente para cima. Caixa (spec 017): Admin e Supervisor todas; Gerente
     * todas menos ver o caixa dos clientes e editar a comissão do gerente; Vendedor só ver o próprio.
     */
    public function pode_usar(string $permissao): bool
    {
        // usuários: cadastrar/editar/excluir só de funções abaixo; as gerais, só de quem tem rede
        if (in_array($permissao, self::PERMISSOES_GESTAO, true)) {
            foreach (self::PERMISSOES_USUARIOS_POR_FUNCAO as $alvo => $permissoes) {
                if (in_array($permissao, $permissoes, true)) {
                    return self::from($alvo)->nivel() > $this->nivel();
                }
            }

            return $this !== self::Vendedor;
        }

        if (in_array($permissao, self::PERMISSOES_DASHBOARD, true)) {
            return match ($this) {
                self::Admin, self::Supervisor => true,
                self::Gerente => $permissao !== 'dashboard.ver_caixa_online',
                self::Vendedor => in_array($permissao, self::PERMISSOES_DASHBOARD_VENDEDOR, true),
            };
        }

        if (in_array($permissao, self::PERMISSOES_CAIXA, true)) {
            return match ($this) {
                self::Admin, self::Supervisor => true,
                self::Gerente => ! in_array($permissao, self::PERMISSOES_CAIXA_RESTRITAS_GERENTE, true),
                self::Vendedor => $permissao === 'caixa.ver',
            };
        }

        // vincular o gateway mexe no dinheiro de toda a banca e o robô usa o WhatsApp da banca
        // (spec 021): só o Admin
        if (in_array($permissao, ['gateway_pagamento.vincular', 'robo_whatsapp.conectar'], true)) {
            return $this === self::Admin;
        }

        // corrigir o resultado reapura e estorna prêmios de todas as redes (spec 024): só o Admin
        if ($permissao === 'especiais.corrigir_resultado') {
            return $this === self::Admin;
        }

        // lançar resultado vale para todas as redes e segue o antigo: só o Admin (spec 023)
        if ($permissao === 'confrontos.lancar_resultado') {
            return $this === self::Admin;
        }

        if (in_array($permissao, self::PERMISSOES_AFILIADOS, true)) {
            return match ($this) {
                self::Admin, self::Supervisor => true,
                self::Gerente => ! in_array($permissao, self::PERMISSOES_AFILIADOS_RESTRITAS, true),
                self::Vendedor => false,
            };
        }

        if (in_array($permissao, [...self::PERMISSOES_ESPECIAIS, ...self::PERMISSOES_SITE, ...self::PERMISSOES_PAGAMENTOS, ...self::PERMISSOES_ENCERRAMENTOS, ...self::PERMISSOES_CASSINO], true)) {
            return $this === self::Admin || $this === self::Supervisor;
        }

        if (in_array($permissao, self::PERMISSOES_APOSTAS, true)) {
            return match (true) {
                in_array($permissao, self::PERMISSOES_APOSTAS_TODOS, true) => true,
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
