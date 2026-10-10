import styled from 'styled-components';
import { Link } from '@inertiajs/react';
import { mobile } from '../../theme/tokens';

// Menu de main/styles.js: coluna no desktop (20%, mínimo 240px) e gaveta no mobile (70%)
export const Container = styled.div`
    width: 20%;
    min-width: 240px;
    background-color: ${({ theme }) => theme.superficie_campeonato};
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 100px;

    @media screen and (min-width: 901px) {
        &::-webkit-scrollbar-track {
            background-color: #cccccc;
        }

        &::-webkit-scrollbar {
            width: 5px;
        }

        &::-webkit-scrollbar-thumb {
            background-color: #666;
        }
    }

    ${mobile} {
        position: fixed;
        z-index: 50;
        left: ${({ $aberto }) => ($aberto ? '0' : '-70%')};
        top: 0;
        width: 70%;
        min-width: 0;
        transition-duration: 0.3s;
        height: 100vh;
        height: 100dvh;
    }
`;

const estilo_item = `
    display: grid;
    grid-template-columns: 32px 65% auto;
    align-items: center;
    padding: 10px;
    text-decoration: none;
    user-select: none;
    transition-duration: 0.3s;
    cursor: pointer;
`;

export const Item = styled.div`
    ${estilo_item}
    background-color: ${({ theme }) => theme.superficie_barra};
    border-top: solid thin ${({ theme }) => theme.superficie_campeonato};

    &:hover {
        background-color: ${({ theme }) => theme.superficie_campeonato};
    }
`;

export const ItemLink = styled(Link)`
    ${estilo_item}
    background-color: ${({ theme }) => theme.superficie_barra};
    border-top: solid thin ${({ theme }) => theme.superficie_campeonato};

    &:hover {
        background-color: ${({ theme }) => theme.superficie_campeonato};
    }
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 25px;
`;

export const ItemTitle = styled.div`
    font-size: 13px;
    color: ${({ theme }) => theme.texto_principal};
`;

export const Country = styled.a`
    padding: 10px;
    text-decoration: none;
    background-color: ${({ theme }) => theme.superficie_pais};
    color: ${({ theme }) => theme.texto_principal};
    display: flex;
    justify-content: flex-start;
    align-items: center;
    transition-duration: 0.3s;
`;

export const Championship = styled.a`
    padding: 10px;
    border-bottom: solid #999 1px;
    text-decoration: none;
    background: ${({ theme }) => theme.superficie_campeonato};
    color: ${({ theme }) => theme.texto_principal};
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition-duration: 0.3s;
    font-size: 13px;

    &:hover {
        background: ${({ theme }) => theme.superficie_barra};
    }

    &:last-child {
        border-bottom: none;
    }
`;

// bandeira com largura fixa: se a imagem falhar, o espaço continua o mesmo
export const Flag = styled.img`
    width: 22px;
    min-width: 22px;
    margin-right: 15px;
`;

export const Title = styled.span`
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

export const Count = styled.span`
    padding: 3px 7px;
    background-color: ${({ theme }) => theme.superficie_titulo};
    text-align: center;
    font-size: 10px;
    color: ${({ theme }) => theme.texto_principal};
    font-weight: 500;
`;
