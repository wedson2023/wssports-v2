import { useEffect } from 'react';

import Backdrop from '../Backdrop';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { Body, Card, CloseButton, Footer, Header, ModalButton, Subtitle, Title, Titles } from './styles';

// base dos modais da área do cliente (spec 007): cabeçalho escuro com faixa na cor do tema,
// corpo rolável e rodapé opcional; fecha pelo X, pelo fundo, pelo Esc e pelo botão voltar
export default function ModalCard({ aberto, titulo, subtitulo = null, ao_fechar, rodape = null, largura = 520, children }) {
    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    return (
        <>
            <Card $visivel={aberto} $largura={largura} role="dialog" aria-modal="true" aria-label={titulo}>
                <Header>
                    <Titles>
                        <Title>{titulo}</Title>
                        {subtitulo && <Subtitle>{subtitulo}</Subtitle>}
                    </Titles>
                    <CloseButton type="button" onClick={ao_fechar} aria-label="Fechar">
                        <i className="material-icons" aria-hidden="true">close</i>
                    </CloseButton>
                </Header>
                <Body>{aberto && children}</Body>
                {rodape && <Footer>{rodape}</Footer>}
            </Card>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={155} />
        </>
    );
}

export { ModalButton };
