# Quickstart: validação manual dos ajustes da tela principal

**Feature**: `006-ajustes-tela-principal` | **Data**: 2026-10-10

Sem testes automatizados (constituição): cada seção é um roteiro manual ligado aos cenários da
[spec](spec.md). Rotas e corpos em [contracts/api.md](contracts/api.md); dados em
[data-model.md](data-model.md).

## 1. Preparação

```powershell
php artisan migrate
php artisan db:seed --class=PapeisPermissoesSeeder
php artisan db:seed --class=EspeciaisSeeder
php artisan db:seed --class=AvisosSeeder
php artisan db:seed --class=BannersSeeder
php artisan db:seed --class=ClientesPromocoesSeeder
php artisan storage:link
npm run build
php artisan serve
```

- Rodar os seeders de novo não duplica nada e não recria aviso ou banner que o administrador
  removeu (FR-034).
- Em produção, `/storage/avisos`, `/storage/banners` e `/storage/logos` podem ter cache longo no
  servidor web (`Cache-Control: public, max-age=31536000, immutable`), porque o nome do arquivo muda
  quando a imagem muda.
- Para o Bluetooth: Chrome no Android, site em HTTPS (ou `localhost` por depuração USB) e uma
  impressora térmica pareada.
- Usuários: um vendedor e um admin (DatabaseSeeder); um cliente cadastrado pelo site.

## 2. Link do bilhete (US1)

1. Sem login, monte um cupom com 2 jogos e gere o código. Copie o link `/?code=XXXXXXXX`.
2. Entre como vendedor e abra o link: o cupom traz os palpites, abre no mobile e o botão é
   "Validar" (verde). Valide → "Bilhete validado com sucesso!".
3. A barra de endereço fica sem `?code=`; recarregar não repete a validação.
4. Abra o mesmo link sem login: abre o modal do bilhete. Abra `/?code=NAOEXISTE`: mensagem de não
   encontrado e a tela normal.
5. Com o cupom já montado, abra outro link como vendedor: aparece a confirmação de substituir.
6. Em nenhum desses casos o aviso aparece.

## 3. Impressão do vendedor e tabela (US2)

1. Como vendedor no celular, abra o menu: Impressão (PADRÃO), Largura (80 mm) e Tabela. No
   computador, só Tabela. Como visitante/cliente/gestor, nenhum desses itens.
2. Toque em Impressão e em Largura: alternam (APP / 58 mm) e continuam assim ao recarregar.
3. PADRÃO + 80 mm: faça uma aposta e toque em "Imprimir" → escolha a impressora → o bilhete sai em
   48 colunas, sem acentos e sem texto cortado. Imprima outro: sai direto, sem escolher (SC-002).
4. Troque para 58 mm e imprima: 32 colunas.
5. Cancele a escolha da impressora: nada acontece, sem erro. Desligue a impressora e imprima:
   mensagem de erro; ao religar, a próxima impressão pede a impressora de novo.
6. APP: "Imprimir" abre `app://{site}/{codigo}/{58|80}/false`.
7. iPhone (Safari) em PADRÃO: mensagem de aparelho sem Bluetooth sugerindo o modo APP.
8. Tabela → "Jogos de Hoje" e "Jogos de Amanhã": a tabela sai com as cotações do vendedor (compare 3
   jogos com a lista, SC-003), cabeçalho repetido a cada 5 campeonatos. "Jogos por Campeonatos":
   marque 2 campeonatos e imprima só eles. Dia sem jogos: "Nenhum jogo encontrado.".
9. No computador, Tabela e Imprimir abrem a impressão do navegador (tabela com 14 colunas).

## 4. Especiais (US3)

1. Como admin, `POST /api/especiais` com uma categoria e 3 opções (data limite amanhã).
2. No site, "Especiais" na barra: a categoria aparece com as opções e cotações; o menu mostra o país
   "Especiais" com a categoria. Busca pelo nome da categoria funciona.
3. Escolha uma opção → cupom "Vencedor: categoria". Outra opção da mesma categoria troca; a mesma
   remove. Junte com 2 jogos de futebol e finalize como visitante, como cliente e como vendedor.
4. Mude a cotação da opção pelo painel e finalize um cupom antigo: aparece a confirmação de cotação
   alterada. Desative a opção: o palpite é recusado com o motivo.
