# Instruções do Projeto

## Sobre o projeto
- Trata-se de um **sistema de apostas esportivas**.

## Nomenclatura
- Backend (PHP), frontend e instruções: sempre utilize **snake_case** ao invés de **camelCase**.
- Frontend (React/JavaScript):
  - componentes e styled-components em **inglês** e **PascalCase** (ex.: `OddButton`, `Container`), exigência do React;
  - variáveis, funções, props, chaves de objetos e constantes em **snake_case** e em **português** (ex.: `adicionar_palpite`, `ao_clicar`);
  - hooks no padrão do React: `use` + nome em camelCase, em português (ex.: `useCupom`);
  - chaves de dados vindos do backend usadas como chegam, em snake_case (ex.: `time_casa`);
  - nomes de APIs de bibliotecas e do navegador mantêm o original (ex.: `useState`, `localStorage`);
  - props só de estilo nos styled-components com prefixo `$` + snake_case português (ex.: `$selecionado`);
  - arquivos de hooks com o nome do hook (ex.: `hooks/useCupom.js`); arquivos de utilitários em inglês snake_case (ex.: `utils/money.js`).
- Nomenclaturas de banco de dados (migrations, tabelas, colunas, etc.) devem ser criadas em **português**.
- Estrutura de pastas deve ser em **inglês** (snake_case; pastas de componentes React em PascalCase).

## Idioma
- Instruções (specs, planos, tarefas) em **português**, no backend e no frontend.
- Comentários no código em **português**, no backend e no frontend.

## Componentes React
- Cada componente deve ter sua própria pasta, nomeada com o nome do componente (inglês, PascalCase).
- Dentro da pasta, dois arquivos:
  - `index.jsx`
  - `styles.jsx`

## Regras de edição
- Nunca altere trechos de código fora do que foi solicitado.
- Se for necessário mexer em outro trecho para a tarefa funcionar, avise antes de fazer.

## Testes
- O projeto **não terá testes automatizados**, nem no backend nem no frontend.
- Não crie arquivos, tarefas ou dependências de teste; a validação das features é manual.
