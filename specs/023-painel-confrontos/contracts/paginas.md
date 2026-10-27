# Contrato das páginas: Painel — Confrontos

Visual do painel novo (specs 014 a 022), modais com `ModalHeader` e regras de tamanho da
constituição. Celular = até 900 px. API em [api.md](api.md).

## 1. `/painel/confrontos` — `PanelMatches`

**Acesso**: `confrontos.listar`; sem ela, volta para `/painel`. Item "Confrontos" (ícone
`sports_soccer`) no `PanelShell`, logo antes de "Cotações".

**Topo** (`PanelHeader`): título "Confrontos", total do filtro, busca (time ou campeonato; na aba
Campeonatos, nome), botão "Filtros", botão "Atualizar" e, conforme a aba: "Cadastrar jogo" (Jogos,
com `confrontos.cadastrar`) ou "Cadastrar campeonato" (Campeonatos, com `campeonatos.cadastrar`).

**Cartões** (`StatCard`, `GET /confrontos/resumo`, nas abas Jogos, Mais apostados e Pendentes): Jogos
· Ao vivo agora · Desativados · Pendentes de resultado (só Admin).

**Abas** (`PanelTabs`):

| Aba | Visível para | Conteúdo |
|---|---|---|
| Jogos | `confrontos.listar` | `GET /confrontos`; chips de tipo Todos (padrão), Pré-jogo, Ao vivo |
| Mais apostados | `confrontos.listar` | `GET /confrontos/mais-apostados`; mesmos filtros e chips de Jogos |
| Pendentes | Admin com `confrontos.lancar_resultado` | `GET /confrontos/pendentes` |
| Campeonatos | `campeonatos.listar` | `GET /campeonatos` |

**Dia** (Jogos e Mais apostados): atalhos Ontem · Hoje (padrão) · Amanhã · Depois de amanhã e
"Escolher data" (campo de data, de hoje −30 a hoje +2; fora disso, erro no campo).

**Filtros** (painel com etiquetas removíveis, `ActiveFilters`):

| Aba | Filtros |
|---|---|
| Jogos e Mais apostados | Dia · Campeonato (lista de `GET /campeonatos?busca=`) · Situação (Ativos, Desativados, Todos) · Só manuais |
| Campeonatos | País (`GET /campeonatos/paises`) · Situação · Só favoritos · Só manuais |

Na URL: `aba`, `busca`, `tipo`, `dia`, `data`, `campeonatos_id`, `campeonato` (nome, para a
etiqueta), `situacao`, `manual`, `pais`, `favorito`, `page`.

**Colunas**:

| Aba | Colunas |
|---|---|
| Jogos | Jogo (casa x fora, selo "Ao vivo 63' 1–0" e marca "Manual") · Campeonato (país) · Início (dd/mm hh:mm) · ID · Situação (Ativo/Desativado; "Desativado para a minha rede"; "Campeonato desativado"; Encerrado com o placar) · Limite (R$) · ⋮ |
| Mais apostados | Jogo · Campeonato · Início · Apostas (total) · Simples (qtd · valor · retorno) · Múltiplas (qtd · valor · retorno) · ⋮; legenda: "Múltiplas contam inteiras em cada jogo." |
| Pendentes | Jogo (e "Manual") · Campeonato · Início · Atraso (h min) · Apostas · Em aberto (R$) · ⋮ |
| Campeonatos | Campeonato (bandeira, nome, "Manual") · País · Jogos disponíveis · Situação · Favorito (estrela) · ⋮ |

Admin e Supervisor veem, na Situação, "Desativado em N redes" quando `redes_desativadas > 0`.

**Menu ⋮** (`ActionsMenu`, conforme `acoes` do item):

| Aba | Itens |
|---|---|
| Jogos e Mais apostados | Ver detalhe · Alterar cotações · Alterar limite · Desativar/Ativar (para todos) ou Desativar/Ativar para a minha rede · Lançar/Corrigir resultado · Editar e Excluir (manuais) |
| Pendentes | Lançar resultado · Ver detalhe |
| Campeonatos | Ver jogos · Alterar cotações · Favoritar/Desfavoritar · Desativar/Ativar (para todos ou para a minha rede) · Adicionar jogo, Editar e Excluir (manuais) |

