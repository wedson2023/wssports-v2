<?php

namespace App\Enums;

/**
 * Mensagens do sistema (spec 025, FR-023): canal padrão, canal de reserva e quais aparecem na escolha
 * de canal da tela Configurações.
 */
enum TipoMensagem: string
{
    case CódigoRecuperaçãoCliente = 'Código de recuperação do cliente';
    case BoasVindasCliente = 'Boas-vindas do cliente';
    case ConfirmaçãoCadastro = 'Confirmação de cadastro';
    case CombinaçãoRisco = 'Combinação de risco';
    case SaqueParado = 'Saque parado';
    case AfiliadoCadastroRecebido = 'Cadastro de afiliado recebido';
    case AfiliadoAprovado = 'Afiliado aprovado';
    case AfiliadoRecusado = 'Afiliado recusado';
    case CódigoRecuperaçãoAfiliado = 'Código de recuperação do afiliado';
    case RobôDesconectado = 'Robô desconectado';
    case Teste = 'Teste';

    public function canal_padrao(): CanalMensagem
    {
        return match ($this) {
            self::CódigoRecuperaçãoCliente, self::CombinaçãoRisco, self::SaqueParado => CanalMensagem::WhatsApp,
            self::BoasVindasCliente => CanalMensagem::OsDois,
            default => CanalMensagem::Email,
        };
    }

    /**
     * Sai pelo outro canal quando o escolhido falha ou não pode ser usado (FR-008 e FR-011a).
     */
    public function tem_reserva(): bool
    {
        return in_array($this, [self::CódigoRecuperaçãoCliente, self::CódigoRecuperaçãoAfiliado, self::CombinaçãoRisco, self::SaqueParado], true);
    }

    /**
     * Códigos saem antes das demais mensagens (FR-006, research R-04).
     */
    public function e_codigo(): bool
    {
        return in_array($this, [self::CódigoRecuperaçãoCliente, self::CódigoRecuperaçãoAfiliado], true);
    }

    /**
     * Fora da lista: a confirmação segue o canal das boas-vindas, a queda do robô é sempre por e-mail
     * (FR-024) e o teste usa o canal do botão.
     */
    public function escolhivel(): bool
    {
        return ! in_array($this, [self::ConfirmaçãoCadastro, self::RobôDesconectado, self::Teste], true);
    }

    public function destinatario(): string
    {
        return match ($this) {
            self::CódigoRecuperaçãoCliente, self::BoasVindasCliente, self::ConfirmaçãoCadastro => 'Cliente',
            self::AfiliadoCadastroRecebido, self::AfiliadoAprovado, self::AfiliadoRecusado, self::CódigoRecuperaçãoAfiliado => 'Afiliado',
            default => 'Admin',
        };
    }

    /**
     * @return list<self>
     */
    public static function escolhiveis(): array
    {
        return array_values(array_filter(self::cases(), fn (self $tipo) => $tipo->escolhivel()));
    }
}
