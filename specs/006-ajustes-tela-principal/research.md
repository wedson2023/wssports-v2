# Research: Ajustes da tela principal

**Feature**: `006-ajustes-tela-principal` | **Data**: 2026-10-10 | **Spec**: [spec.md](spec.md)

## R-01. Arquivos do sistema antigo consultados (Princípio VII)

Sistema antigo: `wssports.bet` (somente leitura).

| Assunto | Arquivos |
|---|---|
| Link do bilhete e validação | `resources/js/screens/main/index.js` (`useEffect` do `?code=`, `getTicket`, `open_check`) |
| Menu do vendedor e impressão | `resources/js/screens/main/index.js` (itens Impressão, Largura, Tabela; `connect_printer`, `table`, `table_html`), `resources/js/utils/helpers.js` (`print_ticket_mobile`, `print_table_mobile`, `print_ticket`), `resources/js/modals/table/index.js` |
| Tabela de jogos | `app/Http/Controllers/ConfrontosController.php` (`tabela`), `routes/api.php` (`POST tabela`) |
| Especiais | `database/migrations/2021_03_21_090701_create_modalidades_table.php`, `..._090705_create_modalidades_especiais_table.php`, `app/Http/Controllers/ModalidadesController.php` (`update`, `destroy`), `app/Http/Controllers/HomeController.php` (`index` com `tipo_esporte = ESPECIAL`, `odd`), `app/Http/Controllers/OperacaoController.php` (`operacao`, `situacao`), `resources/js/screens/main/components/specials/index.js` |
| Aviso (antigo popup) | `database/migrations/2024_01_29_094333_create_popups_table.php`, `2024_02_29_134509_add_tipo_notificacaos_table.php`, `app/Http/Controllers/HomeController.php` (`index`, bloco `$popup`), `routes/api.php` (`notificacao-popup`), `resources/js/screens/main/index.js` (`getData`, `confirm_popup`, `close`) |
| Banners e logo | `database/migrations/2021_03_23_180041_create_slides_table.php`, `app/Http/Controllers/ConfigController.php` (envio de `logo` e `banner`, `deletarBanner`, `AtualizarLinkBanner`), `routes/api.php` (`logo`, `atualizar-link-banner`, `deletar-banner`) |
| Regras e bônus | `app/Http/Controllers/HomeController.php` (`regras`), `resources/js/screens/rules/index.js` e `styles.js` (`Header`), `database/migrations/2023_09_07_101849_create_creditos_bonuses_table.php`, `2024_01_29_204951_add_modalidade_creditos_bonuses_table.php`, `2025_07_09_091146_change_ganho_em_creditos_bonuses_table.php`, `resources/js/screens/adm/modals/bonus/index.js` |

## R-02. Como cada funcionalidade funcionava no sistema antigo

- **Link `?code=`**: o `main` lia `code` da URL depois de carregar os jogos, chamava `home/{code}` e:
  com token e bilhete `Pendente`, montava o `player` com os palpites, guardava o código em
  `validate_code` e abria o cupom (o "Finalizar" virava "Validar"); nos demais casos abria o
  `ModalTicket`. Depois apagava o parâmetro com `history.replaceState`. O popup (aviso) não
  aparecia quando havia `code` na URL.
- **Menu do vendedor**: com token e largura < 1024px, apareciam "Impressão: PADRÃO/APP" e "Largura:
  80/58 mm" (gravados no `localStorage` como `printer` e `column`); com token de Vendedor aparecia
  "Tabela" (em qualquer largura), que abria "Jogos de Hoje", "Jogos de Amanhã" e "Jogos por
  Campeonatos" (`ModalTable`, com a escolha dos campeonatos).
- **Imprimir**: no desktop, `print_ticket` (HTML pela impressão do navegador). No mobile, com
  `printer = APP`, `location.href = app://{host}/{codigo}/{column}/false`; com `PADRÃO`,
  `navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices:
  ['e7810a71-73ae-499d-8c15-faa9aef0c3f2'] })`, procura a primeira característica com `write`, guarda
  no Redux (`ADD_DEVICE`) e imprime; nas próximas usa a mesma característica.
