// tokens de tema do site (research.md, R-17 a R-19): cores do tema vêm das props do servidor;
// as cores fixas mudam conforme o modo (escuro = sistema antigo; claro = paleta aprovada)

// largura (px) em que o layout e o comportamento passam para o mobile (FR-003)
export const telas = { mobile: 900 };

// media query do mobile para os styles.jsx
export const mobile = `@media screen and (max-width: ${telas.mobile}px)`;

// cor escura derivada de cada cor principal (research.md, R-04)
export const cores_derivadas = {
    '#c40808': '#a41f1a',
    '#d0af01': '#9f8601',
    '#008000': '#005400',
    '#006eb1': '#024b77',
    '#fe6a00': '#b94e02',
    '#b91552': '#930137',
};

// cores fixas de cada modo (research.md, R-19)
export const paletas = {
    escuro: {
        fundo_pagina: '#000',
        superficie_pais: '#111',
        superficie_titulo: '#222',
        superficie_barra: '#333',
        superficie_cupom: '#444',
        superficie_campeonato: '#666',
        superficie_jogo: '#f0f0f0',
        fundo_lista: '#fff',
        texto_principal: '#fff',
        texto_secundario: '#ccc',
        texto_apagado: '#999',
        texto_jogo: '#000',
    },
    claro: {
        fundo_pagina: '#ffffff',
        superficie_pais: '#e6e6e6',
        superficie_titulo: '#eeeeee',
        superficie_barra: '#f5f5f5',
        superficie_cupom: '#fafafa',
        superficie_campeonato: '#d9d9d9',
        superficie_jogo: '#ffffff',
        fundo_lista: '#ffffff',
        texto_principal: '#222222',
        texto_secundario: '#555555',
        texto_apagado: '#777777',
        texto_jogo: '#000000',
    },
};

// tema completo entregue ao ThemeProvider: cores do tema + paleta do modo atual
export const montar_tema = (tema, modo) => ({
    principal: tema.temas,
    derivada: tema.letter ?? cores_derivadas[tema.temas],
    modo,
    telas,
    ...paletas[modo],
});
