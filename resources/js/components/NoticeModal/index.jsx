import { useEffect, useRef } from 'react';

import Backdrop from '../Backdrop';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { Actions, Card, CloseButton, Handle, Image, ImageLink, PrimaryButton, SecondaryButton, Title } from './styles';

// elementos que recebem foco com Tab dentro do aviso
const focaveis = 'a[href], button:not([disabled])';

// aviso da banca redesenhado com práticas de UX (spec 006, FR-031, diferença visual aprovada):
// cartão centralizado no desktop e folha que sobe de baixo no mobile; "Lido" como ação principal e
// "Fechar" como secundária; fecha no X, no fundo e no Esc; foco preso enquanto aberto
export default function NoticeModal({ aviso, ao_fechar, ao_ler }) {
    const visivel = Boolean(aviso);
    const cartao = useRef(null);
    const botao_lido = useRef(null);
    useVoltarFecha(visivel, ao_fechar);

    useEffect(() => {
        if (!visivel) return undefined;

        botao_lido.current?.focus();

        const ao_teclar = (evento) => {
            if (evento.key === 'Escape') {
                ao_fechar();
                return;
            }

            if (evento.key !== 'Tab' || !cartao.current) return;

            // Tab e Shift+Tab giram só entre os botões e o link do aviso
            const elementos = [...cartao.current.querySelectorAll(focaveis)];
            const primeiro = elementos[0];
            const ultimo = elementos[elementos.length - 1];

            if (evento.shiftKey && document.activeElement === primeiro) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primeiro.focus();
            }
        };

        document.addEventListener('keydown', ao_teclar);

        return () => document.removeEventListener('keydown', ao_teclar);
    }, [visivel, ao_fechar]);

    if (!aviso) return null;

    const titulo = aviso.titulo || 'Aviso';
    const imagem = <Image src={aviso.imagem} alt={titulo} />;

    return (
        <>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} camada={60} />
            <Card ref={cartao} role="dialog" aria-modal="true" aria-label={titulo}>
                <Handle aria-hidden="true" />
                <CloseButton type="button" onClick={ao_fechar} aria-label="Fechar aviso">
                    <i className="material-icons">close</i>
                </CloseButton>
                {aviso.titulo && <Title>{aviso.titulo}</Title>}
                {aviso.link ? (
                    <ImageLink href={aviso.link} target="_blank" rel="noopener noreferrer" title="Abrir o link do aviso">
                        {imagem}
                    </ImageLink>
                ) : imagem}
                <Actions>
                    <SecondaryButton type="button" onClick={ao_fechar}>Fechar</SecondaryButton>
                    <PrimaryButton ref={botao_lido} type="button" onClick={ao_ler}>
                        <i className="material-icons">done</i>
                        Lido
                    </PrimaryButton>
                </Actions>
            </Card>
        </>
    );
}
