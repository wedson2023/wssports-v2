// períodos do caixa (spec 017, R-07): atalhos calculados no fuso do negócio (Brasília) e enviados
// à API como datas AAAA-MM-DD

const fuso = 'America/Sao_Paulo';

// "2026-10-21" de uma data, no fuso de Brasília
const formato_iso = new Intl.DateTimeFormat('en-CA', { timeZone: fuso, year: 'numeric', month: '2-digit', day: '2-digit' });
const data_iso = (data) => formato_iso.format(data);

// meio-dia do dia em Brasília, para somar e subtrair dias sem cair no dia vizinho
const meio_dia = (texto) => new Date(`${texto}T12:00:00-03:00`);

const somar_dias = (texto, dias) => data_iso(new Date(meio_dia(texto).getTime() + dias * 24 * 60 * 60 * 1000));

export const hoje = () => data_iso(new Date());

// atalhos na ordem da tela
export const atalhos_periodo = [
    { chave: 'hoje', rotulo: 'Hoje' },
    { chave: 'ontem', rotulo: 'Ontem' },
    { chave: 'semana', rotulo: 'Esta semana' },
    { chave: 'mes', rotulo: 'Este mês' },
    { chave: 'personalizado', rotulo: 'Personalizado' },
];

// { de, ate } do atalho; a semana vai de segunda até hoje
export const periodo_do_atalho = (chave) => {
    const atual = hoje();

    if (chave === 'ontem') {
        const ontem = somar_dias(atual, -1);

        return { de: ontem, ate: ontem };
    }

    if (chave === 'semana') {
        const dia_semana = meio_dia(atual).getUTCDay();

        return { de: somar_dias(atual, -((dia_semana + 6) % 7)), ate: atual };
    }

    if (chave === 'mes') return { de: `${atual.slice(0, 8)}01`, ate: atual };

    return { de: atual, ate: atual };
};

// atalho que corresponde ao período (ou "personalizado")
export const atalho_do_periodo = ({ de, ate }) => (
    ['hoje', 'ontem', 'semana', 'mes'].find((chave) => {
        const periodo = periodo_do_atalho(chave);

        return periodo.de === de && periodo.ate === ate;
    }) ?? 'personalizado'
);

// mensagem de erro do período, ou null quando está certo (as mesmas regras da API)
export const validar_periodo = ({ de, ate }) => {
    if (!de || !ate) return 'Informe as duas datas.';
    if (de > ate) return 'A data inicial deve ser igual ou anterior à final.';
    if (ate > hoje()) return 'O período não pode terminar depois de hoje.';
    if ((meio_dia(ate) - meio_dia(de)) / (24 * 60 * 60 * 1000) > 366) return 'O período deve ter no máximo 1 ano.';

    return null;
};

// "21/10/2026" ou "14/10/2026 a 21/10/2026"
const formatar = (texto) => texto.split('-').reverse().join('/');

export const texto_periodo = ({ de, ate }) => (de === ate ? formatar(de) : `${formatar(de)} a ${formatar(ate)}`);

// agora no formato do campo datetime-local, no horário de Brasília ("2026-10-21T15:30")
export const agora_local = () => {
    const partes = new Intl.DateTimeFormat('en-CA', {
        timeZone: fuso, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date());
    const parte = (tipo) => partes.find((item) => item.type === tipo).value;

    return `${parte('year')}-${parte('month')}-${parte('day')}T${parte('hour')}:${parte('minute')}`;
};
