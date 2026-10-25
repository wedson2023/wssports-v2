<?php

namespace App\Enums;

/**
 * Ação registrada no histórico da tela Configurações (spec 021, FR-003a): alteração de campos,
 * cadastro e exclusão de banners e avisos, vínculo e desvínculo do Mercado Pago e conexão e
 * desconexão do robô do WhatsApp.
 */
enum AcaoConfiguracao: string
{
    case Alteração = 'Alteração';
    case Cadastro = 'Cadastro';
    case Exclusão = 'Exclusão';
    case Vínculo = 'Vínculo';
    case Desvínculo = 'Desvínculo';
    case Conexão = 'Conexão';
    case Desconexão = 'Desconexão';
}
