// datas exibidas no fuso do negócio (Brasília, -03:00)
const fuso = 'America/Sao_Paulo';

const formato_hora = new Intl.DateTimeFormat('pt-BR', { timeZone: fuso, hour: '2-digit', minute: '2-digit' });
const formato_dia_mes = new Intl.DateTimeFormat('pt-BR', { timeZone: fuso, day: '2-digit', month: '2-digit' });
const formato_dia_semana = new Intl.DateTimeFormat('pt-BR', { timeZone: fuso, weekday: 'long' });

// nomes dos dias iguais aos do sistema antigo (objeto week de screens/main/index.js)
const dias_semana = {
    domingo: 'Domingo',
    'segunda-feira': 'Segunda',
    'terça-feira': 'Terça',
    'quarta-feira': 'Quarta',
    'quinta-feira': 'Quinta',
    'sexta-feira': 'Sexta',
    sábado: 'Sábado',
};

// "2026-10-09T21:30:00-03:00" → "21:30"
export const formatar_hora = (data) => formato_hora.format(new Date(data));

// "2026-10-09T21:30:00-03:00" → "09/10"
export const formatar_dia_mes = (data) => formato_dia_mes.format(new Date(data));

const formato_data_hora = new Intl.DateTimeFormat('pt-BR', {
    timeZone: fuso, day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
});

// "09/10/2026 21:30"
export const formatar_data_hora = (data) => formato_data_hora.format(new Date(data)).replace(',', '');

// nome do dia da semana de depois de amanhã (terceira aba de data)
export const nome_dia_depois_de_amanha = () => {
    const depois_de_amanha = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000);

    return dias_semana[formato_dia_semana.format(depois_de_amanha)];
};
