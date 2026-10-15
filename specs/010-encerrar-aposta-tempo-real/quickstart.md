# Quickstart: validação manual do bilhete em tempo real, do encerramento e das notificações

Sem testes automatizados (constituição). Os cenários seguem a [spec](spec.md). Formatos em
[contracts/api.md](contracts/api.md), [contracts/tempo_real.md](contracts/tempo_real.md) e
[contracts/comandos.md](contracts/comandos.md); estados e fórmulas em
[data-model.md](data-model.md) e [research.md](research.md).

## Preparação

```bash
composer install && npm install
php artisan migrate
php artisan db:seed --class=EncerramentoSeeder
php artisan notificacoes_push:gerar_chaves   # copiar o par para o .env
```

No `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`,
`REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` e
`VAPID_SUBJECT` (exemplos no `.env.example`).

Processos (o `composer dev` sobe servidor, fila, logs, Vite e Reverb):

```bash
composer dev
php artisan queue:work --queue=apostas,notificacoes,default --tries=1   # se o dev não atender as filas
php artisan schedule:work
```

- **Push** exige contexto seguro: `localhost` no desktop ou um túnel HTTPS (ngrok) no celular. O
  `npm run build` é necessário para o service worker (ele só é registrado em produção).
- **Dados do ao vivo**: usar o mock do provedor das specs 003 e 009. O placar, a situação, o minuto
  e as cotações mudam no mock, e a carga `confrontos_ao_vivo:importar` (a cada 5 s) leva a mudança.
- **Janela**: `PUT /api/configuracoes/apuracao` com `minutos_pagamento_premio: 0` para não esperar
  o crédito (os cenários de janela usam 5).
- **Massa**: jogos A e B no pré-jogo, com A também no mock do ao vivo; cliente C1 com R$ 200,00 em
  Saldo e R$ 50,00 em Promoção esportes; cliente C2; um vendedor; um Admin.
- **Conferir comentários**: `SHOW FULL COLUMNS FROM apostas_encerramentos;` (e
  `apostas_encerramentos_automaticos`, `clientes_inscricoes_notificacoes`, `clientes_notificacoes`,
  `confrontos_eventos`, `apostas`, `configuracoes`, `clientes_configuracoes`).
- **Conferir registros**: `SELECT modalidade, situacao, oferta, valor, margem, fracao_antes,
  fracao_depois, palpites FROM apostas_encerramentos ORDER BY id DESC;`

## US1 — Bilhete em tempo real

| # | Passos | Esperado | Requisito |
|---|---|---|---|
| 1 | Aposta de C1 com A (ao vivo, 1 x 0, 2º tempo) e B (não iniciado). Abrir `/bilhete/{codigo}` em aba anônima | Resumo com "José W.", valor, cotação total e prêmio potencial; barra com um segmento por palpite e legenda; A com placar, período, minuto, 1º/2º tempo e escanteios; B com "começa em X min"; sem bloco de encerramento | US1-1, FR-001 a FR-004, FR-016 |
| 2 | Mesma aposta, logado como C1 | Nome completo e bloco de encerramento | FR-002, US2-1 |
| 3 | Mock: gol em A | Em até 5 s, sem recarregar: placar e gols do tempo mudam e o placar recebe o destaque passageiro | US1-2, FR-008, SC-001 |
| 4 | Palpite "Casa" em A com 1 x 0 e depois 1 x 1 | "Ganhando agora" e depois "Perdendo agora" | US1-3, FR-005 |
| 5 | Palpite de escanteios em A com o mock sem escanteios | Selo "Aguardando" | FR-005 |
| 6 | Parar o Reverb com a página aberta | Indicador "Reconectando…"; a página continua atualizando a cada 10 s (conferir na aba Rede); "atualizado há X s" muda. Subir o Reverb → volta a "Ao vivo" | US1-5, FR-009, SC-002 |
| 7 | Aposta decidida (resultado gravado, spec 009) | Resultado de cada palpite (Vencedor, Perdedor, Devolvido, Cancelado); nenhuma requisição nova na aba Rede | US1-4, US1-6, FR-006, FR-010 |
| 8 | Abrir `/bilhete/XXXXXXXX`, e o código de uma aposta Pendente, Recusada e Expirada | "Bilhete não encontrado ou ainda não validado", sem dados | US1-7, FR-011 |
| 9 | Aposta com bloco Criar Aposta | Bloco agrupado pelo jogo, com a situação dele | FR-007 |
| 10 | Home → conferir bilhete pelo código → ícone "acompanhar em tempo real"; depois compartilhar | Abre `/bilhete/{codigo}`; o texto compartilhado traz o link | US1-8, FR-015 |
| 11 | Minha Conta → tocar numa operação de aposta | Abre direto `/bilhete/{codigo}`, sem o cupom | US1-9, FR-015 |
| 12 | Repetir 1 a 3 em 360 px, em desktop, nos modos escuro e claro e em 2 cores de tema (trocar `DadosFake::tema`) | Sem rolagem horizontal; uma coluna com barra fixa (só para o dono) no celular; duas colunas com o resumo acompanhando a rolagem no desktop; cores de estado distintas da cor principal | FR-012, FR-013, SC-009 |
| 13 | Navegar só pelo teclado; leitor de tela no 3; sistema com "reduzir movimento" | Foco visível; o gol é anunciado; sem animação do destaque | FR-014 |

