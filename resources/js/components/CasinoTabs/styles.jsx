import styled from 'styled-components';

export const Container = styled.div`
    width: 100%;
    display: grid;
    grid-template-columns: 25% 25% 25% 25%;
    background-color: #111;
    color: #fff;
    user-select: none;
    position: fixed;
    bottom: 0;
    left: 0;
    z-index: 25;
`;

export const Tab = styled.div`
    padding: 15px 5px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-left: solid 1px #222;

    &:hover {
        background-color: ${({ theme }) => theme.principal};
    }
`;

export const Text = styled.span`
    font-weight: 300;
    font-size: 0.85em;
`;

export const Icon = styled.i.attrs(() => ({ className: 'material-icons' }))`
    user-select: none;
    outline: none;
    color: #fff;
    font-size: 1.5em;
    padding: 5px;
    margin-bottom: 5px;
    cursor: pointer;
`;
