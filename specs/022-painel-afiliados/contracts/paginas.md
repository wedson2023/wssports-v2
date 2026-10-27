# Contrato das páginas: Painel — Afiliados

Visual do painel novo (specs 014 a 021), modais com `ModalHeader` e regras de tamanho da
constituição. Celular = até 900 px.

## 1. `/painel/afiliados` — `PanelAffiliates`

**Acesso**: `afiliados.listar`; sem ela, volta para `/painel`. Item "Afiliados" (ícone
`handshake`) no `PanelShell`, depois de "Bônus".

**Topo**: título "Afiliados", total do filtro, busca (nome, login ou código), botão "Filtros" e
botão "Cadastrar" (com `afiliados.cadastrar`).

**Cartões** (`StatCard`, `GET /afiliados/resumo`): Total de afiliados · Em análise (só Admin) ·
Comissão do período · A receber.

**Abas** (`PanelTabs`):

| Aba | Visível para | Conteúdo |
|---|---|---|
| Ativos | todos com `afiliados.listar` | `grupo=aprovados`; chips Todos (padrão), Ativos, Inativos |
| Em análise | Admin com `afiliados.alterar_situacao` | `grupo=analise`; chips Em análise (padrão), Recusados |
| Lixeira | com `afiliados.restaurar` | `GET /afiliados/excluidos` |

**Filtros** (painel com etiquetas removíveis, `ActiveFilters`): Situação (seleção), Gerente (lista
dos gerentes da rede; Admin e Supervisor), Sem gerente (só Admin), Período (`PeriodFilter`: Hoje,
Ontem, Esta semana, Este mês — padrão —, Personalizado até 1 ano). Na URL: `aba`, `busca`,
`situacao`, `usuarios_id`, `sem_gerente`, `data_inicial`, `data_final`, `page`.

**Colunas — Ativos e Lixeira**: ID · Nome (com o código embaixo) · Gerente ("Sem gerente
responsável" em destaque, só Admin) · Ganho (tipo + %, ou "A definir") · Cadastros · Depósitos ·
Saques · Comissão · Entradas · A receber (vermelho se negativo) · Última prestação (data e valor,
ou "Nunca") · Situação · ⋮. Na Lixeira, + Excluído em.

**Colunas — Em análise**: Nome e login · Contato (e-mail e telefone, mascarados conforme a
permissão) · Cidade/UF · Rede · Seguidores · Perfil (link, abre em nova aba) · Sobre (trecho) ·
Cadastrado em · Situação · ⋮. Nos recusados, + Recusado por / em / motivo.

**Menu ⋮** (`ActionsMenu`):

| Aba | Itens (conforme permissão e situação) |
|---|---|
| Ativos | Ver detalhe · Editar · Ativar/Inativar · Trocar regra · Prestar contas · Copiar link · Excluir |
| Em análise | Ver detalhe · Aprovar · Recusar (só Em análise) |
| Lixeira | Ver detalhe · Restaurar |

**Celular**: cartões com nome e código, a receber e situação; o resto ao expandir.

## 2. Modais

Todos: `ModalHeader` (ícone, título, descrição, fechar), corpo em grade de 2 colunas no computador
(1 no celular), seções com título, rodapé fixo com "Cancelar" e a ação principal à direita (no
celular, lado a lado com a largura toda). Sem `ToggleSwitch`: escolhas em `select`.

| Modal | Componente | Campos e seções | Ação |
|---|---|---|---|
| Cadastrar afiliado | `AffiliateFormModal` | Dados pessoais (nome, login, senha, confirmar senha, e-mail, CPF) · Contato e endereço (DDI + telefone, cidade, estado, endereço) · PIX (tipo, chave) · Perfil (rede social, link do perfil, seguidores, sobre você com contador) · Ganho (gerente — não aparece para o Gerente —, tipo de ganho, percentual) | `POST /afiliados` |
| Editar afiliado | `AffiliateFormModal` | iguais, com login só leitura; Ganho só com o gerente (Admin e Supervisor); código (Admin e Supervisor); Situação Ativo/Inativo (com `afiliados.alterar_situacao`); Nova senha e Confirmar nova senha (opcionais) | `PATCH /afiliados/{id}` e, se a situação mudou, `PATCH .../situacao` |
| Aprovar cadastro | `AffiliateApprovalModal` | resumo do cadastro (rede, seguidores, link) · gerente · tipo de ganho · percentual (preenchidos se já houver regra) | `POST .../aprovar` |
| Recusar cadastro | `ConfirmModal` com motivo | motivo (opcional, até 255) | `POST .../recusar` |
| Trocar regra | `AffiliateRuleModal` | regra vigente (só leitura) · tipo de ganho · percentual · aviso "vale a partir de agora" | `PUT .../comissao` |
| Prestar contas | `AffiliateSettlementModal` | lançamentos em aberto · valor apurado · valor a pagar (zero se negativo, com aviso) · chave PIX · observação (até 500) · senha | `POST .../prestacoes` |
| Excluir afiliado | `AffiliateDeleteModal` | aviso "Este afiliado tem R$ X a receber." quando houver · senha | `DELETE /afiliados/{id}` |
| Restaurar com conflito | `AffiliateRestoreModal` | valor novo de cada campo em conflito (login, e-mail, CPF, código) | `POST .../restaurar` |

Restaurar sem conflito usa o `ConfirmModal`. Ativar/Inativar pelo menu usa o `ConfirmModal` e
chama `PATCH .../situacao`.

## 3. Gaveta — `AffiliateDrawer`

Tela cheia no celular. Topo: nome, código com botão "Copiar link", situação, regra vigente, a
receber. Abas, cada uma carregada ao abrir (padrão 30 por página):

| Aba | Fonte | Conteúdo |
|---|---|---|
| Dados | `GET /afiliados/{id}` | dados pessoais, contato, endereço, PIX (mascarados conforme a permissão), rede, link, seguidores, sobre você, origem, gerente, cadastrado por, aprovado/recusado por (data, motivo), criado e alterado em |
| Extrato | `GET .../lancamentos` | período + Em aberto / Prestados / Todos; data, cliente, evento, base, %, comissão; soma do filtro |
| Clientes | `GET .../clientes` | ID, nome, contato, cadastrado em, total depositado; "Ver cliente" (abre `ClientDrawer`, com `clientes.listar`) |
| Prestações | `GET .../prestacoes` | data, período, apurado, pago, lançamentos, autor, observação |
| Regras | `GET .../comissoes` | tipo, %, início, fim, definido por; botão "Trocar regra" (com `afiliados.editar_comissao`) |

## 4. Copiar link

`<origem do site>/?user=CODIGO`, pela área de transferência; aviso "Link copiado." (com o aviso
de afiliado não Ativo quando for o caso). Sem permissão do navegador: `ConfirmModal` com o link
num campo para copiar à mão.

## 5. Área do afiliado (alterações)

- `AffiliateSignupModal` (`/cadastro-afiliado`): + Rede social principal (seleção), Link do perfil,
  Quantidade de seguidores (só números), Sobre você (contador até 1.000), no visual atual do modal.
- `AffiliateProfileModal` ("Meus dados" em `/afiliados`): os mesmos quatro campos, editáveis.

## 6. Configurações (alteração)

`/painel/configuracoes`, aba Operação: some a seção "Afiliados" (entrada `afiliados` de
`utils/settings_fields.js`).
