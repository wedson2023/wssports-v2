# Quickstart: Painel — Cotações (validação manual)

**Spec**: [spec.md](spec.md) · **Contratos**: [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md)

## 0. Preparação

```bash
php artisan migrate          # cria porcentagens_cadeados e porcentagens_historicos
npm run build                # ou npm run dev
```

- Usuários: Admin, um Supervisor S, um Gerente G (de S) e um Vendedor V (de G).
- Ter jogos de pré-jogo de hoje com `odd1` disponível (carga normal do provedor).
- Validar nos temas escuro e claro e em 360 px de largura (celular).

## 1. Acesso (FR-001, FR-002)

1. Admin: menu mostra "Cotações" com as cinco abas.
2. Gerente G: vê Público, Campeonatos, Jogos e Regras ativas; não vê Tetos.
3. Vendedor V: não vê o item; `/painel/cotacoes` volta para `/painel`.

## 2. Público (US1)

1. Admin → Público → "Clientes do site" → Pré-jogo. Digitar −10 em "Casa": o campo fica destacado, a
   SaveBar mostra "1 alteração", a prévia mostra "Provedor 2,00 · Público −10% → 1,80" (num jogo com
   `odd1` 2,00).
2. Trocar de aba sem salvar → aparece a confirmação. Cancelar, **Salvar** → toast de sucesso.
3. Abrir o site como visitante e conferir 1,80 no mesmo jogo (SC-002).
4. Digitar 150 → erro no campo "A porcentagem deve estar entre -100 e 100."; Salvar não grava.
5. Gerente G → Público: só aparece ele e os vendedores dele (sem "Clientes do site").
6. Admin escolhe V: a linha "Superiores somam" mostra a soma de S e G.
7. "Aplicar a todas" −2 no grupo "Vencedor" → três campos pendentes; Desfazer volta tudo.

## 3. Cadeado (FR-019 a FR-019c)

1. Supervisor S → Público → G → Pré-jogo → trancar "Casa" (toast; histórico grava "trancou Casa").
2. Gerente G → Público → ele mesmo: "Casa" só leitura com "trancada por S"; "Aplicar a todas" −1 →
   aviso "1 cotação trancada foi pulada".
3. Via API, G envia `valores.odd1` → 422 "A cotação Casa está trancada por S."
4. G → V: "Casa" também trancada (vale para quem está abaixo de G).
5. Admin tranca "Casa" em G também; S destranca → a do Admin continua; Admin destranca a dele.
6. Gerente não vê cadeado clicável; `POST /cotacoes/cadeados` como G → 403.

## 4. Campeonatos (US2)

1. Buscar um campeonato, filtrar por país (lista com bandeira), selo "sem ajuste".
2. Editar → modal com `ModalHeader`, alvo Clientes, +3 em "Casa", Salvar → selo "+3% · Clientes" na
   lista sem recarregar; prévia: "Campeonato +3%".
3. "Só com ajuste" → só os campeonatos com regra.
4. Gerente G: alvo só "Vendedores de" G ou da rede dele; não vê regras de outra rede.
5. S tranca "Casa" no campeonato para G → G não altera "Casa" nesse campeonato.

## 5. Jogos e Tetos (US3)

1. Jogos → Hoje → abrir um jogo com "Casa" 2,00 → informar 1,95 para Clientes: aparece "Provedor
   2,00 · −0,05"; Salvar; site mostra 1,95 (sem outras regras).
2. Opção sem cotação no provedor: campo desabilitado "indisponível".
3. Via API, gravar o valor de um jogo que já começou → 422 "O jogo já começou; o valor do jogo vale só antes do início."
4. Admin salva uma regra de jogo com alvo Todos → aparece a confirmação "Esta regra vai valer para visitantes, clientes e todos os vendedores."
5. Tetos (Admin): "Casa" 1,50 → prévia "limitada ao teto"; valor 0,50 → erro "mínimo 1,00".

## 6. Regras ativas e histórico (US4, FR-018a)

1. Regras ativas mostra as regras criadas acima, com filtro por camada e faixa de valores.
2. Editar abre a aba e o item certos. Histórico mostra cada gravação com autor, data e antes → depois;
   "aplicar a todas" aparece como uma alteração só (SC-008).
3. Gerente G vê só as regras da rede dele.

## 7. Celular e estados (FR-020 a FR-023, SC-005)

1. 360 px: abas roláveis, listas com colunas principais e três pontinhos, grupos de mercado abrem e
   fecham, prévia em faixa fixa embaixo, nada com rolagem horizontal.
2. Desligar a rede e abrir uma lista → erro com "Tentar novamente" sem travar o resto.
3. Tema claro: bordas dos campos visíveis; cores de aumento/redução legíveis.

## 8. Postman

Importar `docs/postman/wssports_api.postman_collection.json` e rodar a pasta "Cotações do painel".