## US2 — Encerrar a aposta

| # | Passos | Esperado | Requisito |
|---|---|---|---|
| 14 | C1 aposta R$ 50,00 (Saldo) em A + B, nenhum iniciado | Oferta = prêmio ÷ cotações atuais × 0,93, truncada (conferir à mão); abas Total, Parcial e Automático | US2-1, FR-019 a FR-021 |
| 15 | Mudar a cotação de B no mock do pré-jogo e rodar `confrontos_cotacoes:importar` | Em até 60 s, a oferta muda com a indicação "subiu" ou "caiu" | US2-2, FR-023 |
| 16 | Total → Encerrar → confirmar dentro de 8 s | Em até 3 toques: "Aposta encerrada" com o valor e o horário previsto do crédito; aposta `Encerrada`; encerramento `Registrado` com `creditar_em` = agora + janela | US2-3, FR-024, FR-027, SC-003 |
| 17 | `php artisan apostas:liquidar_encerramentos` depois da janela | Crédito em Saldo, origem `Encerramento de aposta`, `referencia_id` = aposta; situação `Creditado`; Minha Conta mostra a operação ligada à aposta | FR-032, SC-007 |
| 18 | Abrir a confirmação e esperar 8 s | A confirmação some e a oferta atual reaparece | US2-4, FR-024 |
| 19 | Aposta com A ao vivo; confirmar e, durante o delay do cliente, fazer gol no mock | 202 "Em análise" → recusado com "Houve lance…" e a nova oferta | US2-5, FR-025, SC-005 |
| 20 | Como o 19, sem lance, com cotação mudando; preferência "Somente para maior" | Aceito se a oferta nova ≥ a aceita (paga a nova); recusado se caiu | FR-025 |
| 21 | Tocar duas vezes em "Encerrar" (ou reenviar o mesmo pedido no Postman) | Um único encerramento e um único crédito | FR-026, SC-004 |
| 22 | Parcial: receber metade da oferta | Antes de confirmar mostra o valor apostado e o prêmio que continuam valendo; depois, `fracao_restante` ≈ 0,5; o bilhete mostra os valores reduzidos | US2-6, FR-028 |
| 23 | Fazer o parcial até o limite (5) | A aba Parcial fica indisponível com o motivo; o Total continua | FR-029 |
| 24 | Automático com alvo menor ou igual à oferta | Recusado com a sugestão de valor maior | Edge, FR-030 |
| 25 | Automático com alvo maior; mudar as cotações no mock até a oferta passar o alvo | Em até 10 s (ao vivo) ou 1 min (pré-jogo): aposta `Encerrada`, modalidade `Automático`, automático `Executado`, aviso push | US2-7, FR-030, SC-006 |
| 26 | Automático ativo e encerramento parcial manual | Automático `Cancelado` ("Encerramento manual") | US2-8, FR-031 |
| 27 | Cada caso: palpite Perdedor; jogo travado (parar a carga); código ausente no mock (mercado suspenso); bloco Criar Aposta; aposta com Promoção esportes; aposta de vendedor; cliente com `encerrar_aposta` desligado; encerramento geral desligado | Sem oferta e com o motivo da R-05 (bloco nem aparece para vendedor e visitante) | US2-9, FR-016, FR-017, SC-010 |
| 28 | `encerramento_jogos_nao_iniciados` desligado; aposta sem jogo iniciado | "O encerramento fica disponível quando o primeiro jogo começar." | FR-036 |
| 29 | Aposta do 16 (total); jogos terminam com acerto | Palpites apurados `Vencedor`; aposta continua `Encerrada`; nenhum prêmio | US2-10, FR-034 |
| 30 | Aposta do 22 (parcial) vence | Prêmio pago = `total_a_pagar` reduzido | FR-034 |
| 31 | Cliente com rollover de depósito pendente: aposta soma; encerrar total; repetir com parcial | Total: o progresso volta ao valor de antes da aposta. Parcial: sai valor × proporção. `apostas_encerramentos.rollovers` com o que saiu | FR-038 |
| 32 | Corrida: gravar o resultado de A (`Encerrado`, palpite perdido) e, antes da apuração da aposta, encerrar | Recusado "Aguardando a apuração de A". Forçar no banco um encerramento com fotografia anterior ao resultado e rodar `apostas:reapurar {A}` → `Desfeito`, sem crédito (ou estorno se já creditado), rollover devolvido, registro "Encerramento desfeito" | Edge, FR-033 |
| 33 | Acesso assistido do Admin em C1 → Minha Conta → operação de aposta → encerrar | Encerrado; `usuarios_id` = Admin | FR-016, FR-035 |
| 34 | Painel: `GET /api/apostas-encerramentos` com Admin, Supervisor e Gerente; filtros de período, cliente e modalidade; `por_pagina=500` | Admin e Supervisor listam (no máximo 100 por página) com `teria_pago`; Gerente 403 | FR-037 |
| 35 | `PUT /api/configuracoes/encerramento` com margem 60 | 422 com a mensagem da faixa | FR-036 |
| 36 | Página `/regras` com o encerramento ligado e depois desligado | Seção com as regras e os valores atuais; some quando desligado | FR-039 |

