import styled from 'styled-components';

export const Page = styled.div`
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
`;

export const Card = styled.div`
    width: min(420px, 100%);
    background: #fff;
    border-radius: 16px;
    border-top: 4px solid ${({ theme }) => theme.principal};
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    padding: 28px 24px;
    text-align: center;
`;

export const Logo = styled.img`
    width: 96px;
    display: block;
    margin: 0 auto 12px;
`;

export const Icon = styled.i`
    font-size: 56px;
    color: ${({ $sucesso }) => ($sucesso ? '#28a745' : '#c40808')};
`;

export const Message = styled.p`
    margin: 12px 0 20px;
    color: #333;
    font-size: 15px;
    line-height: 1.5;
`;

export const Button = styled.button`
    min-height: 44px;
    padding: 0 28px;
    border: none;
    border-radius: 10px;
    background: ${({ theme }) => theme.principal};
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;

    &:hover {
        filter: brightness(0.92);
    }
`;
