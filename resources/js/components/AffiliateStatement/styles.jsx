import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// tabela do painel do afiliado igual à do screens/affiliated antigo: cabeçalho na cor do tema e
// linhas alternadas em #222; usada também pelos clientes e pelas prestações
export const List = styled.div`
    margin-top: 15px;
`;

export const Thead = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    background-color: ${({ theme }) => theme.principal};
`;

export const Tr = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;

    &:nth-child(odd) {
        background-color: #222;
    }
`;

export const Td = styled.div`
    display: flex;
    justify-content: center;
    width: ${({ $largura }) => $largura ?? '20%'};
    padding: 5px 0;
    font-size: 12px;
    color: ${({ $negativo }) => ($negativo ? 'red' : '#fff')};
    text-align: center;
    word-break: break-word;
`;

export const Message = styled.p`
    display: block;
    width: 70%;
    margin: 25px auto;
    font-size: 12px;
    color: #999;
    text-align: center;
`;

// filtros do extrato (acréscimo da spec 012): período e em aberto/prestados
export const Filters = styled.form`
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 8px;

    input,
    select {
        height: 34px;
        padding: 0 8px;
        border: solid thin #444;
        border-radius: 5px;
        background: #111;
        color: #fff;
        font-size: 12px;
        color-scheme: dark;
    }

    ${mobile} {
        input {
            flex: 1;
            min-width: 120px;
        }
    }
`;

export const Button = styled.button`
    height: 34px;
    padding: 0 14px;
    border: none;
    border-radius: 5px;
    background: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 12px;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: default;
    }
`;

export const More = styled(Button)`
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 15px auto 0;
    background: transparent;
    border: solid thin ${({ theme }) => theme.principal};
`;
