import { esportes } from '../SportsBar';
import { Chip, Chips, Error, Field, Grid, Group, GroupTitle, Input, Label, Select, TextArea } from './styles';

// esportes que existem no site (barra de esportes), sem as abas que não são esporte
const esportes_existentes = esportes.filter(({ chave }) => !['CASSINO', 'AO VIVO', 'ESPECIAL'].includes(chave));

// compara nomes como o backend: sem diferenciar maiúsculas nem acentos ("HÓQUEI" = "HOQUEI")
const normalizar = (nome) => String(nome).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim();

// limites e regras de aposta do vendedor (modal do vendedor e regras do gerente do antigo), em grupos;
// tipo: dinheiro, numero (2 casas), inteiro, booleano, periodo, data, texto ou esportes
const grupos = [
    {
        titulo: 'Limites',
        campos: [
            ['limite_geral', 'Limite geral', 'dinheiro', 'Limite em reais para apostas em geral.'],
            ['limite_duplo', 'Limite duplo', 'dinheiro', 'Limite em reais para apostas de dois ou mais jogos.'],
            ['limite_simples', 'Limite simples', 'dinheiro', 'Limite em reais para apostas de um jogo só.'],
        ],
    },
    {
        titulo: 'Apostas',
        campos: [
            ['realizar_aposta', 'Realizar aposta', 'booleano', 'Permite registrar apostas.'],
            ['cancelar_aposta', 'Cancelar aposta', 'booleano', 'Permite cancelar as próprias apostas.'],
            ['tempo_cancelamento_aposta', 'Tempo para cancelar (min)', 'inteiro', 'Minutos depois da aposta em que ainda pode cancelar.'],
            ['valor_minimo_aposta', 'Valor mínimo', 'dinheiro', 'Menor valor por aposta.'],
            ['valor_maximo_aposta', 'Valor máximo', 'dinheiro', 'Maior valor por aposta.'],
            ['odd_minima', 'Odd mínima', 'numero', 'Menor cotação total aceita.'],
            ['premio_maximo', 'Prêmio máximo', 'dinheiro', 'Maior prêmio por aposta.'],
            ['multiplicador', 'Multiplicador', 'inteiro', 'Prêmio máximo em vezes o valor apostado.'],
            ['quantidade_minima_opcoes', 'Nº mínimo de jogos', 'inteiro', 'Menor quantidade de palpites por aposta.'],
            ['quantidade_maxima_opcoes', 'Nº máximo de jogos', 'inteiro', 'Maior quantidade de palpites por aposta.'],
            ['ganho_multiplo_palpites', 'Ganho por múltiplos (%)', 'numero', 'Acréscimo no prêmio das apostas com vários palpites.'],
        ],
    },
    {
        titulo: 'Ao vivo',
        campos: [
            ['ao_vivo_habilitado', 'Ao vivo', 'booleano', 'Permite apostar no ao vivo.'],
            ['minuto_limite_ao_vivo', 'Minuto limite', 'inteiro', 'Último minuto do jogo para apostar ao vivo.'],
            ['cotacao_maxima_ao_vivo', 'Cotação máxima', 'numero', 'Maior cotação aceita no ao vivo.'],
            ['delay_ao_vivo', 'Delay (s)', 'inteiro', 'Segundos de espera para confirmar a aposta ao vivo.'],
        ],
    },
    {
        titulo: 'Jogos',
        campos: [
            ['periodo_jogos', 'Período de jogos', 'periodo', 'Até quando os jogos aparecem para apostar.'],
            ['data_travamento_sistema', 'Data de travamento', 'data', 'A partir desta data e hora o vendedor não aposta mais.'],
            ['apostar_jogadores', 'Apostar em jogadores', 'booleano', 'Libera os mercados de jogadores.'],
            ['apostar_outros_esportes', 'Outros esportes', 'booleano', 'Libera esportes além do futebol.'],
            ['esportes_permitidos', 'Esportes permitidos', 'esportes', 'Esportes que aparecem para o vendedor.'],
        ],
    },
    {
        titulo: 'Criar Aposta',
        campos: [
            ['criar_aposta_habilitado', 'Criar Aposta', 'booleano', 'Permite vários palpites do mesmo jogo.'],
            ['desconto_criar_aposta', 'Desconto (%)', 'numero', 'Desconto sobre a cotação do Criar Aposta.'],
            ['quantidade_minima_criar_aposta', 'Mínimo de palpites', 'inteiro', 'Menor quantidade de palpites no bloco.'],
            ['quantidade_maxima_criar_aposta', 'Máximo de palpites', 'inteiro', 'Maior quantidade de palpites no bloco.'],
        ],
    },
    {
        titulo: 'Bilhete',
        campos: [
            ['mensagem_bilhete', 'Mensagem do bilhete', 'texto', 'Texto impresso no fim do bilhete.'],
        ],
    },
];

