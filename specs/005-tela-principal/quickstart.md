# Quickstart: validação manual da tela principal (área `/`)

**Feature**: `005-tela-principal` | **Data**: 2026-10-09

Sem testes automatizados (Constituição): esta é a validação manual da spec. Cada cenário aponta
para os requisitos que confere.

## Pré-requisitos

- Backend das specs 001 a 004 funcionando (banco `wssports` com confrontos importados para hoje).
- Sistema antigo rodando ao lado para comparação visual (mesmos jogos, se possível).
- Navegadores: Chrome (desktop e Android) e Safari (iOS) para o PWA.

## Preparação

```bash
composer install
npm install
npm run build        # gera public/build com arquivos com hash e o service worker
php artisan serve    # http://127.0.0.1:8000
```

Para desenvolvimento com recarga rápida: `npm run dev` (o service worker só é testado com
`npm run build`).

Os dados fake ficam em `app/Fakes/DadosFake.php` e `public/fakes/` (Princípio IX). Para os
cenários de tema, troque ali `temas` e `cor_fundo`.

## 1. Fidelidade visual (FR-002, SC-001)

1. Abra `/` no sistema novo e o sistema antigo lado a lado, no modo escuro.
2. Nas larguras 360, 414, 768, 900, 901, 1024, 1366 e 1920px (DevTools), confira cada linha do
   inventário visual ([research.md](research.md), R-07 e R-21): medidas, cores, espaçamentos,
   textos e posição.
3. Diferenças aceitas: colunas laterais com no mínimo 240px, altura visível real no celular,
   espaço para o WhatsApp no fim da lista e o botão dia/noite.

**Esperado**: nenhuma outra diferença.

## 2. Responsivo (FR-003 a FR-003d)

1. Com a janela em 901px, reduza para 900px: layout e comportamento passam para o mobile sem
   recarregar. Volte para 901px: voltam ao desktop.
2. No celular, gire a tela: o layout acompanha.
3. No celular, role até o fim da lista: o último jogo fica livre do botão do WhatsApp, e a barra
   do navegador não esconde os botões do cupom.
4. Tente ampliar com os dedos: o zoom continua bloqueado.

## 3. Jogos do dia (US1; FR-019 a FR-029)

1. Abra `/`: "Carregando jogos." e depois os jogos de futebol de hoje, agrupados por campeonato.
2. Um jogo que começa em menos de 60 minutos tem o horário na cor do tema.
3. Role até perto do fim: mais jogos carregam sem recarregar a tela.
4. Use um filtro sem jogos: aparece a mensagem de lista vazia.

## 4. Cupom (US2; FR-034 a FR-040)

1. Clique em C de um jogo: o botão fica selecionado e o palpite entra no cupom.
2. Clique em F do mesmo jogo: o palpite troca. Clique em F de novo: o palpite sai.
3. Monte 3 palpites, toque em 10: o valor vira 10,00, e a cotação total e o retorno são
   recalculados. Confira o retorno com a fórmula do backend (`CalculoPremio`): valor ×
   cotações, truncado em centavos, limitado pelo multiplicador e pelo prêmio máximo.
4. O "vendedor paga" aparece ao lado da cotação total com o mesmo valor do retorno (FR-037a).
5. Recarregue a página: o cupom continua igual.
6. "Limpar" → confirme: o cupom fica vazio.
7. No celular, toque em "Conferir": o cupom abre sobre a tela.
8. No `localStorage`, troque `wssports.cupom` por um texto inválido e recarregue: o cupom abre
   vazio, sem erro.

## 5. Código da aposta (US3; FR-041 a FR-043, FR-033a)

1. Monte um cupom de pré-jogo, informe o nome e clique em "Finalizar": aparece o indicador de
   carregamento e depois o modal de sucesso com o código; o cupom fica vazio.
2. Clique duas vezes rápido em "Finalizar": só um código é gerado.
3. Valor abaixo do mínimo do visitante: aparece a mensagem do backend e o cupom continua.
4. Inclua um palpite do ao vivo e finalize: aparece "Para apostar no ao vivo é preciso fazer
   login." e o cupom continua.
5. Com a cotação alterada no banco depois de montar o cupom (409): aparece a confirmação com o
   novo prêmio; "Sim" gera o código com a cotação nova.

