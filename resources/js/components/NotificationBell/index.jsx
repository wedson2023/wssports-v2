import { useState } from 'react';

import ConfirmModal from '../ConfirmModal';
import NotificationSettingsModal from '../NotificationSettingsModal';
import useNotificacoesPush from '../../hooks/useNotificacoesPush';
import { Bell } from './styles';

const icones = {
    ativa: 'notifications_active',
    bloqueada: 'notifications_off',
    instalar: 'notifications_none',
    desativada: 'notifications_none',
};

const titulos = {
    ativa: 'Notificações ativadas: toque para escolher o que receber',
    bloqueada: 'Notificações bloqueadas no navegador',
    instalar: 'Instale o aplicativo para receber notificações',
    desativada: 'Ativar notificações dos jogos das suas apostas',
};

// sino das notificações push (FR-040, FR-047): ativa no primeiro toque, abre as preferências
// quando ativo e orienta quando o aparelho exige instalação ou o navegador bloqueou
export default function NotificationBell({ ativo = true }) {
    const push = useNotificacoesPush(ativo);
    const [modal, definir_modal] = useState(null); // preferencias | instalar | bloqueada

    if (!ativo || ['carregando', 'indisponivel'].includes(push.situacao)) return null;

    const tocar = () => {
        if (push.situacao === 'desativada') push.ativar();
        else if (push.situacao === 'ativa') definir_modal('preferencias');
        else definir_modal(push.situacao);
    };

    const desativar = async () => {
        await push.desativar();
        definir_modal(null);
    };

    return (
        <>
            <Bell
                type="button"
                className="material-icons"
                onClick={tocar}
                disabled={push.enviando}
                $ativo={push.situacao === 'ativa'}
                title={titulos[push.situacao]}
                aria-label={titulos[push.situacao]}
            >
                {icones[push.situacao]}
            </Bell>

            <NotificationSettingsModal
                aberto={modal === 'preferencias'}
                preferencias={push.preferencias}
                enviando={push.enviando}
                ao_salvar={push.salvar_preferencias}
                ao_desativar={desativar}
                ao_fechar={() => definir_modal(null)}
            />

            <ConfirmModal
                aberto={modal === 'instalar'}
                titulo="Instale o aplicativo"
                icone="install_mobile"
                mensagem="Para receber notificações no iPhone, toque em Compartilhar e depois em Adicionar à Tela de Início. Abra o aplicativo pela tela de início e toque no sino de novo."
                ao_fechar={() => definir_modal(null)}
            />

            <ConfirmModal
                aberto={modal === 'bloqueada'}
                titulo="Notificações bloqueadas"
                icone="notifications_off"
                mensagem="As notificações deste site estão bloqueadas no navegador. Libere em Configurações do navegador > Configurações do site > Notificações e toque no sino de novo."
                ao_fechar={() => definir_modal(null)}
            />
        </>
    );
}
