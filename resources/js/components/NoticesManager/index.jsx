import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActionsMenu from '../ActionsMenu';
import ImagePreviewModal from '../ImagePreviewModal';
import NoticeFormModal from '../NoticeFormModal';
import Pagination from '../Pagination';
import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import {
    Actions, AddTile, Body, Chip, Gallery, Info, Loading, Media, Tile,
} from './styles';

const POR_PAGINA = 20;

const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

// "2026-10-27T13:00:00-03:00" → "27/10/2026 13:00" (a API já manda em Brasília)
const data_curta = (data) => {
    if (!data) return null;

    const [dia, hora] = data.split('T');
    const [ano, mes, numero] = dia.split('-');

    return `${numero}/${mes}/${ano} ${hora.slice(0, 5)}`;
};

const periodo = (aviso) => {
    const inicio = data_curta(aviso.inicio_em);
    const fim = data_curta(aviso.fim_em) ?? 'Sem fim';

    return inicio ? `${inicio} — ${fim}` : `Até ${fim === 'Sem fim' ? 'ser desativado' : fim}`;
};

// avisos da banca, o antigo pop-up (spec 021, US3, FR-015), na mesma galeria dos banners: imagem
// quadrada, título, período e situação, com ver, editar, ativar/desativar e excluir
export default function NoticesManager() {
    const { tema } = usePage().props;
    const { sessao } = useSessao();
    const token = sessao?.token;
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };
    const [pagina, definir_pagina] = useState(1);
    const [lista, definir_lista] = useState({ itens: [], ultima: 1, carregando: true });
    const [recarga, definir_recarga] = useState(0);
    const [edicao, definir_edicao] = useState(null); // null fechado; {} cadastro; aviso edição
    const [vendo, definir_vendo] = useState(null);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    useEffect(() => {
        if (!token) return undefined;

        let ativo = true;
        definir_lista((atual) => ({ ...atual, carregando: true }));

        api.get('/avisos', { params: { por_pagina: POR_PAGINA, page: pagina }, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_lista({ itens: data.data, ultima: data.meta?.last_page ?? 1, carregando: false }))
            .catch((falha) => {
                if (!ativo) return;
                definir_lista((atual) => ({ ...atual, carregando: false }));
                alerta_erro(falha);
            });

        return () => {
            ativo = false;
        };
    }, [token, pagina, recarga]);

    const alterar_ativo = async (aviso) => {
        try {
            await api.put(`/avisos/${aviso.id}`, { ativo: !aviso.ativo }, autorizacao);
            avisar(aviso.ativo ? 'Aviso desativado.' : 'Aviso ativado.');
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            recarregar();
        }
    };

    const excluir = async (aviso) => {
        if (!(await confirmar('Excluir este aviso? Ele deixa de aparecer no site.', tema.temas))) return;

        try {
            await api.delete(`/avisos/${aviso.id}`, autorizacao);
            avisar('Aviso excluído.');
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            recarregar();
        }
    };

    const acoes = (aviso) => [
        { chave: 'ver', icone: 'visibility', titulo: 'Ver', tom: '#1e88e5', ao_clicar: () => definir_vendo(aviso) },
        { chave: 'editar', icone: 'edit', titulo: 'Editar', tom: '#1e88e5', ao_clicar: () => definir_edicao(aviso) },
        aviso.ativo
            ? { chave: 'desativar', icone: 'toggle_off', titulo: 'Desativar', tom: '#757575', ao_clicar: () => alterar_ativo(aviso) }
            : { chave: 'ativar', icone: 'toggle_on', titulo: 'Ativar', tom: '#43a047', ao_clicar: () => alterar_ativo(aviso) },
        { chave: 'excluir', icone: 'delete_outline', titulo: 'Excluir', tom: '#e53935', ao_clicar: () => excluir(aviso) },
    ];

    const salvo = (mensagem) => {
        definir_edicao(null);
        avisar(mensagem);
        recarregar();
    };

    return (
        <>
            {lista.carregando && !lista.itens.length ? <Loading><Spinner tamanho={28} cor={tema.temas} /></Loading> : (
                <Gallery $minimo={170} $ocupada={lista.carregando} aria-busy={lista.carregando}>
                    {lista.itens.map((aviso) => (
                        <Tile key={aviso.id}>
                            <Media type="button" $proporcao="1 / 1" $ativo={aviso.ativo} title="Ver em tamanho real" onClick={() => definir_vendo(aviso)}>
                                <img src={aviso.imagem} alt={aviso.titulo || 'Aviso da banca'} />
                                <Chip $ativo={aviso.ativo}>{aviso.ativo ? 'Ativo' : 'Inativo'}</Chip>
                            </Media>
                            <Body>
                                <Info>
                                    <strong>{aviso.titulo || 'Sem título'}</strong>
                                    <small>{periodo(aviso)}</small>
                                </Info>
                                <Actions>
                                    <ActionsMenu acoes={acoes(aviso)} rotulo="Ações do aviso" />
                                </Actions>
                            </Body>
                        </Tile>
                    ))}
                    <li>
                        <AddTile type="button" onClick={() => definir_edicao({})}>
                            <i className="material-icons" aria-hidden="true">add_photo_alternate</i>
                            <strong>Cadastrar aviso</strong>
                            <small>{lista.itens.length ? 'Imagem de 500x500' : 'Nenhum aviso cadastrado'}</small>
                        </AddTile>
                    </li>
                </Gallery>
            )}

            <Pagination pagina={pagina} ultima={lista.ultima} ao_trocar={definir_pagina} />

            <NoticeFormModal aberto={edicao !== null} aviso={edicao?.id ? edicao : null} token={token} ao_fechar={() => definir_edicao(null)} ao_salvar={salvo} />
            <ImagePreviewModal imagem={vendo?.imagem} titulo={vendo?.titulo || 'Aviso da banca'} link={vendo?.link} ao_fechar={() => definir_vendo(null)} />
        </>
    );
}