- **Formato Bluetooth** (`print_ticket_mobile` e `print_table_mobile`): texto ESC/POS (`ESC @`,
  `ESC E 1`, `ESC a 1`, `GS ! 1`, `GS B 1/0`), 48 colunas para 80 mm e 32 para 58 mm, acentos
  removidos (`slug`), linhas "rótulo ... valor" (`spacePrinter`) e envio em blocos de 20 bytes, um
  após o outro. Bilhete: nome do sistema, código, cliente, vendedor, telefone, horário, palpites
  (times, palpite, cotação, horário, campeonato, tipo; especial: categoria, palpite, cotação,
  horário, tipo), quantidade, cotação, valor, prêmio, "Vendedor Paga" (se houver comissão sobre o
  prêmio), aviso de apresentação, assinatura e mensagem do bilhete.
- **Tabela**: `POST tabela?de&ate&pin&tipo_esporte` com `campeonatos_id` (opcional), cotações do
  vendedor; devolve `nome_sistema`, `time` (atualização) e `tabela` por campeonato. No Bluetooth, 12
  colunas em duas linhas: `CASA EMP FORA AMB +2.5 DP.C` (odd1, odd2, odd3, odd4, odd116, odd10) e
  `GMC GMF 2GMC N.A -2.5 DP.F` (odd15, odd17, odd16, odd7, odd123, odd13), cabeçalho repetido a cada
  5 campeonatos. No navegador, 14 colunas: as 12 mais CGF (odd135) e FGC (odd139).
- **Especiais**: `modalidades` (categoria: nome, horário, vencedor em texto, ativo, situação
  Aguardando/Encerrado/Cancelado) e `modalidades_especiais` (opções: nome, odd, única por categoria).
  A listagem trazia só `Aguardando` com horário futuro, agrupadas por categoria, e o menu mostrava o
  país "Especiais" com as categorias. A cotação era a `odd` gravada, sem porcentagens. O palpite ia
  no cupom como `casa = 'Vencedor'` e `fora = categoria`, um por categoria.
- **Apuração do especial**: no `ModalidadesController@update`, informar o `vencedor` encerrava a
  categoria e, para cada bilhete com palpite dela, recalculava o `status`: especial → Vencedor se
  `vencedor === modalidade`, senão Perdedor (`OperacaoController@situacao`); jogos → resultado pelo
  placar quando o confronto estava Encerrado, senão Aguardando. Bilhete: Perdedor se algum perdeu;
  Aguardando se algum aguarda; Vencedor se todos ganharam.
- **Popup**: tabela `popups` (imagem e link); leituras em `notificacaos` (`ip` + `popups_id`). A home
  sorteava 1 popup ativo não lido pelo IP; o site mostrava ao abrir (flag `start`), com imagem
  clicável (link), "Fechar" e "Lido" (confirmação e `POST notificacao-popup`).
- **Regras**: `regras?pin` devolvia o texto livre do gerente (HTML), as promoções ativas
  (`creditos_bonuses` com `termina_em` futuro) e o tema. A tela montava um parágrafo por bônus com
  valor (fixo ou % do depósito), modalidade, rollover, indicações, valor máximo convertido, validade
  em dias, rollover do primeiro depósito, depósito máximo e, para Esportes, valores mínimo e máximo
  de aposta e cotações mínimas simples e múltipla; depois, as regras fixas de cada mercado. Ordem
  na tela: logo, texto livre (seção branca sem título), "REGRAS DE BÔNUS" e "REGRAS DE APOSTAS".
  Cada mercado tinha um `Header` com o ícone `directions_run`, o nome em caixa alta e bordas
  esquerda e inferior de 2px na cor do tema, seguido de linhas "**RÓTULO:** texto".
- **Banners**: tabela `slides` (`image`, `link`). O envio redimensionava para 1280×405 (Intervention
  Image) e salvava em `upload/images/slide/{md5(time())}.jpg`; o link era editado à parte; não era
  possível apagar o último banner.
- **Logo**: sem tabela; o envio sobrescrevia `upload/images/logo/logo.png` (e gerava ícones e
  favicon), sempre no mesmo endereço; por isso a logo nova ficava presa no cache do navegador.

## R-03. Diferenças de comportamento em relação ao antigo (aprovadas em Clarifications)

