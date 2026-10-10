import { createContext, useCallback, useState } from 'react';
import { chave_modo, gravar_texto } from '../utils/storage';

// modo atual e a troca, entregues pelo PublicLayout às páginas (botão dia/noite)
export const ModoContext = createContext({ modo: 'escuro', alternar_modo: () => {} });

const cor_da_pagina = { claro: '#ffffff', escuro: '#000000' };

// modo inicial: o salvo no aparelho (marcado no <html> pelo app.blade.php) ou, sem escolha,
// o da cor de fundo configurada (FR-055)
const modo_inicial = (cor_fundo) => {
    const salvo = document.documentElement.dataset.modo;

    if (salvo === 'claro' || salvo === 'escuro') return salvo;

    return cor_fundo?.toUpperCase() === '#FFFFFF' ? 'claro' : 'escuro';
};

// modo claro/escuro da tela; a troca vale na hora, sem recarregar (FR-056)
export default function useModo(cor_fundo) {
    const [modo, definir_modo] = useState(() => modo_inicial(cor_fundo));

    const alternar_modo = useCallback(() => {
        definir_modo((atual) => {
            const novo = atual === 'escuro' ? 'claro' : 'escuro';

            gravar_texto(chave_modo, novo);
            document.documentElement.dataset.modo = novo;
            document.documentElement.style.backgroundColor = cor_da_pagina[novo];

            return novo;
        });
    }, []);

    return { modo, alternar_modo };
}
