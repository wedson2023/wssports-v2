import { usePage } from '@inertiajs/react';

import NotificationBell from '../NotificationBell';
import { Actions, Bar, Icon, Logo, Welcome } from './styles';

// topo da tela de créditos (Header, Wellcome e Logo de screens/credits): voltar, saudação, menu
// (só no mobile) e logo da banca
export default function AccountHeader({ nome, ao_voltar, ao_abrir_menu }) {
    const { tema, nome_sistema } = usePage().props;

    return (
        <>
            <Bar>
                <Icon className="material-icons" onClick={ao_voltar} title="Voltar" role="button">keyboard_arrow_left</Icon>
                <Welcome>Olá {nome} Bem Vindo!</Welcome>
                <Actions>
                    {/* notificações push do aparelho (spec 010, FR-040) */}
                    <NotificationBell />
                    <Icon className="material-icons" $menu onClick={ao_abrir_menu} title="Clique para abrir o menu de opção" role="button">more_vert</Icon>
                </Actions>
            </Bar>
            <Logo src={tema.logo} alt={nome_sistema} />
        </>
    );
}
