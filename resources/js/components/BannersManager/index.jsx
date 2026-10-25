import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

import ActionsMenu from '../ActionsMenu';
import BannerFormModal from '../BannerFormModal';
import ImagePreviewModal from '../ImagePreviewModal';
import Pagination from '../Pagination';
import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import {
    Actions, AddTile, Body, Chip, Gallery, Info, Loading, Media, Move, Position, Tile,
} from './styles';

const POR_PAGINA = 20;

// passo da ordem ao renumerar a página (R-18): sobra espaço para cadastrar no meio depois
const PASSO_ORDEM = 10;

const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

// banners do carrossel da página inicial (spec 021, US3, FR-014) em galeria: cada cartão com a
// imagem grande, a posição no carrossel, a situação, mover para antes/depois e o menu com ver,
// editar, ativar/desativar e excluir; o último cartão cadastra um novo
export default function BannersManager() {
    const { tema } = usePage().props;
    const { sessao } = useSessao();
    const token = sessao?.token;
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };
    const [pagina, definir_pagina] = useState(1);
    const [lista, definir_lista] = useState({ itens: [], ultima: 1, carregando: true });
    const [recarga, definir_recarga] = useState(0);
    const [edicao, definir_edicao] = useState(null); // null fechado; {} cadastro; banner edição
    const [vendo, definir_vendo] = useState(null);
    const [movendo, definir_movendo] = useState(false);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    useEffect(() => {
        if (!token) return undefined;

        let ativo = true;
        definir_lista((atual) => ({ ...atual, carregando: true }));

        api.get('/banners', { params: { por_pagina: POR_PAGINA, page: pagina }, headers: { Authorization: `Bearer ${token}` } })
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

    const atualizar = async (banner, dados, mensagem) => {
        try {
            await api.put(`/banners/${banner.id}`, dados, autorizacao);
            if (mensagem) avisar(mensagem);
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            recarregar();
        }
    };

    // troca o banner de lugar com o vizinho e grava a ordem da página de 10 em 10 (R-18), só para
    // quem mudou de número
    const mover = async (indice, direcao) => {
        const itens = [...lista.itens];
        const destino = indice + direcao;
        [itens[indice], itens[destino]] = [itens[destino], itens[indice]];

        const base = (pagina - 1) * POR_PAGINA * PASSO_ORDEM;
        const mudancas = itens
            .map((banner, posicao) => ({ banner, ordem: base + (posicao + 1) * PASSO_ORDEM }))
            .filter(({ banner, ordem }) => banner.ordem !== ordem);

        definir_movendo(true);
        definir_lista((atual) => ({ ...atual, itens }));

        try {
            await Promise.all(mudancas.map(({ banner, ordem }) => api.put(`/banners/${banner.id}`, { ordem }, autorizacao)));
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            definir_movendo(false);
            recarregar();
        }
    };

    const excluir = async (banner) => {
        if (!(await confirmar('Excluir este banner? Ele sai do carrossel do site.', tema.temas))) return;

        try {
            await api.delete(`/banners/${banner.id}`, autorizacao);
            avisar('Banner excluído.');
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            recarregar();
        }
    };

    const acoes = (banner) => [
        { chave: 'ver', icone: 'visibility', titulo: 'Ver', tom: '#1e88e5', ao_clicar: () => definir_vendo(banner) },
        { chave: 'editar', icone: 'edit', titulo: 'Editar', tom: '#1e88e5', ao_clicar: () => definir_edicao(banner) },
        banner.ativo
            ? { chave: 'desativar', icone: 'toggle_off', titulo: 'Desativar', tom: '#757575', ao_clicar: () => atualizar(banner, { ativo: false }, 'Banner desativado.') }
            : { chave: 'ativar', icone: 'toggle_on', titulo: 'Ativar', tom: '#43a047', ao_clicar: () => atualizar(banner, { ativo: true }, 'Banner ativado.') },
        { chave: 'excluir', icone: 'delete_outline', titulo: 'Excluir', tom: '#e53935', ao_clicar: () => excluir(banner) },
    ];

    const salvo = (mensagem) => {
        definir_edicao(null);
        avisar(mensagem);
        recarregar();
    };

    return (
        <>
            {lista.carregando && !lista.itens.length ? <Loading><Spinner tamanho={28} cor={tema.temas} /></Loading> : (
                <Gallery $minimo={300} $ocupada={lista.carregando || movendo} aria-busy={lista.carregando || movendo}>
                    {lista.itens.map((banner, indice) => (
                        <Tile key={banner.id}>
                            <Media type="button" $proporcao="1280 / 405" $ativo={banner.ativo} title="Ver em tamanho real" onClick={() => definir_vendo(banner)}>
                                <img src={banner.imagem} alt={`Banner ${(pagina - 1) * POR_PAGINA + indice + 1}`} />
                                <Position>#{(pagina - 1) * POR_PAGINA + indice + 1}</Position>
                                <Chip $ativo={banner.ativo}>{banner.ativo ? 'Ativo' : 'Inativo'}</Chip>
                            </Media>
                            <Body>
                                <Info>
                                    <strong>{banner.link || 'Sem link'}</strong>
                                </Info>
                                <Actions>
                                    <Move type="button" title="Mover para antes" aria-label="Mover para antes" disabled={movendo || indice === 0} onClick={() => mover(indice, -1)}>
                                        <i className="material-icons" aria-hidden="true">arrow_back</i>
                                    </Move>
                                    <Move type="button" title="Mover para depois" aria-label="Mover para depois" disabled={movendo || indice === lista.itens.length - 1} onClick={() => mover(indice, 1)}>
                                        <i className="material-icons" aria-hidden="true">arrow_forward</i>
                                    </Move>
                                    <ActionsMenu acoes={acoes(banner)} rotulo="Ações do banner" />
                                </Actions>
                            </Body>
                        </Tile>
                    ))}
                    <li>
                        <AddTile type="button" onClick={() => definir_edicao({})}>
                            <i className="material-icons" aria-hidden="true">add_photo_alternate</i>
                            <strong>Cadastrar banner</strong>
                            <small>{lista.itens.length ? 'Imagem de 1280x405' : 'Nenhum banner: o carrossel não aparece no site'}</small>
                        </AddTile>
                    </li>
                </Gallery>
            )}

            <Pagination pagina={pagina} ultima={lista.ultima} ao_trocar={definir_pagina} />

            <BannerFormModal aberto={edicao !== null} banner={edicao?.id ? edicao : null} token={token} ao_fechar={() => definir_edicao(null)} ao_salvar={salvo} />
            <ImagePreviewModal imagem={vendo?.imagem} titulo="Banner" link={vendo?.link} ao_fechar={() => definir_vendo(null)} />
        </>
    );
}
