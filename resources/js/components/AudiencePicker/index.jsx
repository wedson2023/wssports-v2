import { useEffect, useRef, useState } from 'react';

import api from '../../utils/api';
import { Current, Dropdown, Empty, Option, Picker, Search, SearchBox, Section } from './styles';

// grupos de usuários da rede na lista, na ordem da hierarquia
const funcoes = [
    { chave: 'Admin', titulo: 'Admin' },
    { chave: 'Supervisor', titulo: 'Supervisores' },
    { chave: 'Gerente', titulo: 'Gerentes' },
    { chave: 'Vendedor', titulo: 'Vendedores' },
];

// ícone e texto do público escolhido
const descrever = (publico) => {
    if (!publico) return { icone: 'groups', texto: 'Escolha um público' };
    if (publico.tipo === 'clientes') return { icone: 'public', texto: 'Clientes do site' };
    if (publico.tipo === 'cliente') return { icone: 'person', texto: `Cliente: ${publico.nome}` };

    return { icone: 'badge', texto: `${publico.nome} · ${publico.funcao}` };
};

// escolha do público da aba Público (spec 016, FR-011): "Clientes do site", um cliente específico
// (busca) ou um usuário da rede agrupado por função, tudo escolhido de lista (constituição v3.7.0)
export default function AudiencePicker({ publico, ao_escolher, pode_clientes, pode_usuarios, token }) {
    const [aberto, definir_aberto] = useState(false);
    const [busca, definir_busca] = useState('');
    const [usuarios, definir_usuarios] = useState([]);
    const [clientes, definir_clientes] = useState([]);
    const [carregando, definir_carregando] = useState(false);
    const caixa = useRef(null);
    const atual = descrever(publico);

    // fecha ao clicar fora
    useEffect(() => {
        if (!aberto) return undefined;

        const fora = (evento) => !caixa.current?.contains(evento.target) && definir_aberto(false);

        document.addEventListener('pointerdown', fora);

        return () => document.removeEventListener('pointerdown', fora);
    }, [aberto]);

    // usuários da rede e clientes com a busca (clientes só a partir de 2 letras)
    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        const cabecalhos = { Authorization: `Bearer ${token}` };
        const espera = window.setTimeout(async () => {
            definir_carregando(true);

            try {
                const [lista_usuarios, lista_clientes] = await Promise.all([
                    pode_usuarios ? api.get('/cotacoes/publicos', { params: { busca: busca || undefined, por_pagina: 100 }, headers: cabecalhos }) : null,
                    pode_clientes && busca.trim().length >= 2 ? api.get('/cotacoes/clientes', { params: { busca, por_pagina: 20 }, headers: cabecalhos }) : null,
                ]);

                if (!ativo) return;

                definir_usuarios(lista_usuarios?.data.data ?? []);
                definir_clientes(lista_clientes?.data.data ?? []);
            } finally {
                if (ativo) definir_carregando(false);
            }
        }, 300);

        return () => {
            ativo = false;
            window.clearTimeout(espera);
        };
    }, [aberto, busca, pode_usuarios, pode_clientes, token]);

    const escolher = (novo) => {
        definir_aberto(false);
        definir_busca('');
        ao_escolher(novo);
    };

    return (
        <Picker ref={caixa}>
            <Current type="button" aria-haspopup="listbox" aria-expanded={aberto} onClick={() => definir_aberto((valor) => !valor)}>
                <i className="material-icons" aria-hidden="true">{atual.icone}</i>
                <span>{atual.texto}</span>
                <i className="material-icons" aria-hidden="true">{aberto ? 'expand_less' : 'expand_more'}</i>
            </Current>
            {aberto && (
                <Dropdown role="listbox">
                    <SearchBox>
                        <i className="material-icons" aria-hidden="true">search</i>
                        <Search
                            autoFocus
                            type="search"
                            placeholder={pode_clientes ? 'Buscar usuário ou cliente' : 'Buscar usuário'}
                            value={busca}
                            onChange={(evento) => definir_busca(evento.target.value)}
                            aria-label="Buscar público"
                        />
                    </SearchBox>

                    {pode_clientes && !busca && (
                        <Section>
                            <small>Site</small>
                            <Option type="button" role="option" $ativo={publico?.tipo === 'clientes'} onClick={() => escolher({ tipo: 'clientes' })}>
                                <i className="material-icons" aria-hidden="true">public</i>
                                <span>Clientes do site<small>regra geral, vale também para visitantes</small></span>
                            </Option>
                        </Section>
                    )}

                    {funcoes.map(({ chave, titulo }) => {
                        const do_grupo = usuarios.filter((usuario) => usuario.funcao === chave);

                        return do_grupo.length ? (
                            <Section key={chave}>
                                <small>{titulo}</small>
                                {do_grupo.map((usuario) => (
                                    <Option
                                        key={usuario.id}
                                        type="button"
                                        role="option"
                                        $ativo={publico?.tipo === 'usuario' && publico.id === usuario.id}
                                        onClick={() => escolher({ tipo: 'usuario', id: usuario.id, nome: usuario.nome, funcao: usuario.funcao })}
                                    >
                                        <i className="material-icons" aria-hidden="true">badge</i>
                                        <span>{usuario.nome}<small>{usuario.login}{usuario.superior ? ` · ${usuario.superior.nome}` : ''}</small></span>
                                    </Option>
                                ))}
                            </Section>
                        ) : null;
                    })}

                    {pode_clientes && (
                        <Section>
                            <small>Cliente específico</small>
                            {busca.trim().length < 2 && <Empty>Digite ao menos 2 letras para buscar um cliente.</Empty>}
                            {clientes.map((cliente) => (
                                <Option
                                    key={cliente.id}
                                    type="button"
                                    role="option"
                                    $ativo={publico?.tipo === 'cliente' && publico.id === cliente.id}
                                    onClick={() => escolher({ tipo: 'cliente', id: cliente.id, nome: cliente.nome })}
                                >
                                    <i className="material-icons" aria-hidden="true">person</i>
                                    <span>{cliente.nome}<small>{cliente.telefone}{cliente.email ? ` · ${cliente.email}` : ''}</small></span>
                                </Option>
                            ))}
                        </Section>
                    )}

                    {carregando && <Empty>Carregando...</Empty>}
                    {!carregando && !usuarios.length && !clientes.length && busca && <Empty>Nada encontrado para “{busca}”.</Empty>}
                </Dropdown>
            )}
        </Picker>
    );
}
