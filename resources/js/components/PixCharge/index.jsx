import { useEffect, useState } from 'react';

import { ModalButton } from '../ModalCard';
import useTelaMobile from '../../hooks/useTelaMobile';
import { Code, CodeBox, Copied, Hint, QrCode, Status, Timer, Title } from './styles';

// minutos e segundos que faltam até o prazo do Pix
const tempo_restante = (expira_em) => {
    const segundos = Math.max(0, Math.floor((new Date(expira_em).getTime() - Date.now()) / 1000));

    return `${Math.floor(segundos / 60)}:${String(segundos % 60).padStart(2, '0')}`;
};

// cobrança Pix (modals/credits do antigo, com visual novo): QR Code no desktop, copia e cola com
// botão de copiar e o prazo; vencido, oferece gerar outro
export default function PixCharge({ pix, expirado, ao_gerar_novo }) {
    const e_mobile = useTelaMobile();
    const [copiado, definir_copiado] = useState(false);
    const [restante, definir_restante] = useState(() => tempo_restante(pix.expira_em));

    useEffect(() => {
        const relogio = setInterval(() => definir_restante(tempo_restante(pix.expira_em)), 1000);

        return () => clearInterval(relogio);
    }, [pix.expira_em]);

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(pix.copia_cola);
        } catch {
            // navegador sem permissão de área de transferência: seleciona um campo temporário
            const campo = document.createElement('textarea');
            campo.value = pix.copia_cola;
            document.body.appendChild(campo);
            campo.select();
            document.execCommand('copy');
            campo.remove();
        }

        definir_copiado(true);
        setTimeout(() => definir_copiado(false), 1500);
    };

    if (expirado) {
        return (
            <Status>
                <i className="material-icons" aria-hidden="true">timer_off</i>
                <Title>Este código Pix expirou.</Title>
                <ModalButton type="button" $largo onClick={ao_gerar_novo}>Gerar novo Pix</ModalButton>
            </Status>
        );
    }

    return (
        <>
            <Title>Cobrança gerada</Title>
            {!e_mobile && pix.qr_code_base64 && <QrCode src={`data:image/png;base64,${pix.qr_code_base64}`} alt="QR Code do Pix" />}
            <Hint>{e_mobile ? 'Copie o código e cole no aplicativo do seu banco.' : 'Faça a leitura do QR Code ou copie o código Pix.'}</Hint>
            <CodeBox>
                <Code>{pix.copia_cola}</Code>
                <ModalButton type="button" $largo onClick={copiar}>
                    <i className="material-icons" aria-hidden="true">content_copy</i>
                    Copiar código
                </ModalButton>
                {copiado && <Copied role="status">Código Pix copiado</Copied>}
            </CodeBox>
            <Timer>Expira em {restante}</Timer>
            <Hint>Após o pagamento, é só aguardar nesta tela.</Hint>
        </>
    );
}