| Antigo | Novo | Decisão |
|---|---|---|
| Recurso `popups` | Recurso `avisos` (tabelas, rotas, classes e textos) | 2026-10-10 (nomes intuitivos) |
| Leitura do popup por IP | Leitura do aviso por aparelho (UUID no navegador) e, logado, por cliente | 2026-10-10 |
| Popup com visual do antigo | Aviso redesenhado com UX (cartão/folha) | 2026-10-10 |
| Bônus com valores padrão ativos? (antigo criava pelo painel) | Uma promoção padrão de cada categoria, **inativa** | 2026-10-10 |
| Regras: texto livre do gerente (HTML) + bônus + mercados | Texto único do administrador (texto simples, um parágrafo por linha) + bônus + mercados + limites de aposta de quem vê | 2026-10-10 |
| Página de regras da spec 005 (cartão com regras numeradas) | Blocos no visual de "Regras de apostas" do antigo, com as cores pelos tokens do tema (claro/escuro) | 2026-10-10 |
| Banners em `slides`, nome `md5(time())`, último não pode ser apagado | Tabela `banners` com ordem e ativo; nome pelo hash do conteúdo; sem banner ativo o carrossel some | 2026-10-10 / melhoria |
| Logo num endereço fixo (preso no cache) | Logo na configuração, arquivo com hash do conteúdo no nome | 2026-10-10 |
| Vencedor do especial em texto | Opção vencedora por id (`especiais_opcoes_id`) | melhoria (Princípio VII) |
| Encerrar especial apurava bilhetes com jogos | Grava resultado dos palpites especiais e da aposta quando decidível sem os jogos; jogos e pagamento na spec de apuração | 2026-10-10 |
| `modalidades_especiais` apagadas e recriadas a cada edição (`forceDelete`) | Opções editadas uma a uma, com soft delete; opção com palpite não é removida de fato | melhoria (Princípio VII; soft delete da constituição) |
| Tabela por `pin` e `de/ate` em UTC | Tabela do vendedor logado, por `dia` (hoje/amanhã) ou campeonatos, paginada | melhoria (sem `pin`; paginação ≤ 100) |

## R-04. Link do bilhete (`/?code=`)

- **Decisão**: só frontend. A `Home` lê `code` da URL uma vez, depois de a sessão estar definida
  (`useSessao`), e chama a mesma `buscar_codigo` da pesquisa (FR-044 da spec 005), que já leva o
  vendedor para a validação (`abrir_validacao`) e os demais para o `TicketModal`. Em seguida remove
  o parâmetro com `window.history.replaceState` (sem recarregar e sem nova visita do Inertia). O
  `PaginaInicialController` não muda.
- **Aviso**: a `Home` não pede o aviso quando abriu com `code` (FR-028).
- **No mobile**: a validação abre o cupom (`definir_cupom_aberto(true)`, já feito em
  `abrir_validacao`).
- **Alternativa rejeitada**: tratar o `code` no servidor (prop extra): duplicaria a regra de quem
  valida e exigiria o token, que só chega na segunda carga.

## R-05. Preferências e itens de impressão do vendedor

- **Armazenamento**: `localStorage` em `wssports.impressao` = `{ modo: 'PADRÃO' | 'APP', largura:
  58 | 80 }` (padrão `PADRÃO` e `80`), lido e gravado por `utils/storage.js` (chave nova). "Limpar
  cache" apaga junto, como no antigo.
- **Menu**: o `SideMenu` recebe `vendedor` (sessão de usuário com `apostador === 'vendedor'`) e
  `e_mobile`; mostra Impressão e Largura só no mobile e Tabela em qualquer largura, como no antigo.
- **Hook**: `useImpressao` (estado das preferências + ações `alternar_modo`, `alternar_largura`,
  `imprimir_bilhete`, `imprimir_tabela`), usado pelo `SideMenu`, pelo `SuccessModal` e pelo modal da
  tabela.

## R-06. Impressão Bluetooth (Web Bluetooth, sem dependência nova)

- **Decisão**: `navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices:
  ['e7810a71-73ae-499d-8c15-faa9aef0c3f2'] })`, igual ao antigo, conectando ao GATT e usando a
  primeira característica com `write` ou `writeWithoutResponse`. A característica fica guardada em
  memória (variável do módulo `utils/bluetooth.js`) enquanto o servidor GATT estiver conectado; no
  evento `gattserverdisconnected` ou em erro de escrita ela é descartada e a próxima impressão pede
  a impressora de novo.
- **Formato**: texto ESC/POS montado por `utils/thermal.js` com as mesmas regras do antigo (comandos,
  32/48 colunas, sem acentos, `linha_rotulo_valor` no lugar de `spacePrinter`, blocos de 20 bytes
  escritos em sequência com `writeValue`/`writeValueWithoutResponse`). Dinheiro formatado a partir
  dos textos do backend, sem `parseFloat` em cálculo (só exibição).
- **Erros**: sem `navigator.bluetooth` (Safari/iOS, Firefox) → alerta "Este aparelho não aceita
  impressão Bluetooth. Use o modo APP no menu." (FR-009). `NotFoundError` (o vendedor cancelou a
  escolha) → nada. Outros erros → alerta com a mensagem e a característica é esquecida.
