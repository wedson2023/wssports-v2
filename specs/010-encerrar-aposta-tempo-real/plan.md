# Implementation Plan: Encerrar aposta, bilhete em tempo real e notificações push

**Branch**: `010-encerrar-aposta-tempo-real` | **Date**: 2026-10-15 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/010-encerrar-aposta-tempo-real/spec.md`

## Summary

Três entregas sobre a mesma página, `/bilhete/{codigo}`.

**Bilhete em tempo real (P1)**: página Inertia `Ticket`, alimentada por
`GET /api/publico/bilhetes/{codigo}`, com o visual aprovado no protótipo. A atualização contínua
usa o **Laravel Reverb** com canais públicos por jogo (`confrontos.{id}`) e por aposta
(`apostas.{codigo}`). O evento traz o placar na hora, e a página busca o bilhete de novo para os
selos e as cotações. Com a conexão caída, a página consulta a cada 10 s ou 60 s (R-01, R-02). Os
selos "ganhando/perdendo agora" saem das mesmas regras da apuração (`ResultadosCotacao`), aplicadas
ao placar do ao vivo (R-06).

**Encerramento (P2)**: o cálculo e as ações ficam no servidor.

- **Oferta**: prêmio × fração restante ÷ cotações atuais do cliente (`CriacaoApostas::montar`),
  menos a margem de 7% ou 10%, truncada. Vem assinada num token com validade de 8 s (R-04, R-09).
- **Confirmação**: com jogo ao vivo, espera o tempo de confirmação do cliente por um job, como nas
  apostas do ao vivo, e recusa se houve lance.
- **Parcial**: reduz `apostas.fracao_restante`, sem mexer em `valor`, `premio` nem na assinatura
  (R-07).
- **Total**: grava o resultado `Encerrada`, que a apuração preserva (R-08).
- **Crédito**: `apostas:liquidar_encerramentos` credita depois da janela da spec 009, com a origem
  nova `Encerramento de aposta`.
- **Rollover**: o progresso é retirado e devolvido se o encerramento for desfeito (FR-033, R-11).
- **Automático**: conferido depois de cada carga do ao vivo e a cada minuto (R-12).

**Notificações (P3)**: Web Push próprio com VAPID (`minishlink/web-push`), sem servidor externo nem
cota.

- **Eventos**: a carga do ao vivo compara o estado anterior e detecta gol, gol anulado e intervalo;
  o fim de jogo vem da carga de resultados (R-16).
- **Envio**: os avisos são deduplicados por cliente em `clientes_notificacoes` e enviados por job
  aos aparelhos com o tipo ligado (R-13, R-17).
- **Service worker**: o gerado pelo Vite importa `/sw-notificacoes.js` (R-14).

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (React 19) com Vite 7

**Primary Dependencies**: Laravel 12, Inertia 2, styled-components 6, `bcmath`. **Novas**:
`laravel/reverb` ^1.0, `minishlink/web-push` ^9.0, `laravel-echo` ^2.0, `pusher-js` ^8.4
(justificativas no [research.md](research.md#dependências-novas-constituição-stack-e-restrições-técnicas))

**Storage**: MySQL 8.4 local `wssports` (só `migrate`)

**Testing**: nenhum teste automatizado (constituição). Validação manual pelo
[quickstart.md](quickstart.md), com o mock do provedor

**Target Platform**: servidor PHP 8.2 com agendador, worker de fila (`apostas`, `notificacoes`) e
processo `reverb:start`; navegadores atuais e PWA instalada (iOS 16.4+ para push)

**Project Type**: aplicação web Laravel + React (Inertia), backend e frontend

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Mudança do jogo na tela (SC-001) | ≤ 5 s em 95% (carga a cada 5 s + broadcast imediato) |
| Consulta periódica sem conexão (SC-002) | ≤ 10 s com jogo ao vivo |
| `GET /publico/bilhetes/{codigo}` com 20 palpites | < 300 ms p95 (consultas em lote, sem N+1) |
| Encerramento automático (SC-006) | ≤ 10 s depois da carga em que a oferta alcança o alvo |
| Crédito (SC-007) | janela + 1 min |
| Aviso de jogo (SC-008, FR-048) | ≤ 15 s depois da carga que detectou o evento |
| Pós-processamento da carga do ao vivo | < 1 s para 300 jogos, sem atrasar a carga seguinte |

**Constraints**:

- dinheiro e cotações sem `float` (bcmath, centavos do `SaldoClientes`);
- uma transação e trava (`lockForUpdate`) por aposta em todo encerramento, decisão, desfazimento e
  apuração;
- falha do Reverb ou do envio de push nunca desfaz a carga do ao vivo nem a apuração;
- dados por cliente (cotações ajustadas, oferta) nunca vão em canal público;
- o sistema antigo é só leitura;
- textos, logs e mensagens em português.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas | 5 novas (`apostas_encerramentos`, `apostas_encerramentos_automaticos`, `clientes_inscricoes_notificacoes`, `clientes_notificacoes`, `confrontos_eventos`), 3 alteradas (`apostas`, `configuracoes`, `clientes_configuracoes`) |
| Enums | 5 novos, 3 alterados |
| Serviços | 11 novos, 6 alterados |
| Rotas | 12 novas na API, 2 novas na web; 1 existente ganha um campo |
| Permissões | 1 nova (`apostas_encerramentos.listar`, Admin e Supervisor) |
| Comandos / jobs | 3 comandos novos e 1 alterado; 3 jobs novos |
| Frontend | 1 página nova, 11 componentes novos, 3 hooks novos, 2 utilitários novos; 7 arquivos existentes alterados |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos (`calcular_oferta`, `liquidar`), chaves (`token_oferta`, `fracao_restante`), comandos (`apostas:liquidar_encerramentos`); hooks `useBilheteTempoReal`, `useEncerramento`, `useNotificacoesPush` | ✅ Pass |
| I. Banco em português | `apostas_encerramentos`, `fracao_restante`, `receber_gols`; siglas consagradas (`endpoint`, `ip`, `url`, `user_agent`) | ✅ Pass |
| I. Prefixo de tabelas | `apostas_encerramentos*` (prefixo `apostas`), `clientes_inscricoes_notificacoes` e `clientes_notificacoes` (prefixo `clientes`), `confrontos_eventos` (prefixo `confrontos`) | ✅ Pass |
| I. Enums | Casos em `PascalCase` português com acento (`EmAnálise`, `Automático`, `EncerramentoAposta`); valores com espaço (`'Em análise'`, `'Encerramento de aposta'`) | ✅ Pass |
| I. Permissões | `apostas_encerramentos.listar` (`<tabela>.<acao>`) | ✅ Pass |
| I. Rotas em kebab-case | `/bilhete/{codigo}`, `/sw-notificacoes.js`, `/apostas-encerramentos`, `/encerramento-automatico` | ✅ Pass |
| I. Frontend | Componentes em inglês `PascalCase` (`CashOutPanel`, `LiveHunchCard`); props e variáveis em `snake_case` português; transient props `$ganhando`; utilitários `utils/realtime.js` e `utils/web_push.js` | ✅ Pass |
| II. Idioma | Artefatos, comentários, logs, mensagens e textos das notificações em português | ✅ Pass |
| III. Componentes React | Cada componente novo em pasta própria com `index.jsx` e `styles.jsx`; a página `Ticket` também | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, cada um com o motivo, confirmados pelo responsável em 2026-10-15 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Configuração no padrão de `configuracoes/apuracao`; permissão no `Funcao` + seeder no padrão do `ApuracaoSeeder`; saldo só por `SaldoClientes`; delay do ao vivo com job e recusa de análise presa (spec 004); cotações por `CriacaoApostas::montar`; regras de resultado por `ResultadosCotacao`. Padrões novos (broadcasting e push) não têm equivalente no projeto. O grupo de permissão segue o padrão de Admin e Supervisor e não o de clientes (R-19, confirmado em 2026-10-15) | ✅ Pass (confirmado) |
| VII. Sistema antigo | Consultado e listado no [research.md](research.md#sistema-antigo-princípio-vii); o encerramento não existia no antigo (registrado); diferenças aprovadas na spec (Clarifications) | ✅ Pass |
| VIII. Fidelidade visual | Visual novo do bilhete aprovado no protótipo (Clarifications); ícone novo no cupom e link no compartilhamento aprovados (FR-015); Minha Conta só ganha o sino (FR-040) | ✅ Pass |
| IX. Dados fake | Nenhum novo; cores do tema e ícone do push usam os fakes já existentes, fora de qualquer cálculo de dinheiro | ✅ Pass |
| Timestamps e soft delete | 5 tabelas novas com `timestamps()` + `softDeletes()`; inscrição removida por soft delete; nenhuma exclusão física | ✅ Pass |
| Comentários de colunas | Todas as colunas novas com `->comment()` ([data-model.md](data-model.md)); `apostas.resultado` ganha comentário com os valores | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma; a ordem das migrations respeita as chaves | ✅ Pass |
| Paginação ≤ 100 | `GET /apostas-encerramentos`: padrão 20, máximo 100 | ✅ Pass |
| Atualização e cache | `sw-notificacoes.js` sem cache, servido como o `sw.js`; configuração do Reverb vem do servidor a cada carga (props da página), não do build | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada com as 12 rotas e o campo novo | ✅ Pass |
| Novas dependências | 4 justificadas no research, confirmadas pelo responsável em 2026-10-15 (com o processo `reverb:start` em produção) | ✅ Pass (confirmado) |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-10-15)**: lista de arquivos existentes; as 4 dependências e o
processo do Reverb; `fracao_restante` (R-07); permissão só de Admin e Supervisor (R-19); critério
do desfazimento (R-11); jogo com resultado gravado sem oferta até a apuração (R-05, item 8).
Depois do `/speckit-analyze`: cancelamento pelo painel devolve só o valor em jogo (R-21) e a carga
de cotações do pré-jogo avisa o bilhete (R-22), com `CancelamentoApostas.php` e
`ImportacaoCotacoes.php` acrescentados à lista.

### Arquivos existentes que serão alterados (Princípio IV)

**Backend**

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Models/Apostas.php` | Cast de `fracao_restante`; `total_a_pagar()` multiplicado pela fração; `valor_em_jogo()`; relações `encerramentos()` e `encerramento_automatico()` | R-07, FR-028, FR-034 |
| `app/Models/Configuracoes.php` | `fillable` e casts das 10 colunas | R-18 |
| `app/Models/ClientesConfiguracoes.php` | `encerrar_aposta` em `CAMPOS` e `CASTS` | FR-036 |
| `app/Enums/ResultadoAposta.php` | Caso `Encerrada` | FR-027 |
| `app/Enums/OrigemTransacao.php` | Caso `EncerramentoAposta` | FR-032 |
| `app/Enums/TipoOperacaoApuracao.php` | Caso `EncerramentoDesfeito` | R-11 |
| `app/Enums/Funcao.php` | Grupo `PERMISSOES_ENCERRAMENTOS` em `permissoes_padrao` e na regra de Admin e Supervisor do `pode_usar` | FR-037, R-19 |
| `app/Support/PlacarConfronto.php` | Construtor `do_ao_vivo(object $jogo)` | R-06 |
| `app/Services/RolloverClientes.php` | Métodos `retirar(Apostas, proporcao)` e `devolver(lista)` | FR-038, R-10 |
| `app/Services/ApuracaoApostas.php` | `decidir` preserva `Encerrada`; confere e desfaz encerramento (FR-033); cancela o automático da aposta decidida; depois da transação, `ApostaAtualizada` e aviso de resultado | R-08, R-11, R-12, R-17 |
| `app/Services/ImportacaoAoVivo.php` | Lê o estado anterior dos jogos recebidos e chama `ProcessamentoAoVivo` depois da transação | R-16 |
| `app/Services/ImportacaoResultados.php` | Quando o jogo passa a apurável, registra `Fim de jogo` e emite `ConfrontoAtualizado` | R-16 |
| `app/Services/CancelamentoApostas.php` | `devolver` credita ao cliente `valor_em_jogo()` no lugar de `valor` | R-21 (analyze) |
| `app/Services/ImportacaoCotacoes.php` | Depois de gravar, emite `ConfrontoAtualizado` e despacha a conferência dos automáticos para os jogos da carga com aposta em aberto | R-22 (analyze), SC-001 |
| `app/Console/Commands/RecusarAnalisesPresasCommand.php` | Também recusa encerramentos `Em análise` presos | R-09 |
| `app/Http/Controllers/AreaClienteMinhaContaController.php` | Origem `Encerramento de aposta` no filtro de palpites e na ligação da transação com a aposta | FR-015, FR-032 |
| `app/Http/Controllers/ArquivosPwaController.php` | Método `notificacoes` (serve `/sw-notificacoes.js`) | R-14 |
| `app/Http/Controllers/RegrasController.php` | Prop `encerramento` | FR-039, R-20 |
| `app/Http/Requests/ClientesConfiguracoesRequest.php` | `encerrar_aposta` (`sometimes`, `boolean`) | FR-036 |
| `routes/api.php` | 12 rotas e `use` dos controllers | contrato |
| `routes/web.php` | `bilhete/{codigo}` e `sw-notificacoes.js` | contrato |
| `routes/console.php` | Agenda dos 2 comandos | contracts/comandos.md |
| `database/seeders/DatabaseSeeder.php` | Chama `EncerramentoSeeder` depois do `ApuracaoSeeder` | R-19 |
| `config/services.php` | Bloco `notificacoes_push` (chaves VAPID e assunto, do `.env`) | R-13 |
| `.env.example` | Variáveis `REVERB_*` e `VAPID_*`; `BROADCAST_CONNECTION=reverb` | R-01, R-13 |
| `composer.json` / `composer.lock` | `laravel/reverb`, `minishlink/web-push`; script `dev` sobe o `reverb:start` e o `queue:listen` passa a atender `apostas,notificacoes,default` | dependências; filas dos jobs (analyze, U3) |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | constituição |

