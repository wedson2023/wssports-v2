import { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';

import Spinner from '../Spinner';
import ToggleSwitch from '../ToggleSwitch';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { agrupar_permissoes } from '../../utils/permissions';
import {
    AllToggle, Badge, Chevron, Description, Empty, Group, GroupHeader, GroupIcon, GroupTitle, Item, Items, Name, Search, SearchBox, Summary,
    Texts, Toolbar,
} from './styles';

// ícone de cada grupo de permissões
const icones_grupos = {
    Usuários: 'people',
    Clientes: 'person',
    'Confrontos e cotações': 'sports_soccer',
    Apostas: 'receipt_long',
    Especiais: 'star',
    'Site e configurações': 'settings',
    Pagamentos: 'payments',
    Cassino: 'casino',
    Afiliados: 'handshake',
    Dashboard: 'bar_chart',
    Outras: 'more_horiz',
};

// texto sem acento e em minúsculas, para a busca
const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

// aviso curto no canto, sem bloquear a tela
const avisar = (icone, texto) => Swal.fire({ toast: true, position: 'top-end', icon: icone, title: texto, showConfirmButton: false, timer: 2500 });

// permissões de um usuário em grupos que abrem e fecham, com busca, contagem e chave "Todas" (no
// lugar das "travas" Sim/Não do antigo): mostra só as disponíveis que o servidor devolve (o que quem
// edita tem e a função do usuário pode usar) e grava cada mudança na hora (spec 015, FR-017 a FR-019)
export default function PermissionsPanel({ usuario_id }) {
    const { sessao } = useSessao();
    const [dados, definir_dados] = useState(null);
    const [gravando, definir_gravando] = useState([]);
    const [busca, definir_busca] = useState('');
    const [abertos, definir_abertos] = useState(null);
    const cabecalhos = { Authorization: `Bearer ${sessao?.token}` };

    useEffect(() => {
        let ativo = true;

        api.get(`/usuarios/${usuario_id}/permissoes`, { headers: cabecalhos })
            .then(({ data }) => ativo && definir_dados(data))
            .catch((erro) => ativo && definir_dados({ erro }));

        return () => {
            ativo = false;
        };
    }, [usuario_id]);

    const grupos = useMemo(() => (dados?.disponiveis ? agrupar_permissoes(dados.disponiveis) : []), [dados]);

    // o primeiro grupo começa aberto
    useEffect(() => {
        if (grupos.length && abertos === null) definir_abertos([grupos[0].grupo]);
    }, [grupos]);

    // grupos com a busca aplicada (nome ou descrição); buscando, todos os que combinam ficam abertos
    const termo = normalizar(busca.trim());
    const filtrados = grupos
        .map(({ grupo, itens }) => ({
            grupo,
            itens: termo ? itens.filter((item) => normalizar(`${item.nome} ${item.descricao}`).includes(termo)) : itens,
        }))
        .filter(({ itens }) => itens.length);

    const ligada = (chave) => dados.permissoes.includes(chave);
    const total_ativas = (dados?.disponiveis ?? []).filter((chave) => dados.permissoes.includes(chave)).length;

    // grava uma permissão; em erro, a chave fica como estava e aparece a mensagem do servidor
    const alterar = async (chave, ligar) => {
        definir_gravando((atuais) => [...atuais, chave]);

        try {
            const { data } = ligar
                ? await api.post(`/usuarios/${usuario_id}/permissoes`, { permissao: chave }, { headers: cabecalhos })
                : await api.delete(`/usuarios/${usuario_id}/permissoes/${encodeURIComponent(chave)}`, { headers: cabecalhos });

            definir_dados(data);

            return true;
        } catch (erro) {
            avisar('error', erro.response?.data?.message ?? 'Não foi possível alterar a permissão.');

            return false;
        } finally {
            definir_gravando((atuais) => atuais.filter((item) => item !== chave));
        }
    };

    const alterar_uma = async (chave, ligar) => {
        if (await alterar(chave, ligar)) avisar('success', ligar ? 'Permissão concedida.' : 'Permissão retirada.');
    };

    // "Todas" do grupo: só as que precisam mudar, uma de cada vez
    const alterar_grupo = async (itens, ligar) => {
        const mudar = itens.filter((item) => ligada(item.chave) !== ligar);
        let falhas = 0;

        for (const item of mudar) {
            if (!(await alterar(item.chave, ligar))) falhas += 1;
        }

        if (mudar.length && !falhas) avisar('success', ligar ? 'Permissões do grupo concedidas.' : 'Permissões do grupo retiradas.');
    };

    const alternar_grupo = (grupo) => definir_abertos((atuais) => (
        (atuais ?? []).includes(grupo) ? atuais.filter((item) => item !== grupo) : [...(atuais ?? []), grupo]
    ));

    if (!dados) return <Empty><Spinner tamanho={30} cor="currentColor" /></Empty>;

    if (dados.erro) return <Empty>{dados.erro.response?.data?.message ?? 'Não foi possível carregar as permissões.'}</Empty>;

    if (!grupos.length) return <Empty>Não há permissões que você possa conceder a este usuário.</Empty>;

    return (
        <>
            <Toolbar>
                <SearchBox>
                    <i className="material-icons" aria-hidden="true">search</i>
                    <Search
                        type="search"
                        placeholder="Buscar permissão"
                        value={busca}
                        onChange={(evento) => definir_busca(evento.target.value)}
                        aria-label="Buscar permissão"
                    />
                </SearchBox>
                <Summary><strong>{total_ativas}</strong> de {dados.disponiveis.length} permissões ativas</Summary>
            </Toolbar>

            {!filtrados.length && <Empty>Nenhuma permissão encontrada para “{busca}”.</Empty>}

            {filtrados.map(({ grupo, itens }) => {
                const aberto = Boolean(termo) || (abertos ?? []).includes(grupo);
                const ativas = itens.filter((item) => ligada(item.chave)).length;
                const todas = ativas === itens.length;

                return (
                    <Group key={grupo} $aberto={aberto}>
                        <GroupHeader>
                            <button type="button" aria-expanded={aberto} onClick={() => alternar_grupo(grupo)}>
                                <GroupIcon>{icones_grupos[grupo] ?? 'more_horiz'}</GroupIcon>
                                <GroupTitle>{grupo}</GroupTitle>
                                <Badge $completo={todas} $vazio={ativas === 0}>{ativas}/{itens.length}</Badge>
                                <Chevron>{aberto ? 'expand_less' : 'expand_more'}</Chevron>
                            </button>
                            <AllToggle title={todas ? 'Desmarcar todas do grupo' : 'Marcar todas do grupo'}>
                                <span>Todas</span>
                                <ToggleSwitch
                                    ligado={todas}
                                    carregando={itens.some((item) => gravando.includes(item.chave))}
                                    rotulo={`Todas de ${grupo}`}
                                    ao_trocar={(ligar) => alterar_grupo(itens, ligar)}
                                />
                            </AllToggle>
                        </GroupHeader>
                        {aberto && (
                            <Items>
                                {itens.map((item) => (
                                    <Item
                                        key={item.chave}
                                        $ligada={ligada(item.chave)}
                                        onClick={() => !gravando.includes(item.chave) && alterar_uma(item.chave, !ligada(item.chave))}
                                    >
                                        <Texts>
                                            <Name>{item.nome}</Name>
                                            {item.descricao && <Description>{item.descricao}</Description>}
                                        </Texts>
                                        <span onClick={(evento) => evento.stopPropagation()}>
                                            <ToggleSwitch
                                                ligado={ligada(item.chave)}
                                                carregando={gravando.includes(item.chave)}
                                                rotulo={item.nome}
                                                ao_trocar={(ligar) => alterar_uma(item.chave, ligar)}
                                            />
                                        </span>
                                    </Item>
                                ))}
                            </Items>
                        )}
                    </Group>
                );
            })}
        </>
    );
}