## US3 — Notificações

| # | Passos | Esperado | Requisito |
|---|---|---|---|
| 37 | C1 no bilhete toca no sino e permite | Inscrição criada com os 5 tipos ligados; pedido de permissão só depois do toque | US3-1, FR-040, FR-041 |
| 38 | C1 com aposta ativa em A; gol no mock; app fechado | Aviso "⚽ GOL!" com times, placar e minuto em até 15 s; tocar abre o bilhete | US3-2, FR-045, FR-048, SC-008 |
| 39 | Placar diminui no mock | Aviso de gol anulado com o placar corrigido | US3-3 |
| 40 | Mock em `Intervalo`; depois o resultado `Encerrado` pela carga de resultados | Aviso de intervalo e aviso de fim de jogo, cada um uma vez | US3-4, FR-042 |
| 41 | Aposta de C1 decidida (ganhou) | Aviso com o código e o prêmio | US3-5, FR-044 |
| 42 | C1 com 3 apostas em A; gol | Um único aviso por aparelho; o aviso novo de A substitui o anterior na central | US3-6, FR-043, FR-045 |
| 43 | C1 desliga "Gols"; gol; depois intervalo | Sem aviso de gol; aviso de intervalo chega | US3-7 |
| 44 | iPhone pelo Safari, sem instalar; tocar no sino | Orientação para instalar o aplicativo na tela de início | US3-8, FR-047 |
| 45 | Remover o site das permissões do navegador (inscrição invalidada); gol | A inscrição é removida no primeiro envio (`deleted_at`); os outros aparelhos recebem | FR-046 |
| 46 | Cliente sem aparelho inscrito; gol | `confrontos_eventos` registrado; nada enviado | Edge |
| 47 | Gol e intervalo na mesma carga | Dois avisos, sem repetição na carga seguinte | Edge |

## Fechamento

- Postman: as 12 rotas novas e o campo `encerrar_aposta` na coleção regenerada.
- `php artisan route:list --path=api` mostra as rotas com os middlewares do [contrato](contracts/api.md).
- Desligar o Reverb e conferir que `confrontos_ao_vivo:importar` continua gravando (só warning no
  log).