**Frontend**

| Arquivo | Alteração | Motivo |
|---|---|---|
| `package.json` / `package-lock.json` | `laravel-echo`, `pusher-js` | R-01 |
| `vite.config.js` | `workbox.importScripts: ['/sw-notificacoes.js']` | R-14 |
| `resources/js/hooks/useSessao.js` | `aplicar_token` usa o acesso assistido também em `/bilhete/…`; `sair` remove a inscrição de push do aparelho | R-03, R-15 |
| `resources/js/components/TicketModal/index.jsx` (+ `styles.jsx`) | Ícone "acompanhar em tempo real" no topo; o compartilhamento envia o link; cor do resultado `Encerrada` | FR-015 |
| `resources/js/pages/Account/index.jsx` | Tocar numa operação de aposta abre `/bilhete/{codigo}` (o cupom sai dessa tela) | FR-015 |
| `resources/js/components/AccountHeader/index.jsx` (+ `styles.jsx`) | Sino de notificações (`NotificationBell`) | FR-040 |
| `resources/js/components/StatementTable/index.jsx` | Ícone e cor do resultado `Encerrada` | FR-027 |
| `resources/js/components/RulesContent/index.jsx` e `resources/js/pages/Rules/index.jsx` | Seção `CashOutRules` com a prop `encerramento` | FR-039 |

