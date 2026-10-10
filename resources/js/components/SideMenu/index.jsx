import { useState } from 'react';
import { usePage } from '@inertiajs/react';

import Backdrop from '../Backdrop';
import PanelHeader from '../PanelHeader';
import useTelaMobile from '../../hooks/useTelaMobile';
import { confirmar } from '../../utils/alerts';
import { limpar_dados_locais } from '../../utils/storage';
import { Championship, Container, Count, Country, Flag, Icon, Item, ItemLink, ItemTitle, Title } from './styles';

const bandeira_padrao = '/images/banceira_categoria_especial.png';

const url_bandeira = (bandeira) => (bandeira ? `https://api.oddbrasil.com/flags/${bandeira}.png` : bandeira_padrao);

// "Limpar cache" (FR-016): apaga os dados locais e o cache do aplicativo e recarrega, como no antigo
const limpar_cache = async (cor_confirmar) => {
    if (!(await confirmar('Tem certeza que deseja limpar o cache?', cor_confirmar))) return;

    limpar_dados_locais();

    if ('caches' in window) {
        const chaves = await caches.keys();
        await Promise.all(chaves.map((chave) => caches.delete(chave)));
    }

    setTimeout(() => window.location.reload(), 150);
};

// opções da tabela de jogos do vendedor, como no sistema antigo
const opcoes_tabela = [
    { chave: 'hoje', titulo: 'Jogos de Hoje' },
    { chave: 'amanha', titulo: 'Jogos de Amanhã' },
    { chave: 'campeonatos', titulo: 'Jogos por Campeonatos' },
];

// menu: coluna no desktop e gaveta no mobile, com os itens fixos e os campeonatos por país (FR-013);
// com sessão de vendedor, os itens de impressão (só no mobile) e a tabela (spec 006, FR-005)
export default function SideMenu({
    paises,
    aberto,
    ao_fechar,
    ao_escolher_campeonato,
    ao_abrir_ao_vivo = null,
    vendedor = false,
    impressao = null,
    ao_alternar_modo = null,
    ao_alternar_largura = null,
    ao_escolher_tabela = null,
}) {
    const { indicadores, tema } = usePage().props;
    const e_mobile = useTelaMobile();
    // "Tabela" troca pelas três opções ao ser tocada, como no sistema antigo
    const [tabela_aberta, definir_tabela_aberta] = useState(false);

    const escolher_campeonato = (campeonato) => {
        ao_escolher_campeonato(campeonato);

        if (e_mobile) ao_fechar();
    };

    const escolher_tabela = (opcao) => {
        definir_tabela_aberta(false);
        ao_fechar();
        ao_escolher_tabela(opcao);
    };

    return (
        <>
            {e_mobile && <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />}
            <Container $aberto={aberto}>
                <PanelHeader titulo="Menu" ao_fechar={ao_fechar} />

                {vendedor && e_mobile && impressao && (
                    <>
                        <Item onClick={ao_alternar_modo}>
                            <Icon>print</Icon>
                            <ItemTitle>Impressão: {impressao.modo}</ItemTitle>
                        </Item>
                        <Item onClick={ao_alternar_largura}>
                            <Icon>feed</Icon>
                            <ItemTitle>Largura: {impressao.largura} mm</ItemTitle>
                        </Item>
                    </>
                )}

                {/* sem a barra de esportes, o ao vivo fica no menu, como no sistema antigo */}
                {ao_abrir_ao_vivo && (
                    <Item onClick={() => { ao_abrir_ao_vivo(); ao_fechar(); }}>
                        <Icon>live_tv</Icon>
                        <ItemTitle>Ao vivo</ItemTitle>
                    </Item>
                )}
                {/* Acumuladão: visível, sem ação nesta spec (FR-015) */}
                {indicadores.acumuladao && (
                    <Item>
                        <Icon>paid</Icon>
                        <ItemTitle>Acumuladão</ItemTitle>
                    </Item>
                )}
                <Item onClick={() => limpar_cache(tema.temas)}>
                    <Icon>cleaning_services</Icon>
                    <ItemTitle>Limpar cache</ItemTitle>
                </Item>
                <ItemLink href="/regras">
                    <Icon>receipt</Icon>
                    <ItemTitle>Regras</ItemTitle>
                </ItemLink>
                {vendedor && !tabela_aberta && (
                    <Item onClick={() => definir_tabela_aberta(true)}>
                        <Icon>description</Icon>
                        <ItemTitle>Tabela</ItemTitle>
                    </Item>
                )}
                {vendedor && tabela_aberta && opcoes_tabela.map(({ chave, titulo }) => (
                    <Item key={chave} onClick={() => escolher_tabela(chave)}>
                        <Icon>description</Icon>
                        <ItemTitle>{titulo}</ItemTitle>
                    </Item>
                ))}

                {paises.map(({ pais, campeonatos }) => (
                    <div key={pais}>
                        <Country>
                            <Flag
                                src={url_bandeira(campeonatos[0]?.bandeira)}
                                onError={(evento) => {
                                    // bandeira que não carrega: usa a padrão uma única vez
                                    if (!evento.currentTarget.src.endsWith(bandeira_padrao)) evento.currentTarget.src = bandeira_padrao;
                                }}
                                alt=""
                            />
                            <Title>{pais?.toUpperCase()}</Title>
                        </Country>
                        {campeonatos.map((campeonato) => (
                            <Championship key={campeonato.id} onClick={() => escolher_campeonato(campeonato)}>
                                <Title>{campeonato.nome}</Title>
                                <Count>{campeonato.quantidade_confrontos}</Count>
                            </Championship>
                        ))}
                    </div>
                ))}
            </Container>
        </>
    );
}
