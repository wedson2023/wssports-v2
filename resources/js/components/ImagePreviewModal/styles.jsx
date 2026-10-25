import styled from 'styled-components';

export const Image = styled.img`
    display: block;
    max-width: 100%;
    max-height: 70dvh;
    margin: 0 auto;
    border-radius: 4px;
    object-fit: contain;
`;

export const Link = styled.a`
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    color: ${({ theme }) => theme.principal};
    font-size: 0.85em;
    word-break: break-all;

    i {
        font-size: 18px;
    }
`;