**Nenhum outro arquivo existente muda**. Em especial: `CriacaoApostas` (só chamado),
`SaldoClientes`, `LiquidacaoApostas` (já paga `total_a_pagar()` e `devido` já é 0 fora de
`Vencedor`), `EdicaoApostas`, `CalculoPremio`, `CalculoCotacoes`, `ResultadosCotacao`,
`AssinaturaApostas`, `ComprovanteApostaResource` (o cupom continua sendo o comprovante da aposta
como feita) e a página `Home`.

**Impacto no banco local**: `php artisan migrate` (5 tabelas novas e colunas com padrão nas 3
existentes) e `php artisan db:seed --class=EncerramentoSeeder`. Nenhuma tabela do legado é tocada.

### Re-check pós-design (Phase 1)

[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/tempo_real.md](contracts/tempo_real.md) e [contracts/comandos.md](contracts/comandos.md)
foram conferidos:

- nomes em português e `snake_case`;
- tabelas novas com timestamps, soft delete e comentário em todas as colunas;
- enums com valores em português;
- rotas em kebab-case e paginação ≤ 100;
- comandos no padrão `recurso:acao`;
- canais sem dado por cliente;
- nenhuma tarefa de teste.

**Status: aprovado.**

## Project Structure

### Documentation (this feature)

