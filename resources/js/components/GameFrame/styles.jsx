import styled from 'styled-components';

export const Container = styled.div`
    position: fixed;
    inset: 0;
    z-index: 200;
    background-color: #000;
    display: flex;
    flex-direction: column;
`;

export const Bar = styled.div`
    display: grid;
    grid-template-columns: 45px auto 45px;
    align-items: center;
    height: 45px;
    background-color: #111;
    border-bottom: solid 2px ${({ theme }) => theme.principal};
`;

export const Title = styled.span`
    color: #fff;
    font-size: 0.9em;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

export const Button = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    color: #fff;
    font-size: 26px;
    padding: 9px;
    cursor: pointer;

    &:hover {
        color: ${({ theme }) => theme.principal};
    }
`;

export const Frame = styled.iframe`
    flex: 1;
    width: 100%;
    border: 0;
    background-color: #000;
`;
