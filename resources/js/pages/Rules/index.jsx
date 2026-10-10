import { Head, usePage } from '@inertiajs/react';

import RulesContent from '../../components/RulesContent';
import { Page } from './styles';

// página /regras (FR-017)
export default function Rules({ regras }) {
    const { nome_sistema } = usePage().props;

    return (
        <Page>
            <Head title={nome_sistema} />
            <RulesContent regras={regras} />
        </Page>
    );
}
