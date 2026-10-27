# Quickstart: Painel — Confrontos

Validação manual (sem testes automatizados, constituição). Contratos em
[contracts/api.md](contracts/api.md) e [contracts/paginas.md](contracts/paginas.md); regras em
[data-model.md](data-model.md).

## Preparação

1. `php artisan migrate` (índice de `data_inicio` e a retirada de `confrontos.lancar_resultado` dos
   Supervisores).
2. Carga do pré-jogo e do ao vivo rodando (spec 003), ou jogos cadastrados à mão, para ter jogos
   ontem, hoje e amanhã e pelo menos um ao vivo.
3. `npm run dev` e `php artisan serve`.
4. Usuários: Admin, Supervisor S com Gerentes G1 e G2, Vendedor V1 (de G1) e V2 (de G2).
5. Apostas: no jogo J1 de hoje, 3 simples em Casa e 1 em Empate de V1, 1 simples de V2 e 1 múltipla
   de V1 com J1 + J2; um jogo J3 do provedor que começou há 3 horas, sem resultado, com 2 apostas
   aguardando.

## Cenários

### 1. Lista, dia, tipo e busca (US1)

- Admin abre `/painel/confrontos`: cartões, aba Jogos no dia Hoje, J1 com o limite, o jogo ao vivo
  com o selo (minuto e placar).
- Chips: Ao vivo → só os ao vivo; Pré-jogo → os demais. Atalhos Ontem e Amanhã; "Escolher data" com
  31 dias atrás → erro no campo.
- Buscar pelo nome do campeonato de J1 → só jogos daquele campeonato no dia escolhido.
- Recarregar a página → mesma aba, filtros e página.
- Vendedor: sem o item no menu; `GET /api/confrontos` com o token dele → 403.

### 2. Ativar e desativar (US2)

- Admin desativa J1 → some do site e do ao vivo; a linha mostra "Desativado"; ativar → volta.
- G1 escolhe "Desativar para a minha rede" em J1 → some para V1 e continua para V2; Admin vê
  "Desativado em 1 rede"; G1 ativa para a rede → volta para V1.
- Confirmação de J1 com apostas avisa que as apostas continuam valendo; num jogo sem apostas, o
  aviso não aparece.

### 3. Limite (US3)

- Admin altera o limite do pré-jogo de J1 para 1000,00 → linha e gaveta mostram o valor; tentar
  apostar acima com V1 → recusado. Zero ou 3 casas → erro no campo.
- No jogo ao vivo, o modal mostra os dois limites. G1: "Alterar limite" não aparece.

### 4. Gaveta e mais apostados (US4 e US7)

- Admin abre J1: Dados (cotações do site Casa/Empate/Fora), Mais apostados (Casa: simples 3 de V1 +
  1 de V2 = 4; Empate 1; a opção da múltipla em Múltiplas, com o valor e o retorno inteiros), Apostas
  (6 apostas, a múltipla como "Múltipla (2 jogos)"), "Abrir aposta" leva a `/painel/apostas?busca=`.
- G1 abre a mesma gaveta: só as apostas de V1 contam (Casa 3; Apostas sem a de V2).
- Aba Mais apostados: J1 antes de J2; jogo sem aposta não aparece; tocar em J1 abre a gaveta na aba
  Mais apostados. Conferir que os números batem com a aba Apostas da gaveta (SC-003).

### 5. Pendentes e placar (US5)

- Admin abre Pendentes: J3 com atraso ~3 h e 2 apostas. Lançar 2 x 1 com tempos 1x0 e 1x1 e
  escanteios → J3 sai de Pendentes, a gaveta mostra o resultado com origem "Lançamento manual" e as
  apostas são apuradas.
- Lançar 2 x 1 com tempos 1x0 e 0x0 → "A soma dos gols dos times não bate com os gols dos tempos.".
- Deixar um escanteio vazio → erro no campo.
- Jogo de amanhã: sem "Lançar resultado" no menu; `PUT /api/confrontos/{id}/resultado` direto →
  "O resultado só pode ser lançado depois do início do jogo.".
- Rodar a carga de resultados com um placar diferente para J3 (ou simular o retorno do provedor) →
  o resultado do provedor substitui o lançado e as apostas são reapuradas.
- Supervisor: sem a aba Pendentes nem o cartão; `PUT /api/confrontos/{J3}/resultado` com o token
  dele → 403. A permissão não aparece para o Supervisor no painel de permissões.

### 6. Cotações (US6)

- "Alterar cotações" em J1 → `/painel/cotacoes` na aba Jogos com o editor de J1 aberto; fechar e
  voltar → Confrontos com os mesmos filtros. Usuário sem `porcentagens_confrontos.editar`: sem a
  ação.

### 7. Campeonatos (US8)

- Aba Campeonatos: favoritos primeiro, quantidade de jogos, filtro por país.
- Admin desativa o campeonato de J1 → os jogos somem do site e mostram "Campeonato desativado" na aba
  Jogos; ativa → voltam. Favoritar → estrela e destaque no site.
- G1 desativa um campeonato só para a rede → some para V1, continua para V2.
- "Ver jogos" → aba Jogos filtrada pelo campeonato. "Alterar cotações" → Cotações, aba Campeonatos.

### 8. Cadastro manual (US9)

- Cadastrar o campeonato "Copa do Bairro" (Brasil) → aparece como Manual.
- Cadastrar nele um jogo amanhã 16:00 com Casa 1,80, Empate 3,20, Fora 4,00 e "Adicionar cotação"
  de outro mercado → aparece na lista e no site. Data no passado ou cotação 0,90 → erro no campo.
- Editar a cotação de Casa e a situação para Adiado → confere na lista.
- Jogo do provedor: sem Editar nem Excluir; `PUT /api/confrontos/{id}` direto → recusado.
- Fazer uma aposta no jogo manual e tentar excluir → "Este jogo tem 1 aposta. Cancele o jogo em vez
  de excluir."; excluir o campeonato → "Este campeonato tem jogos com apostas."; cancelar a
  aposta e excluir → sai da lista.

### 9. Atualização automática e celular

- Chip Ao vivo aberto por 1 minuto → minuto e placar mudam sozinhos; esconder a aba do navegador →
  sem chamadas (ver na aba Rede) até voltar.
- 360 px: linhas viram cartões, gaveta e modais em tela cheia, sem rolagem horizontal; nenhum modal
  tem interruptor.

### 10. Postman

- Coleção regenerada com as rotas novas e alteradas de [contracts/api.md](contracts/api.md).
