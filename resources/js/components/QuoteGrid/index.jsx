import { useMemo, useState } from 'react';

import { confirmar } from '../../utils/alerts';
import { formatar_ajuste, formatar_cotacao, grupos_mercado, ler_numero, normalizar } from '../../utils/quotes';
import {
    ApplyBox, Badge, Chevron, Field, FieldBox, Group, GroupApply, GroupHeader, GroupTitle, Hint, Input, Item, Items, Lock, Name, Provider, Search,
    SearchBox, Suffix, Toolbar, ToolbarButton, ToolbarInfo,
} from './styles';

// sufixo do campo por modo
const sufixos = { porcentagem: '%', jogo: '', teto: '' };

// valor gravado mostrado como "era ..." quando há pendente
const formatar_valor = (modo, valor) => {
    if (valor === null || valor === undefined) return 'sem ajuste';

    return modo === 'porcentagem' ? formatar_ajuste(valor) : formatar_cotacao(valor);
};

// grade de cotações agrupada por mercado (spec 016, FR-004 a FR-008): busca, "aplicar a todas"
// (geral e por grupo), grupos que abrem e fecham com a contagem de ajustadas, destaque de pendente,
// erro no campo e cadeado; modos porcentagem, jogo (cotação desejada, com a do provedor ao lado) e teto
export default function QuoteGrid({
    nomes, grade, modo = 'porcentagem', provedor = null, cadeados = {}, pode_trancar = false, ao_alternar_cadeado = null, ao_focar = null,
    somente_leitura = false, cor = '#c40808',
}) {
    const codigos = useMemo(() => Object.keys(nomes ?? {}), [nomes]);
    const grupos = useMemo(() => grupos_mercado(codigos), [codigos]);
    const [busca, definir_busca] = useState('');
    const [abertos, definir_abertos] = useState(() => new Set(grupos.slice(0, 1).map((grupo) => grupo.chave)));
    const [valor_geral, definir_valor_geral] = useState('');
    const [valores_grupo, definir_valores_grupo] = useState({});
    const termo = normalizar(busca.trim());
    const sufixo = sufixos[modo];

    // grupos com a busca aplicada (nome da cotação ou título do mercado)
    const filtrados = grupos
        .map((grupo) => ({
            ...grupo,
            codigos: termo && !normalizar(grupo.titulo).includes(termo)
                ? grupo.codigos.filter((codigo) => normalizar(nomes[codigo]).includes(termo))
                : grupo.codigos,
        }))
        .filter((grupo) => grupo.codigos.length);

    const tem_ajuste = (codigo) => {
        const valor = grade.valor_atual(codigo);

        return valor !== null && valor !== undefined && valor !== 0;
    };

    const total_ajustadas = codigos.filter(tem_ajuste).length;

    const alternar_grupo = (chave) => definir_abertos((atuais) => {
        const proximos = new Set(atuais);

        if (proximos.has(chave)) proximos.delete(chave);
        else proximos.add(chave);

        return proximos;
    });

    // "aplicar a todas": confirma a quantidade e avisa quantas trancadas pulou
    const aplicar = async (lista, texto) => {
        const numero = ler_numero(texto);

        if (numero === null || Number.isNaN(numero)) return;

        const disponiveis = modo === 'jogo' ? lista.filter((codigo) => (provedor?.[codigo] ?? 0) > 0) : lista;
        const mostrar = modo === 'porcentagem' ? formatar_ajuste(numero) : formatar_cotacao(numero);

        if (!(await confirmar(`Aplicar ${mostrar} em ${disponiveis.length} cotações? Você ainda pode revisar antes de salvar.`, cor))) return;

        const pulados = grade.aplicar_a_todas(disponiveis, texto);

        if (pulados) await confirmar(`${pulados} cotação${pulados > 1 ? 'ões trancadas foram puladas' : ' trancada foi pulada'}.`, cor);
    };

    return (
        <>
            <Toolbar>
                <SearchBox>
                    <i className="material-icons" aria-hidden="true">search</i>
                    <Search
                        type="search"
                        placeholder="Buscar cotação ou mercado"
                        value={busca}
                        onChange={(evento) => definir_busca(evento.target.value)}
                        aria-label="Buscar cotação ou mercado"
                    />
                </SearchBox>
                {!somente_leitura && (
                    <ApplyBox>
                        <Input
                            inputMode="decimal"
                            placeholder={modo === 'porcentagem' ? 'Ex.: -5' : 'Ex.: 1,90'}
                            value={valor_geral}
                            onChange={(evento) => definir_valor_geral(evento.target.value)}
                            aria-label="Valor para aplicar a todas"
                        />
                        <ToolbarButton type="button" onClick={() => aplicar(filtrados.flatMap((grupo) => grupo.codigos), valor_geral)}>
                            Aplicar a todas
                        </ToolbarButton>
                    </ApplyBox>
                )}
                <ToolbarInfo>
                    <span><strong>{total_ajustadas}</strong> {total_ajustadas === 1 ? 'cotação com ajuste' : 'cotações com ajuste'}</span>
                    <ToolbarButton type="button" $leve onClick={() => definir_abertos(new Set(abertos.size ? [] : filtrados.map((grupo) => grupo.chave)))}>
                        {abertos.size ? 'Fechar grupos' : 'Abrir grupos'}
                    </ToolbarButton>
                </ToolbarInfo>
            </Toolbar>

            {!filtrados.length && <Hint>Nenhuma cotação encontrada para “{busca}”.</Hint>}

            {filtrados.map((grupo) => {
                const aberto = Boolean(termo) || abertos.has(grupo.chave);
                const ajustadas = grupo.codigos.filter(tem_ajuste).length;

                return (
                    <Group key={grupo.chave} $aberto={aberto}>
                        <GroupHeader>
                            <button type="button" aria-expanded={aberto} onClick={() => alternar_grupo(grupo.chave)}>
                                <GroupTitle>
                                    {grupo.titulo}
                                    <small>{grupo.aba}</small>
                                </GroupTitle>
                                <Badge $todas={ajustadas === grupo.codigos.length} $nenhuma={ajustadas === 0}>
                                    {ajustadas} de {grupo.codigos.length} ajustadas
                                </Badge>
                                <Chevron>{aberto ? 'expand_less' : 'expand_more'}</Chevron>
                            </button>
                        </GroupHeader>
                        {aberto && (
                            <Items>
                                {!somente_leitura && grupo.codigos.length > 1 && (
                                    <GroupApply>
                                        <span>Aplicar no grupo</span>
                                        <Input
                                            inputMode="decimal"
                                            placeholder={modo === 'porcentagem' ? '%' : 'cotação'}
                                            value={valores_grupo[grupo.chave] ?? ''}
                                            onChange={(evento) => definir_valores_grupo((atuais) => ({ ...atuais, [grupo.chave]: evento.target.value }))}
                                            aria-label={`Valor para aplicar no grupo ${grupo.titulo}`}
                                        />
                                        <ToolbarButton type="button" $leve onClick={() => aplicar(grupo.codigos, valores_grupo[grupo.chave])}>Aplicar</ToolbarButton>
                                    </GroupApply>
                                )}
                                {grupo.codigos.map((codigo) => {
                                    const pendente = codigo in grade.pendentes;
                                    const cadeado = cadeados[codigo];
                                    const trancada = Boolean(cadeado) && !cadeado.pode_destrancar;
                                    const cotacao_provedor = provedor?.[codigo] ?? 0;
                                    const indisponivel = modo === 'jogo' && cotacao_provedor <= 0;
                                    const atual = grade.valor_atual(codigo);
                                    const texto = pendente ? grade.pendentes[codigo] : (grade.original[codigo] ?? '');

                                    return (
                                        <Item key={codigo} $pendente={pendente} $erro={Boolean(grade.erros[codigo])}>
                                            <Name>
                                                {nomes[codigo]}
                                                {pendente && <small>era {formatar_valor(modo, grade.original[codigo])}</small>}
                                                {trancada && <small>trancada por {cadeado.trancado_por?.nome}</small>}
                                                {grade.erros[codigo] && <em>{grade.erros[codigo]}</em>}
                                            </Name>
                                            {modo === 'jogo' && (
                                                <Provider>
                                                    {indisponivel ? 'indisponível' : `Provedor ${formatar_cotacao(cotacao_provedor)}`}
                                                    {!indisponivel && atual !== null && <span>{formatar_ajuste(Math.round((atual - cotacao_provedor) * 100) / 100, '')}</span>}
                                                </Provider>
                                            )}
                                            <FieldBox>
                                                <Field $sinal={modo === 'porcentagem' ? Math.sign(atual ?? 0) : 0} $pendente={pendente} $erro={Boolean(grade.erros[codigo])}>
                                                    <input
                                                        inputMode="decimal"
                                                        value={texto === null ? '' : String(texto).replace('.', ',')}
                                                        placeholder={modo === 'jogo' && !indisponivel ? formatar_cotacao(cotacao_provedor) : '—'}
                                                        disabled={somente_leitura || trancada || indisponivel}
                                                        onFocus={() => ao_focar?.(codigo)}
                                                        onChange={(evento) => grade.mudar(codigo, evento.target.value)}
                                                        aria-label={nomes[codigo]}
                                                    />
                                                    {sufixo && <Suffix>{sufixo}</Suffix>}
                                                </Field>
                                                {(pode_trancar || cadeado) && (
                                                    <Lock
                                                        type="button"
                                                        $fechado={Boolean(cadeado)}
                                                        disabled={!pode_trancar || (cadeado && !cadeado.pode_destrancar)}
                                                        title={cadeado ? `Trancada por ${cadeado.trancado_por?.nome}` : 'Trancar para quem está abaixo'}
                                                        aria-label={cadeado ? `Destrancar ${nomes[codigo]}` : `Trancar ${nomes[codigo]}`}
                                                        onClick={() => ao_alternar_cadeado?.(codigo, !cadeado)}
                                                    >
                                                        <i className="material-icons" aria-hidden="true">{cadeado ? 'lock' : 'lock_open'}</i>
                                                    </Lock>
                                                )}
                                            </FieldBox>
                                        </Item>
                                    );
                                })}
                            </Items>
                        )}
                    </Group>
                );
            })}
        </>
    );
}