- **Contexto seguro**: Web Bluetooth exige HTTPS (ou `localhost`), o que o PWA já exige.
- **Alternativa rejeitada**: biblioteca de ESC/POS (ex.: `esc-pos-encoder`): o antigo já mostra o
  conjunto pequeno de comandos usados; uma dependência a mais não traz ganho (Stack).

## R-07. Modo APP

- **Decisão**: `window.location.href = 'app://{location.host}/{codigo}/{largura}/false'`, exatamente
  o endereço do antigo (`column` = 58 ou 80), para o aplicativo de impressão já instalado nos
  aparelhos dos vendedores continuar funcionando. Vale só para bilhetes: no antigo, a tabela no
  mobile ia sempre para o Bluetooth (`connect_printer(data, 'table')`), qualquer que fosse o modo;
  mantido.

## R-08. Tabela de jogos (backend)

- **Rota**: `GET /api/tabela-jogos` (`auth:api`, `garantir_acesso`, permissão `apostas.criar`, que
  só o vendedor usa). Query: `dia` (`hoje` | `amanha`), `esporte` (padrão `FUTEBOL`; ao vivo → o
  frontend manda `FUTEBOL`), `campeonatos[]` (opcional; quando vem, ignora `dia` e usa o período do
  vendedor), `pagina`, `por_pagina` (padrão e máximo 100).
- **Dados**: reaproveita `ListagemConfrontos::pre_jogo` com o `Publico` do vendedor (mesmas regras de
  visibilidade da lista, FR-011, e mesmas cotações ajustadas pelo `CalculoCotacoes`), pedindo só os
  14 códigos da tabela: odd1, odd2, odd3, odd4, odd116, odd10, odd135, odd15, odd17, odd16, odd7,
  odd123, odd13, odd139. Para isso, `ListagemConfrontos::pre_jogo` ganha o filtro opcional
  `campeonatos` (lista) e o parâmetro opcional de códigos de cotação (arquivo existente, Princípio
  IV — ver plano).
- **Resposta**: `nome_sistema`, `atualizada_em` e `campeonatos[{ nome, confrontos[{ data_inicio,
  time_casa, time_fora, cotacoes{codigo: valor} }] }]` + `meta` de paginação. Cotação ausente ou
  bloqueada vem `"1.00"` (o antigo imprimia o valor gravado, mínimo 1).
- **Frontend**: pede página por página até a última e junta os campeonatos (mesma junção da rolagem
  infinita, `juntar_paginas`), respeitando o limite de 100 por página da constituição.
- **Escolha de campeonatos**: modal `TableModal` com a lista do menu (`listagem.paises`), igual ao
  `ModalTable` do antigo (marcar campeonatos e "Imprimir").

## R-09. Especiais: modelo

- **Tabelas novas**: `especiais` (categoria) e `especiais_opcoes` (prefixo da tabela principal,
  Princípio I). Situação com o enum novo `SituacaoEspecial` (`Aguardando`, `Encerrado`,
  `Cancelado`, os mesmos termos do antigo). Opção vencedora por id. Detalhes no
  [data-model.md](data-model.md).
- **Cotação**: decimal(8,2) ≥ 1,01 (o antigo tratava 1 como bloqueada). Sem porcentagens nem teto
  (Clarifications).
- **Permissões**: `especiais.listar`, `especiais.gerenciar` (cadastrar, editar, ativar e remover
  categoria e opções) e `especiais.encerrar` (encerrar e cancelar), em `Funcao::PERMISSOES_ESPECIAIS`;
  só Admin e Supervisor, como as de confrontos que não estão em `PERMISSOES_CONFRONTOS_GERENTE`.

## R-10. Especiais na aposta

- **Pedido**: o palpite especial vem com `codigo_cotacao = "especial"` e `especiais_opcoes_id`
  (sem `confrontos_id`). `CodigosCotacao` ganha a constante `ESPECIAL` e o `e_regra` a aceita;
  `ApostasRequest` passa a exigir um entre `confrontos_id`, `confrontos_ao_vivo_id` e
  `especiais_opcoes_id`.
- **Gravação**: `apostas_palpites` ganha `especiais_id` e `especiais_opcoes_id` (nulos) e
  `resultado` (padrão `Aguardando`); `confrontos_id` e `campeonatos_id` passam a aceitar nulo;
  índice único novo `(apostas_id, especiais_id)` (um palpite por categoria). `esporte = "ESPECIAL"`.
