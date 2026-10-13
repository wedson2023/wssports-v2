import styled, { css } from 'styled-components';
import { mobile } from '../../theme/tokens';

export { ErrorText } from '../DepositModal/styles';

export const Tabs = styled.div`
    display: flex;
    gap: 4px;
    padding: 4px;
    margin-bottom: 16px;
    background: #f0f1f3;
    border-radius: 12px;
`;

export const Tab = styled.button`
    flex: 1;
    padding: 9px 6px;
    border: none;
    border-radius: 9px;
    background: ${({ $ativa, theme }) => ($ativa ? theme.principal : 'transparent')};
    color: ${({ $ativa }) => ($ativa ? '#fff' : '#555')};
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
`;

// envolve o conteúdo das abas: a altura em px vem da medição e muda com transição
export const Panel = styled.div`
    overflow: hidden;
    transition: height 0.25s ease;

    @media (prefers-reduced-motion: reduce) {
        transition: none;
    }
`;

export const Section = styled.div`
    display: flex;
    flex-direction: column;
    gap: 12px;
`;

export const Grid = styled.div`
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;

    ${mobile} {
        grid-template-columns: 1fr;
    }
`;

export const Field = styled.label`
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;

    > span {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 13px;
        font-weight: 500;
        color: #444;
    }

    ${({ $destaque, theme }) => $destaque && css`
        > span {
            color: ${theme.principal};
        }

        input,
        select {
            border-color: ${theme.principal};
        }
    `}
`;

const campo = css`
    width: 100%;
    height: 44px;
    padding: 0 12px;
    border: 2px solid #e1e3e8;
    border-radius: 10px;
    background: #fff;
    font-size: 14px;
    color: #222;
    outline: none;

    &:focus {
        border-color: ${({ theme }) => theme.principal};
    }

    &:disabled {
        background: #f3f4f6;
        color: #888;
    }
`;

export const Input = styled.input`
    ${campo}
`;

export const Select = styled.select`
    ${campo}
`;

export const Lock = styled.i`
    font-size: 15px;
    color: #999;
`;

export const Hint = styled.p`
    font-size: 12px;
    color: #888;
`;