Desativar/Ativar, Favoritar e Excluir usam o `ConfirmModal` (Desativar avisa "As apostas já feitas
continuam valendo; o jogo só deixa de receber apostas novas." quando houver apostas). Tocar na linha
abre a gaveta (Mais apostados: já na aba Mais apostados).

**Atualização automática**: com o chip "Ao vivo" ou na aba Pendentes, lista e cartões a cada 30 s;
pausa com a aba do navegador escondida; erro mostra "Não foi possível atualizar" sem apagar a lista.

**Celular**: cartões com casa x fora, início e situação (Campeonatos: nome, jogos e situação); o
resto ao expandir.

**Atalhos**:

- Alterar cotações → `/painel/cotacoes?aba=jogos&confrontos_id=ID&nome=CASA x FORA` (ou
  `?aba=campeonatos&campeonatos_id=ID&nome=NOME`).
- Ver jogos (campeonato) → aba Jogos com `campeonatos_id` e o dia Hoje (o usuário troca o dia
  pelos atalhos).
- Aposta da gaveta → `/painel/apostas?busca=CODIGO`.

## 2. Modais

Todos: `ModalHeader` (ícone, título, descrição, fechar), corpo em grade de 2 colunas no computador
(1 no celular), seções com título, rodapé fixo com "Cancelar" e a ação principal à direita. Sem
`ToggleSwitch`.

| Modal | Componente | Seções e campos | Ação |
|---|---|---|---|
| Alterar limite | `MatchLimitModal` | Pré-jogo (limite, `DecimalField` em R$) · Ao vivo (limite, só quando o jogo estiver ao vivo) | `PATCH /confrontos/{id}/limite` e, se mudou, `PATCH /confrontos-ao-vivo/{id}/limite` |
| Lançar/Corrigir resultado | `MatchResultModal` | Placar final (casa, fora) · 1º tempo (casa, fora) · 2º tempo (casa, fora) · Escanteios (casa, fora) · Gols (lista: jogador, minuto, tempo; "Adicionar gol") | `PUT /confrontos/{id}/resultado` |
| Cadastrar/Editar jogo | `MatchFormModal` | Jogo (campeonato manual — lista de `GET /campeonatos?manual=1` —, esporte — `GET /confrontos/esportes` —, data e hora) · Times (casa, escudo casa, fora, escudo fora) · Cotações (Casa, Empate, Fora e "Adicionar cotação" por mercado) · Situação (edição; seleção Aguardando, Adiado, Cancelado) | `POST /confrontos` / `PUT /confrontos/{id}` |
| Cadastrar/Editar campeonato | `ChampionshipFormModal` | Campeonato (nome, país — seleção de `GET /campeonatos/paises` com "Outro país" em campo de texto —, bandeira) | `POST /campeonatos` / `PUT /campeonatos/{id}` |

Erros do servidor no campo que os causou; o modal não fecha com erro.

## 3. Gaveta — `MatchDrawer`

Tela cheia no celular. Topo: casa x fora, campeonato, início, situação, selo Ao vivo. Abas, cada uma
carregada ao abrir:

| Aba | Fonte | Conteúdo |
|---|---|---|
| Dados | `GET /confrontos/{id}` | ID, campeonato e país, esporte, início, origem (Provedor/Manual), situação, desativações (para todos, "Desativado em N redes", "Desativado para a minha rede"), limites (pré-jogo e ao vivo), cotações do site Casa · Empate · Fora |
| Mais apostados | `GET /confrontos/{id}/mais-apostados` | opção (nome como no cupom) · total · simples (qtd, valor, retorno) · múltiplas (qtd, valor, retorno) |
| Apostas | `GET /confrontos/{id}/apostas` | código · data · vendedor ou cliente · opção · "Simples" ou "Múltipla (N jogos)" · valor · retorno · resultado; "Abrir aposta" (com `apostas.listar`); paginada (30) |
| Resultado | `GET /confrontos/{id}` (`resultado`) | placar final, tempos, escanteios, gols por jogador, origem; "Sem resultado"; botão "Lançar/Corrigir resultado" (só Admin) |

## 4. Tela Cotações (alteração)

`PanelQuotes` lê `confrontos_id` + `nome` (aba Jogos) ou `campeonatos_id` + `nome` (aba
Campeonatos) da URL e abre o `QuoteEditorModal` com `{ id, nome }`; ao fechar o modal, remove esses
parâmetros da URL (mantém `aba`).