- **Pontos de integração** (arquivos existentes, listados no plano):
  - `CriacaoApostas::montar` carrega as opções e categorias pedidas e monta o palpite com a cotação
    fixa (`cotacao_original = cotacao_atual = opcao.cotacao`);
  - `RegrasAposta::conferir_palpites` e `ConferenciaCotacoes::garantir_disponiveis` recusam categoria
    inativa, não `Aguardando`, com data limite passada e opção inativa ou removida, com mensagens no
    padrão dos jogos;
  - `garantir_limite_por_confronto` ignora palpites especiais (não há limite por categoria);
  - `ValidacaoCodigos::simular` marca o especial indisponível com o motivo, como os jogos;
  - `ComprovanteApostaResource` e `SimulacaoApostaResource` mostram o especial: `time_casa =
    "Vencedor"`, `time_fora` = nome da categoria, `campeonato` = nome da categoria, `mercado` = nome
    da opção, `data_inicio` = data limite, `esporte = "ESPECIAL"`, `especiais_opcoes_id`.
- **Tipo da aposta**: especial conta como pré-jogo (não muda `TipoAposta`).
- **Cotação alterada**: a `cotacao_vista` diferente da cotação atual da opção segue a regra de
  cotação alterada dos jogos (409 com as cotações novas).

## R-11. Especiais: encerramento e cancelamento

- **Serviço** `EncerramentoEspeciais` numa transação, bloqueando a categoria:
  - encerrar(opção): grava `especiais_opcoes_id_vencedora`, `situacao = Encerrado`,
    `encerrado_em`/`encerrado_por`; marca `resultado` dos palpites ativos da categoria (Vencedor na
    opção, Perdedor nas demais) em apostas Ativas; recalcula o resultado de cada aposta afetada
    (FR-018a);
  - cancelar: `situacao = Cancelado`; cada palpite ativo da categoria em aposta Ativa é cancelado
    pela mesma regra da spec 004 (`EdicaoApostas`: palpite Cancelado, prêmio recalculado, histórico
    com o autor). Como o `EdicaoApostas` recusa cancelar o último palpite ativo, o cancelamento pelo
    sistema usa um método novo que permite: sem palpite ativo, a cotação total fica 1,00 e o prêmio
    igual ao valor (devolução), como no antigo;
  - resultado da aposta: Perdedor se algum palpite ativo tem `resultado = Perdedor`; Vencedor se
    todos os palpites ativos são especiais com `resultado = Vencedor` ou se não há palpite ativo;
    senão Aguardando. `Pendente` (código ainda não validado) e `Em análise` não são tocadas; o
    especial delas fica indisponível na validação/decisão.
- **Sem pagamento**: nenhuma transação de saldo; o pagamento é da spec de apuração.
- **Alteração em arquivo existente**: `EdicaoApostas` ganha o método público de cancelamento pelo
  sistema reaproveitando `marcar`/`recalcular` (listado no plano, Princípio IV).

## R-12. Especiais na tela

- **Página**: com `esporte=ESPECIAL`, o `PaginaInicialController` usa `ListagemEspeciais` no lugar
  da listagem de jogos, devolvendo a mesma forma da prop `listagem` (`tipo: "especial"`,
  `campeonatos[{ id, nome, data_limite, opcoes[{ id, nome, cotacao }] }]`, `paises: [{ pais:
  "Especiais", campeonatos[{ id, nome, quantidade_confrontos, bandeira: null }] }]`, `meta`), para
  o menu, o filtro por categoria (`campeonato`), a busca (`busca` pelo nome da categoria) e a
  rolagem infinita funcionarem sem mudar o `SideMenu`. As abas de data não valem para especiais
  (como no antigo, que mostrava todos os futuros) e ficam ocultas.
- **API pública**: `GET /api/publico/especiais` com a mesma listagem, para o app e integrações.
- **Componentes**: `SpecialList` (lista) e `SpecialCard` (categoria com as opções), no visual do
  `components/specials` do antigo, reaproveitando `ChampionshipHeader` e `OddButton`.
- **Cupom**: palpite `{ tipo: "especial", confronto_id: especiais_id, codigo_cotacao: "especial",
  opcao_id, mercado: nome da opção, time_casa: "Vencedor", time_fora: categoria, ... }`; um por
  categoria pela mesma regra de `mesmo_jogo`. `palpite_valido` passa a aceitar `especial` e
  `montar_envio` manda `especiais_opcoes_id`. A versão do formato do cupom não muda (cupons salvos
  continuam válidos).


