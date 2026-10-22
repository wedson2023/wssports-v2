# Quickstart: Painel — Apostas (validação manual)

**Spec**: [spec.md](spec.md) · **Contratos**: [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md) · **Dados**: [data-model.md](data-model.md)

## 0. Preparação

```bash
php artisan migrate          # índice de apostas e permissões apostas.listar/exportar/imprimir_segunda_via
npm run build                # ou npm run dev
```

- Usuários: Admin; Supervisor S; Gerentes G1 e G2 (de S); Vendedores V1 e V2 (de G1) e V3 (de G2);
  um cliente do site C1.
- Conferir que todos receberam as três permissões novas (tela de permissões, spec 015).
- Validar nos temas escuro e claro e em 360 px de largura.

## 1. Acesso e alcance (FR-001 a FR-004, SC-003)

1. Hoje: V1 faz as apostas A (R$ 100,00, 2 palpites), B (R$ 50,00, 1 palpite ao vivo) e D (R$ 20,00,
   3 palpites, com valor acrescido); V3 faz E (R$ 30,00); C1 faz F no site (R$ 40,00, saldo); um
   visitante gera um código Pendente P.
2. Admin → menu "Apostas": A, B, D, E e F (F com vendedor "Site"); P não aparece.
3. S: A, B, D, E e F. G1: A, B e D (sem E e sem F). V1: A, B e D, sem o filtro de vendedor.
4. G1 pede `GET /api/apostas/{E}` → 404; `GET /api/apostas?usuarios_id={V3}` → 403;
   `GET /api/apostas/{F}` → 404.
5. Usuário sem `apostas.listar`: menu sem "Apostas"; `/painel/apostas` volta para `/painel`;
   `GET /api/apostas` → 403.

## 2. Lista, período, abas e colunas (US1, FR-005 a FR-008)

1. Admin em "Hoje": colunas código, vendedor, cliente, valor, comissão, prêmio, situação, jogos e
   horário; D mostra o prêmio com o valor acrescido somado (igual ao "Prêmio" do bilhete).
2. Aba Ao vivo: só B. Pré-jogo: A, D, E e F. Trocar para "Ontem": lista vazia com a orientação.
3. Cancelar um palpite de A (como G1): a coluna Jogos de A passa a "1/1".
4. Paginação: com mais de 20 apostas no período, a página 2 continua a ordem (mais nova primeiro).

## 3. Busca e filtros (FR-009 a FR-011)

1. Buscar o código completo de uma aposta de ontem com o período em "Hoje": ela é encontrada.
2. Buscar parte do nome do cliente de uma aposta de ontem: não aparece; marcar "Buscar em todo o
   período": aparece.
3. Filtros: Resultado Premiadas, Vendedor V1 (escolhido na busca com resultados), Forma de pagamento
   Saldo (só F), Prêmio mínimo R$ 200,00; cada mudança recarrega lista e cartões; as etiquetas
   aparecem e o ✕ remove.
4. Recarregar a página e voltar do bilhete: os mesmos filtros, aba e página.

## 4. Totais iguais ao Caixa (FR-012, FR-013, SC-002)

1. Apurar A como vencedora e B como devolvida. Admin, Hoje, filtro Vendedor V1: Apostas, Entradas,
   Comissão e Saídas iguais à linha de V1 no Caixa (spec 017), em Hoje e na mesma aba de tipo.
2. Admin e S veem o cartão Encerramentos; G1 e V1 não. Encerrar F pelo cliente (spec 010): o cartão
   mostra o valor pago e F aparece como "Encerrada" com o valor pago no Prêmio.
3. Aba Canceladas: só os cartões de quantidade e valor cancelado.

## 5. Bilhete (US2, FR-014 a FR-016)

1. Abrir D: dados, palpites (placar e período nos jogos em andamento), cotação total, comissão,
   valor, prêmio, valor acrescido e "Vendedor paga" (quando houver comissão por prêmio).
2. Compartilhar: no celular abre o compartilhamento; no computador, o WhatsApp; sem
   compartilhamento, "Link copiado.". O link abre `/bilhete/{codigo}`.
3. Acompanhar em tempo real: abre `/bilhete/{codigo}`.
4. 2ª via em D (aguardando): imprime com "2ª via" no topo (navegador; Bluetooth; modo APP envia
   `true`). Em A (vencedora): o ícone não aparece e `GET /api/apostas/{A}/segunda-via` → 422 com a
   mensagem. Usuário sem a permissão → 403.

## 6. Cancelar e editar (US3, FR-017 a FR-019)

1. V1, dentro do tempo de cancelamento, cancela uma aposta própria pela lista: some de Todas,
   aparece em Canceladas com data, hora e "V1"; o limite de V1 volta.
2. V1 tenta cancelar depois do tempo, ou uma aposta com jogo ao vivo: aparece a mensagem do servidor
   e nada muda.
3. G1 com `apostas.editar` cancela e restaura um palpite de D no bilhete: prêmio, cotação e comissão
   recalculados; a linha e os cartões atualizam.
4. Usuário sem `apostas.cancelar`: a ação não aparece; `POST /api/apostas/{codigo}/cancelar` → 403.
5. Duas abas cancelando a mesma aposta: a segunda recebe "Esta aposta já foi cancelada.".
6. Aba Canceladas: nenhuma ação de restaurar.

## 7. Atalho do Caixa (US4, FR-025)

1. No Caixa em "Esta semana", aba Ao vivo, menu de V1 → "Ver apostas": abre Apostas com Vendedor V1,
   a mesma semana e a aba Ao vivo; a quantidade e as entradas batem com a linha de V1.
2. Remover a etiqueta do vendedor: volta a rede toda, no mesmo período.

## 8. Premiados e planilha (US5, FR-020, FR-021)

1. G1 → Premiados: imagem com o logo e as premiadas da rede dele (até 23), maior prêmio primeiro;
   Compartilhar (celular) e Baixar (PNG). Sem premiadas: "Nenhuma aposta premiada no período.".
2. V1: sem o botão Premiados; `GET /api/apostas/premiados` → 403.
3. Baixar planilha com o filtro Premiadas: período, filtros, totais e as linhas iguais à tela
   (inclusive Jogos); dois downloads ao mesmo tempo chegam completos e diferentes.
4. Filtro com mais de 50.000 apostas (ou limite reduzido para teste): a tela avisa antes e a API
   responde 422 com a mensagem.

## 9. Visual e celular (FR-026 a FR-030, SC-006)

1. 360 px: linhas em cartões, cartões de totais em duas colunas, filtros em tela cheia, sem rolagem
   horizontal, alvos de toque ≥ 40 px.
2. Temas claro e escuro: selos de situação legíveis; `ModalHeader` nos modais.
3. Esqueleto ao carregar; erro de rede na lista com "Tentar novamente" sem travar os cartões.

## 10. Postman

`docs/postman/wssports_api.postman_collection.json` com as 7 rotas novas (lista, resumo, vendedores,
bilhete, 2ª via, premiados, exportação).
