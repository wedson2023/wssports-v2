# Instruções do Projeto

## Sobre o projeto
- Trata-se de um **sistema de apostas esportivas**.

## Nomenclatura
- Backend (PHP) e instruções: sempre utilize **snake_case** ao invés de **camelCase**.
- Frontend (React/JavaScript):
  - componentes e styled-components em **inglês** e **PascalCase** (ex.: `OddButton`);
  - variáveis, funções e hooks em **camelCase** e em **português** (ex.: `adicionarPalpite`, `useCupom`);
  - chaves de dados vindos do backend usadas como chegam, em snake_case (ex.: `time_casa`).
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
