import styled from 'styled-components';

export const Container = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
`;

// etiqueta na cor principal, legível nos dois temas
export const Chip = styled.span`
    display: inline-flex;
    align-items: center;
    gap: 2px;
    max-width: 100%;
    padding: 2px 4px 2px 10px;
    border: solid 1px ${({ theme }) => theme.principal};
    border-radius: 16px;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.8em;

    span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    button {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        padding: 0;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: ${({ theme }) => theme.principal};
        cursor: pointer;
    }

    i {
        font-size: 16px;
    }
`;
