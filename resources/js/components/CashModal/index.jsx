import { useEffect } from 'react';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { Body, Container, Footer } from './styles';

// moldura dos modais do caixa (spec 017, contracts/paginas §4), igual à dos modais do painel
// (specs 014 a 016): ModalHeader, corpo rolável e rodapé com os botões; altura do conteúdo ou fixa
// desde a abertura (lista que carrega depois); tela cheia no celular. Fecha pelo X, Esc e voltar
export default function CashModal({ aberto, icone, titulo, descricao, ao_fechar, rodape = null, altura_fixa = false, largura = 560, children }) {
    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={47} />
            <Container $visivel={aberto} $altura_fixa={altura_fixa} $largura={largura} role="dialog" aria-modal="true" aria-label={titulo}>
                {aberto && (
                    <>
                        <ModalHeader icone={icone} titulo={titulo} descricao={descricao} ao_fechar={ao_fechar} />
                        <Body>{children}</Body>
                        {rodape && <Footer>{rodape}</Footer>}
                    </>
                )}
            </Container>
        </>
    );
}

// valor em reais digitado ("50", "50,5", "-20,00") → texto da API ("50.50"); null quando inválido.
// Aceita negativo só com permitir_negativo (ajuste de limite, valor recebido)
export const ler_valor = (texto, { permitir_negativo = false } = {}) => {
    const bruto = String(texto ?? '').trim();
    // com vírgula, os pontos são de milhar ("1.250,50"); sem vírgula, o ponto é o decimal ("50.5")
    const limpo = bruto.includes(',') ? bruto.replace(/\./g, '').replace(',', '.') : bruto;
    const padrao = permitir_negativo ? /^-?\d+(\.\d{1,2})?$/ : /^\d+(\.\d{1,2})?$/;

    if (!padrao.test(limpo)) return null;

    return Number(limpo).toFixed(2);
};

// "50.50" → "50,50" para preencher o campo
export const valor_para_campo = (valor) => String(valor ?? '').replace('.', ',');

// mensagem de um campo no erro 422 da API
export const erro_do_campo = (erro, campo) => erro?.response?.data?.errors?.[campo]?.[0] ?? null;

export {
    Button, Field, Fields, GhostButton, Hint, Input, Label, Error, Summary, SummaryRow, Textarea, Warning,
} from './styles';
