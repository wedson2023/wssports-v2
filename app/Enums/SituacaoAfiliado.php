<?php

namespace App\Enums;

/**
 * Situação do afiliado: só Ativo entra e recebe clientes novos. Em análise é o cadastro público
 * aguardando a banca; Recusado pode ser aprovado depois (reavaliação).
 */
enum SituacaoAfiliado: string
{
    case EmAnálise = 'Em análise';
    case Ativo = 'Ativo';
    case Inativo = 'Inativo';
    case Recusado = 'Recusado';

    /**
     * Mensagem do login e da área do afiliado quando a situação não permite o acesso.
     */
    public function mensagem_sem_acesso(): ?string
    {
        return match ($this) {
            self::EmAnálise => 'Seu cadastro ainda está em análise.',
            self::Recusado => 'Seu cadastro não foi aprovado.',
            self::Inativo => 'Seu acesso está desativado.',
            self::Ativo => null,
        };
    }
}
