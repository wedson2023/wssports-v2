import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { ThemeProvider } from 'styled-components';

import GlobalStyle from '../../components/GlobalStyle';
import useAtualizacaoVersao from '../../hooks/useAtualizacaoVersao';
import { CupomContext, useEstadoCupom } from '../../hooks/useCupom';
import useModo, { ModoContext } from '../../hooks/useModo';
import { SessaoContext, useEstadoSessao } from '../../hooks/useSessao';
import { montar_tema } from '../../theme/tokens';
import { Container } from './styles';

// layout persistente da área "/" (site de apostas): tema, modo, sessão, cupom e checagem de versão; o
// cupom não reinicia ao navegar entre as páginas (R-16)
export default function PublicLayout({ children }) {
    const { tema } = usePage().props;
    const { modo, alternar_modo } = useModo(tema.cor_fundo);
    const estado_cupom = useEstadoCupom();
    const estado_sessao = useEstadoSessao();

    // a checagem de versão espera o envio do código terminar (FR-050a)
    useAtualizacaoVersao(estado_cupom.enviando);

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