const periodos = ['Hoje', 'Amanhã', 'Depois de amanhã'];

// "2026-10-20T15:00:00.000000Z" → "2026-10-20T12:00" (Brasília) para o campo de data e hora
const para_campo_data = (valor) => {
    if (!valor) return '';

    const partes = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(valor)).map((parte) => [parte.type, parte.value]));

    return `${partes.year}-${partes.month}-${partes.day}T${partes.hour}:${partes.minute}`;
};

// limites e regras de aposta; em_massa (Regras da equipe/rede) troca as chaves por "Não alterar /
// Sim / Não", porque o campo em branco significa manter o valor de cada vendedor
export default function BettingRulesFields({ valores, ao_mudar, somente_leitura = false, erros = {}, em_massa = false }) {
    const entrada = ([chave, , tipo]) => {
        const valor = valores[chave];
        const comum = { id: `regra-${chave}`, disabled: somente_leitura, $erro: Boolean(erros[chave]) };

        // Sim/Não numa lista, como no antigo; em massa ganha "Não alterar" (manter o de cada vendedor)
        if (tipo === 'booleano') {
            const atual = em_massa && valor === undefined ? '' : String(Boolean(valor));

            return (
                <Select {...comum} value={atual} onChange={(evento) => ao_mudar(chave, evento.target.value === '' ? undefined : evento.target.value === 'true')}>
                    {em_massa && <option value="">Não alterar</option>}
                    <option value="true">Sim</option>
                    <option value="false">Não</option>
                </Select>
            );
        }

        if (tipo === 'periodo') {
            return (
                <Select {...comum} value={valor ?? ''} onChange={(evento) => ao_mudar(chave, evento.target.value || undefined)}>
                    {em_massa && <option value="">Não alterar</option>}
                    {periodos.map((periodo) => <option key={periodo} value={periodo}>{periodo}</option>)}
                </Select>
            );
        }

        if (tipo === 'data') {
            return <Input {...comum} type="datetime-local" value={para_campo_data(valor)} onChange={(evento) => ao_mudar(chave, evento.target.value || null)} />;
        }

        if (tipo === 'texto') {
            return <TextArea {...comum} rows={2} maxLength={500} value={valor ?? ''} onChange={(evento) => ao_mudar(chave, evento.target.value)} />;
        }

        if (tipo === 'esportes') {
            const marcados = valor ?? [];
            const marcado = (esporte) => marcados.some((item) => normalizar(item) === normalizar(esporte.chave));
            const visiveis = somente_leitura ? esportes_existentes.filter(marcado) : esportes_existentes;

            // ao trocar, grava só os esportes da lista (nomes digitados à mão no antigo saem aqui)
            const alternar = (esporte) => ao_mudar(chave, esportes_existentes
                .filter((item) => (item.chave === esporte.chave ? !marcado(item) : marcado(item)))
                .map((item) => item.chave));

            return (
                <Chips id={comum.id} role="group" aria-label="Esportes permitidos">
                    {visiveis.map((esporte) => (
                        <Chip
                            key={esporte.chave}
                            type="button"
                            aria-pressed={marcado(esporte)}
                            disabled={somente_leitura}
                            $selecionado={marcado(esporte)}
                            onClick={() => alternar(esporte)}
                        >
                            <i className="material-icons" aria-hidden="true">{marcado(esporte) ? 'check' : esporte.icone}</i>
                            {esporte.titulo}
                        </Chip>
                    ))}
                    {!visiveis.length && <span>Nenhum esporte.</span>}
                </Chips>
            );
        }

        return (
            <Input
                {...comum}
                type="number"
                inputMode={tipo === 'inteiro' ? 'numeric' : 'decimal'}
                step={tipo === 'inteiro' ? '1' : '0.01'}
                min="0"
                value={valor ?? ''}
                placeholder={tipo === 'dinheiro' ? 'R$' : ''}
                onChange={(evento) => ao_mudar(chave, evento.target.value)}
            />
        );
    };

    return grupos.map((grupo) => (
        <Group key={grupo.titulo}>
            <GroupTitle>{grupo.titulo}</GroupTitle>
            <Grid>
                {grupo.campos.map((definicao) => {
                    const [chave, rotulo, tipo, titulo] = definicao;

                    return (
                        <Field key={chave} title={titulo} $largo={tipo === 'esportes' || tipo === 'texto'}>
                            <Label htmlFor={`regra-${chave}`}>{rotulo}</Label>
                            {entrada(definicao)}
                            {erros[chave] && <Error>{erros[chave]}</Error>}
                        </Field>
                    );
                })}
            </Grid>
        </Group>
    ));
}