```text
specs/010-encerrar-aposta-tempo-real/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   ├── api.md           # rotas REST e páginas
│   ├── tempo_real.md    # canais do Reverb e mensagens de push
│   └── comandos.md      # comandos, jobs, agenda e processos
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   ├── LiquidarEncerramentosCommand.php                 # NOVO  apostas:liquidar_encerramentos
│   ├── ConferirEncerramentosAutomaticosCommand.php      # NOVO  apostas:conferir_encerramentos_automaticos
│   ├── GerarChavesPushCommand.php                       # NOVO  notificacoes_push:gerar_chaves
│   └── RecusarAnalisesPresasCommand.php                 # ALTERADO
├── Enums/
│   ├── ModalidadeEncerramento.php                       # NOVO
│   ├── SituacaoEncerramento.php                         # NOVO
│   ├── SituacaoEncerramentoAutomatico.php               # NOVO
│   ├── TipoEventoConfronto.php                          # NOVO
│   ├── TipoNotificacao.php                              # NOVO
│   └── Funcao.php, OrigemTransacao.php, ResultadoAposta.php, TipoOperacaoApuracao.php   # ALTERADOS
├── Events/
│   ├── ConfrontoAtualizado.php                          # NOVO  broadcast confrontos.{id}
│   └── ApostaAtualizada.php                             # NOVO  broadcast apostas.{codigo}
├── Http/
│   ├── Controllers/
│   │   ├── BilheteController.php                        # NOVO  página Inertia /bilhete/{codigo}
│   │   ├── PublicoBilhetesController.php                # NOVO
│   │   ├── PublicoNotificacoesController.php            # NOVO
│   │   ├── AreaClienteEncerramentosController.php       # NOVO
│   │   ├── AreaClienteEncerramentoAutomaticoController.php  # NOVO
│   │   ├── AreaClienteNotificacoesController.php        # NOVO
│   │   ├── ConfiguracoesEncerramentoController.php      # NOVO
│   │   ├── ApostasEncerramentosController.php           # NOVO  listagem do painel
│   │   └── AreaClienteMinhaContaController.php, ArquivosPwaController.php, RegrasController.php  # ALTERADOS
│   ├── Requests/
│   │   ├── EncerrarApostaRequest.php                    # NOVO
│   │   ├── EncerramentoAutomaticoRequest.php            # NOVO
│   │   ├── InscricaoNotificacaoRequest.php              # NOVO
│   │   ├── ConfiguracoesEncerramentoRequest.php         # NOVO
│   │   ├── ListagemEncerramentosRequest.php             # NOVO
│   │   └── ClientesConfiguracoesRequest.php             # ALTERADO
│   └── Resources/
│       ├── BilheteTempoRealResource.php                 # NOVO
│       ├── EncerramentoResource.php                     # NOVO
│       ├── ApostasEncerramentosResource.php             # NOVO
│       ├── ConfiguracoesEncerramentoResource.php        # NOVO
│       └── InscricaoNotificacaoResource.php             # NOVO
├── Jobs/
│   ├── DecidirEncerramento.php                          # NOVO
│   ├── ConferirEncerramentosAutomaticos.php             # NOVO
│   └── EnviarNotificacaoPush.php                        # NOVO
├── Models/
│   ├── ApostasEncerramentos.php                         # NOVO
│   ├── ApostasEncerramentosAutomaticos.php              # NOVO
│   ├── ClientesInscricoesNotificacoes.php               # NOVO
│   ├── ClientesNotificacoes.php                         # NOVO
│   ├── ConfrontosEventos.php                            # NOVO
│   └── Apostas.php, Configuracoes.php, ClientesConfiguracoes.php   # ALTERADOS
├── Services/
│   ├── BilheteTempoReal.php                             # NOVO  monta o bilhete (selos, cotações atuais, progresso)
│   ├── ElegibilidadeEncerramento.php                    # NOVO  R-05
│   ├── CalculoEncerramento.php                          # NOVO  oferta, mínimo e cotações atuais (R-04)
│   ├── EncerramentoApostas.php                          # NOVO  pedido, decisão, efeitos e desfazimento (R-09 a R-11)
│   ├── EncerramentosAutomaticos.php                     # NOVO  R-12
│   ├── LiquidacaoEncerramentos.php                      # NOVO  crédito após a janela
│   ├── ProcessamentoAoVivo.php                          # NOVO  broadcast, eventos e automáticos após a carga (R-16)
│   ├── EventosConfrontos.php                            # NOVO  registra eventos e os avisos de cada cliente (R-17)
│   ├── NotificacoesPush.php                             # NOVO  envio Web Push (R-13)
│   ├── InscricoesNotificacoes.php                       # NOVO  grava e remove inscrições (R-15)
│   ├── ConfiguracoesEncerramento.php                    # NOVO  leitura das configurações + regras do cliente
│   └── ApuracaoApostas.php, ImportacaoAoVivo.php, ImportacaoResultados.php, ImportacaoCotacoes.php,
│       RolloverClientes.php, CancelamentoApostas.php                                  # ALTERADOS
├── Exceptions/
│   └── OfertaAlteradaException.php                      # NOVO (implementação)  409 da oferta alterada (R-09)
└── Support/
    ├── TokenOferta.php                                  # NOVO  assina e confere o token da oferta (R-09)
    └── PlacarConfronto.php                              # ALTERADO

config/
├── broadcasting.php                                     # NOVO (publicado do framework, conexão reverb)
├── reverb.php                                           # NOVO (publicado do pacote)
└── services.php                                         # ALTERADO

database/migrations/                                     # 8 NOVAS (data-model.md, seção 10)
database/seeders/EncerramentoSeeder.php                  # NOVO
database/seeders/DatabaseSeeder.php                      # ALTERADO

resources/
├── pwa/push_notifications.js                            # NOVO  handlers push e notificationclick (R-14)
└── js/
    ├── pages/Ticket/index.jsx, styles.jsx               # NOVO  página do bilhete
    ├── components/
    │   ├── TicketSummary/              # NOVO  resumo, prêmio potencial, situação, cliente
    │   ├── TicketProgress/             # NOVO  barra por palpite + legenda em texto
    │   ├── LiveHunchCard/              # NOVO  cartão do palpite (placar em destaque, selo)
    │   ├── LiveBlockCard/              # NOVO  bloco Criar Aposta agrupado
    │   ├── ConnectionStatus/           # NOVO  "Ao vivo" / "Reconectando…" / atualizado há X s
    │   ├── CashOutPanel/               # NOVO  oferta, abas Total/Parcial/Automático, motivo
    │   ├── CashOutConfirm/             # NOVO  confirmação com contagem regressiva
    │   ├── CashOutBar/                 # NOVO  barra fixa do celular
    │   ├── NotificationBell/           # NOVO  sino + estados de permissão + orientação do iPhone
    │   ├── NotificationSettingsModal/  # NOVO  liga/desliga por tipo
    │   ├── CashOutRules/               # NOVO  seção das Regras
    │   └── TicketModal/, AccountHeader/, StatementTable/, RulesContent/   # ALTERADOS
    ├── hooks/
    │   ├── useBilheteTempoReal.js      # NOVO  dados + canais + plano B (R-02)
    │   ├── useEncerramento.js          # NOVO  pedido, Em análise, automático
    │   ├── useNotificacoesPush.js      # NOVO  permissão, inscrição, preferências
    │   └── useSessao.js                # ALTERADO
    ├── utils/
    │   ├── realtime.js                 # NOVO  cria o Echo com as props tempo_real
    │   ├── web_push.js                 # NOVO  conversão da chave VAPID e detecção de suporte/iPhone
    │   ├── live_states.js              # NOVO (implementação)  cores e ícones dos estados do bilhete
    │   └── shields.js                  # NOVO (implementação)  endereço do escudo (mesma regra do MatchCard)
    └── pages/Account/index.jsx, pages/Rules/index.jsx   # ALTERADOS

routes/api.php, routes/web.php, routes/console.php       # ALTERADOS
vite.config.js, package.json, composer.json, .env.example # ALTERADOS
docs/postman/wssports_api.postman_collection.json         # ALTERADO (regenerado)
```

