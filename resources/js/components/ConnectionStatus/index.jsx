import { useEffect, useState } from 'react';

import { Container, Dot, Updated } from './styles';

// "há X s" a partir do horário da última atualização
const tempo_desde = (momento, agora) => {
    if (!momento) return '';

    const segundos = Math.max(0, Math.round((agora - momento) / 1000));

    if (segundos < 60) return `há ${segundos} s`;

    return `há ${Math.floor(segundos / 60)} min`;
};

// indicador da atualização do bilhete (FR-009): "Ao vivo" com a conexão contínua, "Reconectando…"
// na consulta periódica e há quanto tempo os dados chegaram
export default function ConnectionStatus({ conexao, atualizado_em }) {
    const [agora, definir_agora] = useState(Date.now());

    useEffect(() => {
        const timer = setInterval(() => definir_agora(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    if (conexao === 'final') return null;

    const ao_vivo = conexao === 'ao_vivo';

    return (
        <Container role="status" aria-live="polite">
            <Dot $ao_vivo={ao_vivo} aria-hidden="true" />
            <span>{ao_vivo ? 'Ao vivo' : 'Reconectando…'}</span>
            {atualizado_em && <Updated>· atualizado {tempo_desde(atualizado_em, agora)}</Updated>}
        </Container>
    );
}