5. Valide como vendedor um código de visitante com especial: o especial entra na simulação.
6. Encerre a categoria (`POST /api/especiais/{id}/encerrar`) com a opção de uma das apostas:
   - palpite na vencedora → `resultado = Vencedor`; nas demais → `Perdedor`;
   - aposta só com especial vencedor → `Vencedor`; com especial perdedor → `Perdedor`;
   - aposta com especial vencedor e jogos sem resultado → `Aguardando`.
7. Cancele outra categoria: palpites `Cancelado`, cotação total e prêmio recalculados, histórico
   com o admin; aposta só com esse especial → prêmio igual ao valor e `Vencedor`.
8. Categoria com data limite passada não aparece; palpite antigo dela é recusado no "Finalizar".
9. Nenhuma transação de saldo é criada por encerrar ou cancelar.

## 5. Regras e promoções padrão (US4)

1. Abra `/regras`: logo, bloco da banca com os três parágrafos padrão (sem título), "Regras de
   apostas" com os mercados do sistema antigo e "Limites de aposta", todos no visual de cabeçalho
   com ícone e bordas na cor do tema, nos modos claro e escuro.
2. `PUT /api/configuracoes/regras` com um texto de 2 linhas: recarregue e veja 2 parágrafos. Envie
   `<b>teste</b><script>alert(1)</script>`: aparece como texto, sem negrito e sem alerta. Envie
   vazio: o bloco da banca some.
3. `GET /api/clientes-promocoes`: 4 promoções (uma por categoria), todas inativas.
4. Sem promoção ativa: sem o bloco "Regras de bônus". Cadastre um cliente: nenhum bônus é
   creditado (SC-006).
5. Ative as 4 promoções: o bloco mostra um item por promoção, abaixo das regras da banca e acima
   das regras de apostas, com o texto montado pelos campos (valor fixo ou %, rollover, conversão
   máxima, depósito máximo só nas de depósito, apostas mínima e máxima, cotações mínimas simples e
   múltipla).
6. Mude o rollover de uma promoção e o valor mínimo de aposta do visitante: recarregue `/regras` e
   veja os valores novos (SC-005).
7. "Limites de aposta": como visitante, os do visitante; como cliente e como vendedor, os deles.

## 6. Aviso (US5)

1. Abra `/` num navegador novo: depois da lista, aparece o aviso padrão (cartão no desktop, folha
   de baixo no mobile), nos modos claro e escuro.
2. "Fechar", tocar fora ou Esc: fecha; recarregue → aparece de novo.
3. "Lido" → confirmação "Realizando essa ação esse aviso não irá aparecer mais para você,
   confirma?" → não aparece mais nesse navegador (SC-007). Em outro navegador ainda aparece.
   Logado como cliente, "Lido" num aparelho → some também no outro aparelho do cliente.
4. "Limpar cache" → o aviso lido só pelo aparelho volta a aparecer.
5. Cadastre um aviso com link pelo painel (`POST /api/avisos`, multipart): tocar na imagem abre o
   link em outra aba. Com 2 avisos não lidos, cada abertura mostra um.
6. Desative todos: nenhum aviso, nenhum erro.

## 7. Banners e logo (US6)

1. Abra `/`: o carrossel mostra o banner padrão e o cabeçalho, a logo padrão
   (`/images/logo_padrao.png`).
2. `POST /api/banners` com duas imagens (`ordem` 2 e 1, uma com `link`): o carrossel mostra as duas
   na ordem 1, 2, com a imagem em 1280×405; tocar no banner com link abre o link. Arquivo `.pdf`
   ou acima de 4 MB → `422`.
3. Desative todos os banners: o carrossel some e a coluna sobe, sem erro.
4. `POST /api/configuracoes/logo`: cabeçalho, rodapé, login e `/regras` mostram a logo nova. Envie
   outra logo e recarregue sem limpar o cache: a nova aparece e a URL (`/storage/logos/{hash}.png`)
   mudou (SC-008); o arquivo da anterior foi apagado de `storage/app/public/logos`.
5. Envie de novo a mesma logo: a URL não muda.
6. Troque a imagem de um banner: o arquivo antigo some de `storage/app/public/banners`, desde que
   nenhum outro banner use a mesma imagem.

## 8. Postman

Abra a coleção `docs/postman/wssports_api.postman_collection.json`: estão as rotas de especiais,
opções, encerrar/cancelar, avisos, banners, `configuracoes/regras`, `configuracoes/logo`,
`tabela-jogos`, `publico/especiais`, `publico/avisos/atual` e leituras, e o exemplo de palpite
especial nas rotas de aposta.
