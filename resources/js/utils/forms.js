// máscaras e erros de formulário dos modais de afiliado (spec 012); a API recebe só os dígitos

const digitos = (texto) => String(texto ?? '').replace(/\D/g, '');

// (11) 99999-9999 enquanto digita
export const formatar_telefone = (texto) => {
    const numeros = digitos(texto).slice(0, 11);

    if (numeros.length <= 2) return numeros;
    if (numeros.length <= 6) return `(${numeros.slice(0, 2)}) ${numeros.slice(2)}`;
    if (numeros.length <= 10) return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 6)}-${numeros.slice(6)}`;

    return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 7)}-${numeros.slice(7)}`;
};

// 000.000.000-00 enquanto digita
export const formatar_cpf = (texto) => digitos(texto)
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

const formatar_cnpj = (texto) => digitos(texto)
    .slice(0, 14)
    .replace(/(\d{2})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');

// tipos de chave Pix aceitos pela API (enum TipoChavePix)
export const tipos_chave_pix = ['CPF', 'CNPJ', 'Telefone', 'E-mail', 'Chave aleatória'];

// máscara da chave Pix conforme o tipo, como no modal antigo
export const formatar_chave_pix = (tipo, texto) => {
    if (tipo === 'CPF') return formatar_cpf(texto);
    if (tipo === 'CNPJ') return formatar_cnpj(texto);
    if (tipo === 'Telefone') return formatar_telefone(texto);

    return texto;
};

// chave como a API espera: CPF, CNPJ e telefone só com dígitos
export const chave_pix_para_api = (tipo, texto) => (['CPF', 'CNPJ', 'Telefone'].includes(tipo) ? digitos(texto) : texto.trim());

export const somente_digitos = digitos;

// erros de validação (422) por campo: só a primeira mensagem de cada um
export const erros_por_campo = (erro) => Object.fromEntries(
    Object.entries(erro?.response?.data?.errors ?? {}).map(([campo, mensagens]) => [campo, mensagens[0]]),
);

// estados do Brasil e "Estrangeiro", na ordem do modal antigo
export const estados = [
    ['AC', 'Acre'], ['AL', 'Alagoas'], ['AP', 'Amapá'], ['AM', 'Amazonas'], ['BA', 'Bahia'], ['CE', 'Ceará'],
    ['DF', 'Distrito Federal'], ['ES', 'Espírito Santo'], ['GO', 'Goiás'], ['MA', 'Maranhão'], ['MT', 'Mato Grosso'],
    ['MS', 'Mato Grosso do Sul'], ['MG', 'Minas Gerais'], ['PA', 'Pará'], ['PB', 'Paraíba'], ['PR', 'Paraná'],
    ['PE', 'Pernambuco'], ['PI', 'Piauí'], ['RJ', 'Rio de Janeiro'], ['RN', 'Rio Grande do Norte'],
    ['RS', 'Rio Grande do Sul'], ['RO', 'Rondônia'], ['RR', 'Roraima'], ['SC', 'Santa Catarina'], ['SP', 'São Paulo'],
    ['SE', 'Sergipe'], ['TO', 'Tocantins'], ['EX', 'Estrangeiro'],
];
