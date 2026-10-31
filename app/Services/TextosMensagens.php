<?php

namespace App\Services;

use App\Enums\CanalMensagem;
use App\Enums\TipoMensagem;
use App\Mail\AdminCombinacaoRisco;
use App\Mail\AdminRoboDesconectado;
use App\Mail\AdminSaqueParado;
use App\Mail\AfiliadoAprovado;
use App\Mail\AfiliadoCadastroRecebido;
use App\Mail\AfiliadoCodigoRecuperacao;
use App\Mail\AfiliadoRecusado;
use App\Mail\ClienteCadastroRealizado;
use App\Mail\ClienteCodigoRecuperacao;
use App\Mail\ClienteConfirmacaoCadastro;
use App\Mail\MensagemTeste;
use App\Models\Afiliados;
use App\Models\AfiliadosCodigosRecuperacao;
use App\Models\Clientes;
use App\Models\ClientesCodigosRecuperacao;
use App\Models\ClientesConfirmacoesEmail;
use App\Models\Configuracoes;
use App\Models\Saques;

/**
 * Conteúdo e destinatários de cada mensagem (spec 025, contracts/mensagens.md): o texto do WhatsApp e
 * o e-mail de cada tipo, no formato que DisparoMensagens recebe.
 */
class TextosMensagens
{
    public function __construct(private AvisosWhatsapp $avisos) {}