## R-13. Avisos (antigo popup)

- **Nome**: o recurso se chama **aviso** em tudo o que é novo: tabelas `avisos` e
  `avisos_leituras`, models `Avisos` e `AvisosLeituras`, rotas `avisos` e `publico/avisos`,
  permissão `avisos.gerenciar` e textos da tela (decisão de 2026-10-10: nomes intuitivos, sem copiar
  o `popups` do antigo). O componente continua em inglês (`NoticeModal`). Na `Home`, a prop `aviso`
  da spec 005 (mensagem de atenção da listagem) não muda; o estado do aviso da banca se chama
  `aviso_banca`, para os dois não se confundirem.
- **Tabelas**: `avisos` e `avisos_leituras` ([data-model.md](data-model.md)). Imagem no disco
  `public` (`storage/app/public/avisos`), servida por `/storage/avisos/...` (exige `php artisan
  storage:link`, registrado no quickstart), com o nome pelo hash do conteúdo (R-20).
- **Aparelho**: UUID gerado com `crypto.randomUUID()` e guardado em `wssports.aparelho`; "Limpar
  cache" apaga e um novo é gerado (FR-030).
- **Entrega**: depois que a lista carrega, a `Home` chama `GET /api/publico/avisos/atual?aparelho=`
  (com o token do cliente, se houver); o servidor sorteia um aviso ativo, no período, com imagem e
  sem leitura do aparelho nem do cliente. Assim o aviso não atrasa a página (FR-032) e não depende
  do token chegar na primeira carga do Inertia.
- **Leitura**: `POST /api/publico/avisos/{aviso}/leituras` com `aparelho`; com token de cliente grava
  também `clientes_id`. Repetir não duplica (`firstOrCreate`). Limite de requisições
  (`throttle:30,1`).
- **Gerenciamento**: `apiResource('avisos')` no painel com upload (`multipart/form-data`, imagem até
  2 MB, `jpg`, `png` ou `webp`, sem redimensionar, para a imagem aparecer inteira) e permissão
  `avisos.gerenciar` (Admin e Supervisor), em `Funcao::PERMISSOES_SITE` (R-21).
- **Aviso padrão**: `AvisosSeeder` copia `database/seeders/files/aviso_padrao.png` para o disco
  público (nome pelo hash) e cria o aviso ativo só se a tabela nunca teve registro, nem removido
  (`withTrashed()->exists()`): rodar de novo não duplica nem recria um aviso que o administrador
  apagou (FR-027, FR-034).
- **Design (FR-031)**: componente `NoticeModal`: desktop → cartão centralizado (máx. 480px) sobre
  o `Backdrop`, imagem com `object-fit: contain` e altura máxima de 70vh; mobile → folha presa embaixo
  com cantos arredondados no topo, puxador visual e altura máxima de 85vh; X no canto superior; ações
  no rodapé: "Lido" (botão cheio na cor do tema) e "Fechar" (texto); `role="dialog"`,
  `aria-modal`, foco inicial no "Lido", Tab preso no modal, Esc fecha; transição de 200 ms
  (opacidade + deslocamento), respeitando `prefers-reduced-motion`; cores pelos tokens do tema (modos
  claro e escuro).

## R-14. Página de regras

- **Ordem dos blocos** (FR-021a): logo, regras da banca, regras de bônus, regras de apostas e
  limites de aposta. A barra com "Voltar" e o atalho do WhatsApp da spec 005 continuam; o título,
  o subtítulo e o cartão de regras numeradas saem.
- **Props** da página `Rules`:
  - `regras`: continua uma lista de parágrafos, agora vinda de `configuracoes.regras` (R-22),
    quebrada por linha no backend, sem linhas vazias. O formato não muda para o frontend;
  - `regras_bonus`: promoções `vigentes()` com `ativa = true`, por categoria e nome, com os campos
    usados no texto;
  - `limites_aposta`: configuração de quem vê, pelo `IdentificacaoPublico` +
    `RegrasExibicao::configuracao` (gestor = visitante, como na `Home`).
- **Regras de apostas**: textos fixos copiados do `screens/rules` do antigo para
  `resources/js/utils/market_rules.js` (lista de `{ titulo, itens: [{ rotulo, texto }] }`, mesma
  ideia do `utils/market_groups.js`). Ficam no frontend porque não mudam com a configuração nem com
  quem vê; uma spec futura pode torná-los editáveis.
