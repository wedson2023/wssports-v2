// acesso ao localStorage sempre em try/catch: em janela anônima, com dados bloqueados ou sem
// espaço, a tela continua funcionando sem guardar nada

export const chave_cupom = 'wssports.cupom';
export const chave_modo = 'wssports.modo';
export const chave_sessao = 'wssports.sessao';
// preferências de impressão do vendedor: { modo: 'PADRÃO' | 'APP', largura: 58 | 80 }
export const chave_impressao = 'wssports.impressao';
// identificador do aparelho, usado no "Lido" dos avisos da banca
export const chave_aparelho = 'wssports.aparelho';

export const ler_texto = (chave) => {
    try {
        return window.localStorage.getItem(chave);
    } catch {
        return null;
    }
};

export const gravar_texto = (chave, valor) => {
    try {
        window.localStorage.setItem(chave, valor);
    } catch {
        // sem acesso ao armazenamento: segue sem guardar
    }
};

// devolve null quando a chave não existe, o conteúdo não é JSON válido ou não há acesso
export const ler_json = (chave) => {
    const texto = ler_texto(chave);

    if (texto === null) return null;

    try {
        return JSON.parse(texto);
    } catch {
        return null;
    }
};

export const gravar_json = (chave, valor) => gravar_texto(chave, JSON.stringify(valor));

export const remover = (chave) => {
    try {
        window.localStorage.removeItem(chave);
    } catch {
        // sem acesso ao armazenamento: nada a remover
    }
};

// "Limpar cache" (FR-016): apaga o cupom, o modo escolhido, a sessão de login, as preferências de
// impressão e o aparelho (um novo é gerado; os avisos lidos só por ele voltam)
export const limpar_dados_locais = () => {
    remover(chave_cupom);
    remover(chave_modo);
    remover(chave_sessao);
    remover(chave_impressao);
    remover(chave_aparelho);
};
