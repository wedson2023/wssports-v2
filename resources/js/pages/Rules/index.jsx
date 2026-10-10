import { Head, usePage } from '@inertiajs/react';

import RulesContent from '../../components/RulesContent';
import { Page } from './styles';

// página /regras (FR-017; spec 006, US4)
export default function Rules({ regras, regras_bonus = [], limites_aposta = null }) {
    const { nome_sistema } = usePage().props;

    return (
        <Page>
            <Head title={nome_sistema} />
            <RulesContent regras={regras} regras_bonus={regras_bonus} limites_aposta={limites_aposta} />
        </Page>
    );
}
