import styled from 'styled-components';

const telefone = '@media screen and (max-width: 600px)';

// Balances, Balance, BalanceText, BalanceValue, BalanceLast e Message de screens/credits/styles.js
export const Balances = styled.div`
    display: flex;
    justify-content: space-around;
    width: 90%;
    margin: auto;

    ${telefone} {
        justify-content: space-between;
        width: 95%;
    }
`;

export const Balance = styled.div`
    display: flex;
    flex-direction: column;
    flex: 1;
`;

export const BalanceText = styled.p`
    margin: 0 auto 5px auto;
    font-weight: 500;
    color: ${({ theme }) => theme.texto_principal};
`;

export const BalanceValue = styled.h3`
    font-size: 1.2em;
    background-color: ${({ $negativo }) => ($negativo ? '#c40808' : 'green')};
    width: 120px;
    display: block;
    margin: auto;
    color: #fff;
    padding: 10px;
    text-align: center;
    border-radius: 5px;
    margin-bottom: 5px;

    ${telefone} {
        width: 95px;
        font-size: 1em;
    }
`;

export const BalanceLast = styled.span`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 12px;
    text-align: center;
    display: block;
    width: 70%;
    margin: auto;
`;

export const Message = styled.p`
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 12px;
    text-align: center;
    display: block;
    width: 70%;
    margin: 25px auto;
`;

export const Negative = styled(Message)`
    color: #e53935;
    font-weight: 500;
    margin-bottom: 0;
`;
