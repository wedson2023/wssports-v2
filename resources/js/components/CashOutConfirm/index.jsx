import { useEffect, useRef, useState } from 'react';

import Spinner from '../Spinner';
import { formatar_hora } from '../../utils/dates';
import { formatar_real, para_centavos } from '../../utils/money';
import { Actions, Amount, Box, Cancel, Confirm, Countdown, Details, Line, Preference, Title } from './styles';

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

const segundos_ate = (momento) => Math.max(0, Math.ceil((new Date(momento).getTime() - Date.now()) / 1000));

// opções de aceitar alterações da oferta durante a confirmação (FR-025)
const preferencias = [
    { valor: 'Somente para maior', rotulo: 'Aceitar se a oferta subir' },
    { valor: 'Qualquer', rotulo: 'Aceitar qualquer alteração' },
    { valor: 'Nenhuma', rotulo: 'Não aceitar alterações' },
];

// confirmação do encerramento dentro da página (FR-024): quanto recebe, o que deixa de concorrer
// ou continua valendo, quando entra no saldo e a contagem regressiva da validade da oferta
export default function CashOutConfirm({
    modalidade, valor, restante = null, total_a_pagar, expira_em, minutos_credito, ao_vivo,
    aceitar, ao_mudar_aceitar, enviando, ao_confirmar, ao_cancelar, ao_expirar,
}) {
    const [segundos, definir_segundos] = useState(() => segundos_ate(expira_em));
    const botao = useRef(null);

    useEffect(() => {
        botao.current?.focus();
    }, []);

    useEffect(() => {
        const timer = setInterval(() => {
            const restantes = segundos_ate(expira_em);
            definir_segundos(restantes);

            if (restantes === 0 && !enviando) {
                clearInterval(timer);
                ao_expirar();
            }
        }, 250);

        return () => clearInterval(timer);
    }, [expira_em, enviando, ao_expirar]);

    const fechar_com_esc = (evento) => {
        if (evento.key === 'Escape' && !enviando) ao_cancelar();
    };

    const credito = formatar_hora(new Date(Date.now() + minutos_credito * 60000));

    return (
        <Box role="dialog" aria-modal="true" aria-labelledby="titulo-confirmacao-encerramento" onKeyDown={fechar_com_esc}>
            <Title id="titulo-confirmacao-encerramento">
                {modalidade === 'Parcial' ? 'Confirmar encerramento parcial' : 'Confirmar encerramento'}
            </Title>
            <Amount>{real(valor)}</Amount>
            <Details>
                {modalidade === 'Parcial' && restante ? (
                    <>
                        <Line><span>Continua valendo</span><strong>{real(restante.valor_apostado)}</strong></Line>
                        <Line><span>Prêmio que continua</span><strong>{real(restante.premio)}</strong></Line>
                    </>
                ) : (
                    <Line><span>Deixa de concorrer ao prêmio de</span><strong>{real(total_a_pagar)}</strong></Line>
                )}
                <Line><span>Entra no seu saldo às</span><strong>{credito}</strong></Line>
            </Details>
            {ao_vivo && (
                <Preference>
                    <span>Se a oferta mudar durante a confirmação</span>
                    <select value={aceitar} onChange={(evento) => ao_mudar_aceitar(evento.target.value)} disabled={enviando}>
                        {preferencias.map(({ valor: opcao, rotulo }) => <option key={opcao} value={opcao}>{rotulo}</option>)}
                    </select>
                </Preference>
            )}
            <Countdown aria-live="polite">
                {enviando ? 'Enviando…' : `Oferta válida por ${segundos} s`}
            </Countdown>
            <Actions>
                <Cancel type="button" onClick={ao_cancelar} disabled={enviando}>Cancelar</Cancel>
                <Confirm ref={botao} type="button" onClick={ao_confirmar} disabled={enviando || segundos === 0}>
                    {enviando ? <Spinner tamanho={18} /> : 'Confirmar'}
                </Confirm>
            </Actions>
        </Box>
    );
}