- **Visual (FR-025)**: dois componentes novos, reaproveitados por todos os blocos:
  - `RulesBlock`: bloco com o título centralizado em caixa alta (como o `h6` do antigo); o título é
    opcional (o bloco da banca não tem título, como no antigo);
  - `RuleItem`: cabeçalho igual ao `Header` do antigo (ícone Material `directions_run`, título em
    caixa alta, bordas esquerda e inferior de 2px na cor do tema) seguido do conteúdo; linhas
    "**RÓTULO:** texto" em 0,9em.

  As cores de fundo e de texto vêm dos tokens do tema (modos claro e escuro da spec 005), no lugar
  do fundo preto e da seção branca fixos do antigo; a borda e o ícone usam `theme.principal`.
- **Componentes de conteúdo**: `BonusRules` (um `RuleItem` por promoção, texto montado como no
  antigo, trechos omitidos quando o campo é nulo, valores pelo `utils/money.js`), `MarketRules` (um
  `RuleItem` por mercado de `market_rules.js`) e `BetLimits` (um `RuleItem` "Limites de aposta"
  com os valores de quem vê). O bloco da banca é montado no próprio `RulesContent`.
- **Sessão**: a página chega sem token; com sessão de cliente ou vendedor, o `useSessao` já recarrega
  as props do público; `limites_aposta` entra na lista `props_do_publico` (arquivo existente).

## R-15. Promoções padrão

- **Seeder** `ClientesPromocoesSeeder` (idempotente por `categoria` + `nome`), todas `ativa = false`,
  `data_inicio` = momento da criação e `data_fim` nula, valores que passam no
  `ClientesPromocoesRequest`:

| Categoria | Modalidade | Tipo | Valor | Rollover | Aposta mín./máx. | Depósito máx. | Conversão máx. | Odd simples/múltipla |
|---|---|---|---|---|---|---|---|---|
| Primeiro cadastro | Esportes | Fixo | 10,00 | 10 | 1,00 / 100,00 | — | 100,00 | 1,50 / 1,30 |
| Primeiro depósito | Esportes | Percentual | 100 | 10 | 1,00 / 100,00 | 100,00 | 500,00 | 1,50 / 1,30 |
| Qualquer depósito | Esportes | Percentual | 10 | 5 | 1,00 / 100,00 | 500,00 | 200,00 | 1,50 / 1,30 |
| Indicação | Esportes | Fixo | 10,00 | 10 | 1,00 / 100,00 | — | 100,00 | 1,50 / 1,30 |

  O antigo criava os bônus só pelo painel e os padrões das colunas eram 0 (inválidos no sistema
  novo); os valores acima são uma proposta revisável, que só vale quando o administrador ativar.

## R-16. Dados fake

Saem do `DadosFake`: `banners()` (R-18), `regras()` (R-22) e a chave `logo` de `tema()` (R-19). Os
arquivos `public/fakes/banners/1.jpg` e `public/fakes/logo.png` passam a ser as imagens padrão
(`database/seeders/files/banner_padrao.jpg` e `public/images/logo_padrao.png`) e saem de
`public/fakes`. Continuam fake: cores do tema, contatos, indicadores e ícones do aplicativo
(favicon e PWA), até a spec de configurações visuais.

## R-17. Coleção do Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada na mesma entrega
(constituição), com as pastas novas: Especiais (painel), Avisos (painel), Banners (painel),
Configurações (regras e logo), Público (especiais, aviso atual, leitura) e Apostas (tabela de
jogos; palpite especial no corpo de exemplo).

## R-18. Banners

- **Tabela** `banners` (no lugar do `slides` do antigo): `imagem`, `link`, `ordem`, `ativo`
  ([data-model.md](data-model.md)).
- **Gerenciamento**: `apiResource('banners')` com upload (`jpg`, `png` ou `webp`, até 4 MB); a
  imagem é ajustada para 1280×405 como no antigo (esticada, sem corte, com GD; R-20) e salva em JPEG
  com qualidade 85. `ordem` é um número (menor primeiro; empate por `id`). Permissão
  `banners.gerenciar` (Admin e Supervisor).
- **Exibição**: o `PaginaInicialController` troca `DadosFake::banners()` por `Banners::ativos()`
  (ativos por `ordem` e `id`), no mesmo formato `{ imagem, link }` com a URL pública; o
  `BannerCarousel` não muda (já não aparece com a lista vazia, FR-032c).
- **Último banner**: pode ser removido ou desativado (o antigo recusava); sem banner ativo o
  carrossel some e a coluna sobe (spec, US6).