    /**
     * @return array<string, mixed>
     */
    public function codigo_recuperacao_cliente(Clientes $cliente, string $codigo, int $codigo_id, int $validade_minutos): array
    {
        return [
            ...$this->do_cliente($cliente),
            'tipo' => TipoMensagem::CódigoRecuperaçãoCliente,
            'texto' => "Seu código de recuperação é {$codigo}, válido por {$validade_minutos} minutos.",
            'email_mensagem' => new ClienteCodigoRecuperacao($cliente, $codigo, $validade_minutos),
            'validar' => [ClientesCodigosRecuperacao::class, $codigo_id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function boas_vindas_cliente(Clientes $cliente): array
    {
        return [
            ...$this->do_cliente($cliente),
            'tipo' => TipoMensagem::BoasVindasCliente,
            'texto' => "Olá, {$cliente->nome}! Sua conta foi criada com sucesso.",
            'email_mensagem' => new ClienteCadastroRealizado($cliente),
        ];
    }

    /**
     * Substitui as boas-vindas (FR-030): o e-mail sai sempre, e o WhatsApp só quando as boas-vindas
     * o usam.
     *
     * @return array<string, mixed>
     */
    public function confirmacao_cadastro(Clientes $cliente, string $link, int $confirmacao_id): array
    {
        $usa_whatsapp = Configuracoes::atual()->canal_mensagem(TipoMensagem::BoasVindasCliente) !== CanalMensagem::Email;

        return [
            ...$this->do_cliente($cliente),
            'tipo' => TipoMensagem::ConfirmaçãoCadastro,
            'canal' => $usa_whatsapp ? CanalMensagem::OsDois : CanalMensagem::Email,
            'texto' => "Olá, {$cliente->nome}! Enviamos um link para {$cliente->email}. Confirme para entrar na sua conta.",
            'email_mensagem' => new ClienteConfirmacaoCadastro($cliente, $link),
            'validar' => [ClientesConfirmacoesEmail::class, $confirmacao_id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function combinacao_risco(string $texto): array
    {
        return [
            ...$this->do_admin(),
            'tipo' => TipoMensagem::CombinaçãoRisco,
            'texto' => $texto,
            'email_mensagem' => new AdminCombinacaoRisco($texto),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saque_parado(Saques $saque): array
    {
        $nome = $saque->cliente?->nome ?? 'Cliente';
        $valor = $this->avisos->real((string) $saque->valor);
        $tipo_chave = $saque->pix_tipo_chave->value;
        $link = url('/painel/clientes');

        return [
            ...$this->do_admin(),
            'tipo' => TipoMensagem::SaqueParado,
            'texto' => "⚠️ Saque aguardando há 30 minutos\n\nCliente: {$nome}\nValor: {$valor}\nChave Pix ({$tipo_chave}): {$saque->pix_chave}\n\nConfira e dê baixa no painel: {$link}",
            'email_mensagem' => new AdminSaqueParado($nome, $valor, $tipo_chave, $saque->pix_chave),
            'referencia_tipo' => 'Saque',
            'referencia_id' => $saque->id,
        ];
    }

    /**
     * Só e-mail (FR-024): sem texto de WhatsApp.
     *
     * @return array<string, mixed>
     */
    public function robo_desconectado(): array
    {
        return [
            ...$this->do_admin(),
            'tipo' => TipoMensagem::RobôDesconectado,
            'canal' => CanalMensagem::Email,
            'email_mensagem' => new AdminRoboDesconectado,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function afiliado_cadastro_recebido(Afiliados $afiliado): array
    {
        return [
            ...$this->do_afiliado($afiliado),
            'tipo' => TipoMensagem::AfiliadoCadastroRecebido,
            'texto' => "Olá, {$afiliado->nome}! Recebemos seu cadastro de afiliado. Avisaremos quando ele for analisado.",
            'email_mensagem' => new AfiliadoCadastroRecebido($afiliado),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function afiliado_aprovado(Afiliados $afiliado): array
    {
        return [
            ...$this->do_afiliado($afiliado),
            'tipo' => TipoMensagem::AfiliadoAprovado,
            'texto' => "Olá, {$afiliado->nome}! Seu cadastro de afiliado foi aprovado. Acesse: ".url('/afiliados'),
            'email_mensagem' => new AfiliadoAprovado($afiliado),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function afiliado_recusado(Afiliados $afiliado): array
    {
        return [
            ...$this->do_afiliado($afiliado),
            'tipo' => TipoMensagem::AfiliadoRecusado,
            'texto' => "Olá, {$afiliado->nome}! Seu cadastro de afiliado não foi aprovado.",
            'email_mensagem' => new AfiliadoRecusado($afiliado),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function codigo_recuperacao_afiliado(Afiliados $afiliado, string $codigo, int $codigo_id, int $validade_minutos): array
    {
        return [
            ...$this->do_afiliado($afiliado),
            'tipo' => TipoMensagem::CódigoRecuperaçãoAfiliado,
            'texto' => "Seu código de recuperação é {$codigo}, válido por {$validade_minutos} minutos.",
            'email_mensagem' => new AfiliadoCodigoRecuperacao($afiliado, $codigo, $validade_minutos),
            'validar' => [AfiliadosCodigosRecuperacao::class, $codigo_id],
        ];
    }

    /**
     * Sempre para os dados do Admin configurados: não há destinatário livre (FR-020 e FR-021).
     *
     * @return array<string, mixed>
     */
    public function teste(CanalMensagem $canal): array
    {
        $nome_sistema = Configuracoes::atual()->nome_sistema;

        return [
            ...$this->do_admin(),
            'tipo' => TipoMensagem::Teste,
            'canal' => $canal,
            'texto' => "Teste de mensagem da {$nome_sistema}. O WhatsApp está funcionando.",
            'email_mensagem' => new MensagemTeste,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function do_cliente(Clientes $cliente): array
    {
        return [
            'telefone' => $cliente->telefone ? $cliente->ddi.$cliente->telefone : null,
            'email' => $cliente->email,
            'referencia_tipo' => 'Cliente',
            'referencia_id' => $cliente->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function do_afiliado(Afiliados $afiliado): array
    {
        return [
            'telefone' => $afiliado->telefone ? $afiliado->ddi.$afiliado->telefone : null,
            'email' => $afiliado->email,
            'referencia_tipo' => 'Afiliado',
            'referencia_id' => $afiliado->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function do_admin(): array
    {
        $configuracoes = Configuracoes::atual();

        return [
            'telefone' => $configuracoes->telefone_whatsapp_admin,
            'email' => $configuracoes->email_admin,
        ];
    }
}
