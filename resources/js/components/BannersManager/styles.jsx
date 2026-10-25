import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Loading = styled.div`
    display: flex;
    justify-content: center;
    padding: 20px 0;
`;

// galeria de cartões com a imagem grande; quantos cabem na largura, cada um com o mínimo informado
// (no celular, os banners ficam um por linha e os avisos, dois)
export const Gallery = styled.ul`
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(${({ $minimo }) => $minimo}px, 100%), 1fr));
    gap: 16px;
    margin: 0;
    padding: 0;
    list-style: none;
    opacity: ${({ $ocupada }) => ($ocupada ? 0.6 : 1)};
    transition: opacity 0.2s;

    ${mobile} {
        gap: 12px;
    }
`;

export const Tile = styled.li`
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: solid 1px ${({ theme }) => theme.superficie_titulo};
    border-radius: 8px;
    background-color: ${({ theme }) => theme.fundo_pagina};
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;

    &:hover {
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
        transform: translateY(-2px);
    }
`;

// imagem na proporção em que aparece no site; inativa fica apagada
export const Media = styled.button`
    position: relative;
    display: block;
    width: 100%;
    aspect-ratio: ${({ $proporcao }) => $proporcao};
    padding: 0;
    border: none;
    background-color: ${({ theme }) => theme.superficie_barra};
    cursor: zoom-in;

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: ${({ $ativo }) => ($ativo ? 'none' : 'grayscale(1)')};
        opacity: ${({ $ativo }) => ($ativo ? 1 : 0.5)};
    }
`;

// posição no carrossel, no canto de cima à esquerda
export const Position = styled.span`
    position: absolute;
    top: 8px;
    left: 8px;
    min-width: 28px;
    padding: 3px 8px;
    border-radius: 12px;
    background-color: rgba(0, 0, 0, 0.7);
    color: #fff;
    font-size: 0.75em;
    font-weight: 500;
`;

// situação, no canto de cima à direita
export const Chip = styled.span`
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 3px 10px;
    border-radius: 12px;
    background-color: ${({ $ativo }) => ($ativo ? '#2e7d32' : '#616161')};
    color: #fff;
    font-size: 0.72em;
    font-weight: 500;
`;

export const Body = styled.div`
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 8px 8px 12px;
`;

// link (ou título e período) com reticências quando não cabe
export const Info = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 2px;
    min-width: 0;

    strong,
    small {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    strong {
        color: ${({ theme }) => theme.texto_principal};
        font-size: 0.85em;
        font-weight: 500;
    }

    small {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.75em;
    }
`;

export const Actions = styled.div`
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 2px;
`;

export const Move = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    cursor: pointer;

    &:hover:not(:disabled) {
        background-color: ${({ theme }) => theme.superficie_barra};
    }

    &:disabled {
        opacity: 0.3;
        cursor: default;
    }

    i {
        font-size: 20px;
    }
`;

// último cartão: tracejado, para cadastrar um novo
export const AddTile = styled.button`
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    height: 100%;
    min-height: 140px;
    padding: 12px;
    border: dashed 2px ${({ theme }) => theme.texto_apagado};
    border-radius: 8px;
    background: transparent;
    color: ${({ theme }) => theme.texto_secundario};
    font: inherit;
    cursor: pointer;
    transition: border-color 0.2s, color 0.2s;

    i {
        color: ${({ theme }) => theme.principal};
        font-size: 34px;
    }

    strong {
        font-size: 0.9em;
        font-weight: 500;
    }

    small {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.75em;
    }

    &:hover {
        border-color: ${({ theme }) => theme.principal};
        color: ${({ theme }) => theme.texto_principal};
    }
`;