**Structure Decision**: aplicação Laravel + React (Inertia) única, seguindo:

- spec 004: delay do ao vivo com job e recusa de análise presa; idempotência por chave;
- spec 007: saldo só por `SaldoClientes`; área do cliente com `GarantirAcessoCliente`; acesso
  assistido com autor;
- spec 008: configuração geral por rota própria com `configuracoes.editar`;
- spec 009: janela de pagamento, registro em `operacoes_apuracao`, regras de resultado em
  `app/Support`.

Página e componentes no padrão do Princípio III. Sem Policies, sem fakes e sem arquivos em
`tests/`.

## Complexity Tracking

| Violação / custo | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Processo novo em produção (`reverb:start`) | A spec decidiu conexão contínua (WebSocket) | Pusher é pago e de terceiros; SSE prende processos PHP; consulta periódica foi rejeitada na spec |
| `Apostas::total_a_pagar()` passa a considerar `fracao_restante` (spec 004) | O parcial precisa reduzir o que a aposta paga sem mexer em `valor`, `premio` e na assinatura (R-07) | Colunas de valores restantes ficariam defasadas a cada recálculo de prêmio da spec 009 |
| `ApuracaoApostas::decidir` deixa de recalcular o resultado da aposta `Encerrada` (spec 009) | A aposta encerrada não pode voltar a `Vencedor` e receber prêmio (FR-034) | Um ramo na liquidação deixaria o resultado inconsistente com o que foi pago |
| `ImportacaoAoVivo` ganha uma leitura antes do `upsert` (spec 003) | Os eventos de jogo saem da comparação com o estado anterior (premissa da spec) | Ler o provedor de novo ou guardar cópias dobraria a carga |