## 6. Navegação e filtros (US4; FR-009 a FR-018, FR-025 a FR-027)

1. Troque de esporte: o esporte fica destacado e a lista muda. "Cassino" e "Especiais" não fazem
   nada.
2. Troque entre Hoje, Amanhã e o dia de depois de amanhã.
3. Clique num campeonato do menu: a lista mostra só ele; no celular a gaveta fecha.
4. Digite um time e pressione Enter; apague o texto: a lista volta.
5. Nas configurações de visitante, desative "outros esportes": só o futebol aparece e a barra
   some.

## 7. Detalhe do jogo, ao vivo e bilhete (US5 a US7)

1. Clique em "+N": abre o modal com os mercados; escolha uma cotação: ela entra no cupom e o "+N"
   fica na cor do tema.
2. Abra "Ao vivo": placar, período e minuto; as cotações mudam a cada 7 segundos e os botões
   piscam em verde ou vermelho. Troque para Futebol: as atualizações param (aba Rede do
   DevTools).
3. Digite um código existente no campo do bilhete e pressione Enter: abre o modal do bilhete.
   Um código inexistente mostra a mensagem de não encontrado.

## 8. Tema e modo dia/noite (US8; FR-004, FR-005, FR-053 a FR-056)

1. Em `DadosFake::tema()`, teste as 6 cores principais: todos os elementos tematizados mudam, e o
   selo das letras usa a cor derivada.
2. Com `cor_fundo` `#000000`, a primeira visita abre no modo escuro; com `#FFFFFF`, no claro.
3. Toque no botão dia/noite: a tela troca na hora, sem recarregar. Recarregue: o modo escolhido
   continua, sem piscar o outro modo.
4. "Limpar cache": o modo volta ao de `cor_fundo` e o cupom fica vazio.

## 9. Atualização instantânea e cache (FR-049 a FR-050c, SC-009, SC-010)

1. Abra `/` com o site gerado por `npm run build` e monte um cupom.
2. Mude um texto qualquer do frontend e rode `npm run build` de novo (nova versão).
3. Na aba aberta, troque de esporte: a página recarrega sozinha na versão nova e o cupom continua.
4. Repita o passo 2, mude para outra aba e volte: a página recarrega na versão nova.
5. Com o PWA instalado, repita o passo 2 e reabra o app: já abre na versão nova, sem reinstalar
   e sem limpar o cache.
6. Mude um banner em `DadosFake::banners()` (sem build) e recarregue: o banner novo aparece.
7. No DevTools > Application > Cache Storage: só arquivos com hash, fontes, imagens fixas e
   `/offline.html`; nenhum `/api/`, `/fakes/` nem a página da tela.
8. No DevTools, marque "Offline" e recarregue: abre a página offline com a mensagem de erro de
   conexão; nenhum jogo ou cotação antiga aparece. Com "Slow 3G" (conectado), a página vem
   sempre da rede, mesmo demorando.
9. Monte um cupom, gere uma versão nova (`npm run build`) e, com a rede em "Slow 3G", clique em
   "Finalizar" e troque de aba e volte durante o envio: o envio termina, o código aparece e só
   depois a página recarrega na versão nova.

## 10. PWA e menu (US9; FR-013 a FR-017, FR-045 a FR-048)

1. No celular, abra o menu: gaveta da esquerda com "Menu", o X, Acumuladão, Limpar cache, Regras
   e os campeonatos; não aparecem Impressão e Largura.
2. "Acumuladão" não faz nada. "Regras" abre a tela de regulamento.
3. Toque no WhatsApp: abre a conversa com a mensagem padrão.
4. Instale o site (Chrome: "Instalar app"; iOS: "Adicionar à Tela de Início"): abre em tela
   cheia, com o nome e o ícone.

## Produção (servidor web)

Configure no servidor:

- `/build/assets/*`: `Cache-Control: public, max-age=31536000, immutable`;
- páginas, `/sw.js` e `/manifest.webmanifest`: `Cache-Control: no-cache` (o Laravel já envia
  nas páginas e nas rotas do PWA).

A cada deploy, rode `npm run build`; os usuários passam para a versão nova sozinhos (seção 9).
