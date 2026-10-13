import { useEffect, useState } from 'react';

import { Banner, Button, Text } from './styles';

// minutos que faltam para o acesso vencer
const minutos_restantes = (vence_em) => Math.max(0, Math.ceil((vence_em - Date.now()) / 60000));

// tarja do acesso assistido (spec 007, US7): quem do painel está na conta do cliente, o tempo que
// falta e "Voltar ao painel"
export default function AssistedBanner({ nome, usuario, vence_em, ao_voltar }) {
    const [restantes, definir_restantes] = useState(() => minutos_restantes(vence_em));

    useEffect(() => {
        const relogio = setInterval(() => definir_restantes(minutos_restantes(vence_em)), 30000);

        return () => clearInterval(relogio);
    }, [vence_em]);

    return (
        <Banner role="status">
            <i className="material-icons" aria-hidden="true">support_agent</i>
            <Text>
                Você está acessando a conta de <strong>{nome}</strong>
                {usuario ? ` como ${usuario}` : ''} · {restantes} min restantes
            </Text>
            <Button type="button" onClick={ao_voltar}>Voltar ao painel</Button>
        </Banner>
    );
}
