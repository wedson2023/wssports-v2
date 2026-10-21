import styled from 'styled-components';

// faixa escura do topo dos modais do painel (mesmo fundo do cabeçalho da spec 014); não encolhe
export const Container = styled.header`
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #222;
    color: #fff;
`;

// só o ícone, na cor principal do tema, sem fundo
export const Icon = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    flex-shrink: 0;
    color: ${({ theme }) => theme.principal};
    font-size: 30px;
`;

export const Texts = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
`;

export const Title = styled.h3`
    margin: 0;
    overflow: hidden;
    font-size: 1.05em;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

export const Description = styled.p`
    margin: 0;
    color: #b5b5b5;
    font-size: 0.8em;
    line-height: 1.35;
`;

export const Close = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 4px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #ddd;
    cursor: pointer;

    &:hover {
        background-color: rgba(255, 255, 255, 0.12);
        color: #fff;
    }
`;
