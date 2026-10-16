import styled from 'styled-components';

export const Container = styled.div`
    display: grid;
    grid-template-columns: 50% 50%;
    width: 100%;
`;

export const Title = styled.strong`
    display: block;
    text-align: left;
    color: #fff;
    text-transform: uppercase;
    margin: 25px 0;
`;

export const Link = styled.strong`
    cursor: pointer;
    display: block;
    text-align: right;
    color: #fff;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 25px 0;

    &:hover {
        text-decoration: underline;
    }
`;

export const Games = styled.div`
    display: grid;
    grid-template-columns: calc(33.33% - 10px) calc(33.33% - 10px) calc(33.33% - 10px);
    grid-gap: 15px;
    grid-column-start: 1;
    grid-column-end: 3;
`;