- **Banner padrão**: `BannersSeeder` copia `database/seeders/files/banner_padrao.jpg` e cria o
  banner só se a tabela nunca teve registro (`withTrashed()->exists()`).

## R-19. Logo

- **Onde fica**: coluna `logo` em `configuracoes` (caminho no disco `public`, `logos/{hash}.{ext}`).
  Sem logo enviada (`null`), vale a logo padrão do projeto, `public/images/logo_padrao.png`
  (endereço fixo, que nunca muda); assim a instalação já tem logo sem depender de seeder (FR-032d).
- **Envio**: `POST /api/configuracoes/logo` (`multipart/form-data`, `png`, `jpg` ou `webp`, até 1 MB,
  sem redimensionar; o tamanho na tela é dado pelo CSS, como hoje). Permissão `configuracoes.editar`.
- **Uso**: `Configuracoes::url_logo()` devolve a URL pública. O `TratarRequisicoesInertia` passa a
  montar `tema.logo` com essa URL (o restante de `tema` continua do `DadosFake`); assim `Header`,
  `Footer`, `AuthModal` e `RulesContent`, que já leem `tema.logo`, não mudam.
- **Fora**: favicon e ícones do PWA (o antigo gerava a partir da logo) continuam fake (FR-032e).

## R-20. Imagens com hash no nome (cache)

- **Decisão**: serviço `ArmazenamentoImagens`, usado por avisos, banners e logo:
  - `salvar(arquivo, pasta, ?largura, ?altura)`: lê o arquivo (e, com tamanho, redimensiona com GD:
    `imagecreatefromstring` + `imagecopyresampled` + `imagejpeg`), calcula o `sha1` do conteúdo
    final e grava em `{pasta}/{sha1}.{ext}` no disco `public` (se já existir, reaproveita);
  - `remover(caminho)`: apaga o arquivo só se nenhum outro registro (aviso, banner ou logo) ainda
    aponta para ele, porque dois envios iguais geram o mesmo nome.
- **Por quê**: trocar a imagem muda o endereço, e o navegador busca a nova sem limpar o cache
  (SC-008); a mesma imagem enviada de novo mantém o endereço. Em produção, o servidor pode servir
  `/storage/...` com cache longo (`immutable`), anotado no quickstart.
- **Troca e remoção**: ao trocar a imagem ou remover o registro (soft delete), o arquivo anterior é
  removido pelo `remover` depois da transação (FR-032f).
- **GD**: extensão do PHP já presente no ambiente; passa a ser declarada no `composer.json`
  (`"ext-gd": "*"`) para a exigência ficar explícita. Não é pacote novo.
- **Alternativas rejeitadas**: `intervention/image` (usado no antigo): dependência nova para um
  único redimensionamento; nome aleatório (`hashName()`): também resolve o cache, mas troca o
  endereço mesmo quando a imagem é a mesma e não evita arquivos repetidos.

## R-21. Permissões dos recursos do site

Constante nova `Funcao::PERMISSOES_SITE` = `avisos.gerenciar`, `banners.gerenciar` e
`configuracoes.editar` (logo e texto das regras), usadas só por Admin e Supervisor (`pode_usar`,
como as de especiais). Criadas pelos seeders `AvisosSeeder`, `BannersSeeder` e
`PapeisPermissoesSeeder` (para `configuracoes.editar`) e entregues aos usuários existentes da
função, no padrão do `ConfrontosSeeder`.

## R-22. Texto das regras da banca

- **Onde fica**: coluna `regras` (`text`, nula) em `configuracoes`, um texto único da banca
  (decisão de 2026-10-10: só do administrador, sem texto por gerente).
- **Padrão**: constante `Configuracoes::REGRAS_PADRAO` com os três parágrafos do sistema antigo
  (spec, FR-021b). A migration preenche o registro existente; o model usa a constante como valor
  inicial (`$attributes`) para o registro criado depois pelo seeder. Como nem toda versão do MySQL
  aceita padrão em coluna `text`, o padrão fica no código e não na coluna.
- **Edição**: `GET` e `PUT /api/configuracoes/regras` (`regras`: texto até 10.000 caracteres;
  permissão `configuracoes.editar`). Texto vazio é gravado como `""` (o middleware transforma string
  vazia em `null`, então o controller grava `""` explicitamente) e esconde o bloco; nenhum seeder
  sobrescreve o texto.
- **Segurança**: texto simples; a página mostra cada linha como parágrafo pelo React (sem
  `dangerouslySetInnerHTML`), então HTML ou script aparecem como texto (o antigo injetava o HTML do
  gerente com `innerHTML`).
