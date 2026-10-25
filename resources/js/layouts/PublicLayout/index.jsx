import { useEffect, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { ThemeProvider } from 'styled-components';

import GlobalStyle from '../../components/GlobalStyle';
import useAtualizacaoVersao from '../../hooks/useAtualizacaoVersao';
import { CupomContext, useEstadoCupom } from '../../hooks/useCupom';
import useModo, { ModoContext } from '../../hooks/useModo';
import { SessaoContext, useEstadoSessao } from '../../hooks/useSessao';
import { montar_tema } from '../../theme/tokens';
import { carregar_gtm } from '../../utils/tracking';
import { Container } from './styles';

// páginas que não são do apostador: painel, área do afiliado e retorno do Mercado Pago não carregam o
// GTM (spec 021, Clarification 3)
const fora_do_apostador = (componente) => componente === 'Dashboard'
    || componente.startsWith('Panel')
    || componente.startsWith('Affiliate')
    || componente === 'GatewayReturn';

// layout persistente da área "/" (site de apostas): tema, modo, sessão, cupom e checagem de versão; o
// cupom não reinicia ao navegar entre as páginas (R-16)
export default function PublicLayout({ children }) {
    const { component: componente, props: { tema, rastreamento } } = usePage();
    const { modo, alternar_modo } = useModo(tema.cor_fundo);
    const estado_cupom = useEstadoCupom();
    const estado_sessao = useEstadoSessao();

    // a checagem de versão espera o envio do código terminar (FR-050a)
    useAtualizacaoVersao(estado_cupom.enviando);

    // GTM só nas páginas do apostador (spec 021, R-14). O Inertia troca de página sem recarregar:
    // quem vai do site para o painel na mesma aba continua com o script já carregado, mas o painel
    // não envia eventos; quem abre o painel direto não carrega o GTM
    useEffect(() => {
        if (rastreamento?.gtm_id && !fora_do_apostador(componente)) carregar_gtm(rastreamento.gtm_id);
    }, [componente, rastreamento?.gtm_id]);

    const tema_atual = useMemo(() => montar_tema(tema, modo), [tema, modo]);
    const contexto_modo = useMemo(() => ({ modo, alternar_modo }), [modo, alternar_modo]);

    return (
        <ThemeProvider theme={tema_atual}>
            <ModoContext.Provider value={contexto_modo}>
                <SessaoContext.Provider value={estado_sessao}>
                    <CupomContext.Provider value={estado_cupom}>
                        <GlobalStyle />
                        <Container>{children}</Container>
                    </CupomContext.Provider>
                </SessaoContext.Provider>
            </ModoContext.Provider>
        </ThemeProvider>
    );
}
