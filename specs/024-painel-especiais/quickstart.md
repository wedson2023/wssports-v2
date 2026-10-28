# Quickstart: Painel — Especiais

Validação manual (o projeto não tem testes automatizados). Regras em [spec.md](spec.md), rotas em
[contracts/api.md](contracts/api.md), telas em [contracts/paginas.md](contracts/paginas.md).

## Preparação

1. `php artisan migrate` (colunas novas, tabela `especiais_correcoes`, permissão
   `especiais.corrigir_resultado` para o Admin, migração da vencedora antiga).
2. `npm run dev` e `php artisan serve`.
3. Usuários: um Admin, um Supervisor com rede (um vendedor e um cliente) e um Gerente.
4. Dois clientes com saldo, um da rede do Supervisor e outro fora dela.

## 1. Lista, cartões e alcance (US1)

- Como Admin, abrir `/painel/especiais`: item no menu, cartões, aba Especiais filtrada por
  Aguardando.
- Cadastrar três especiais (ver §2): um com data limite futura, um com data limite passada e um que
  será encerrado. Conferir o selo "Aguardando resultado" no segundo.
- Filtrar por Encerrado, Cancelado e Todas; buscar parte do nome; remover as etiquetas.
- Entrar como Supervisor: os números de apostas contam só a rede dele e aparece "Números da sua
  rede". Entrar como Gerente: o item não aparece e `/painel/especiais` volta para `/painel`.
- No celular (≤ 900 px): lista em cartões, ações no menu.

## 2. Cadastro e edição (US2)

- "Novo especial": "Campeão Brasileiro 2026", data limite daqui a 7 dias, quantidade 1, opções Time A
  3,50 · Time B 4,00 · Time C 6,00. Conferir no site (Especiais) as três opções.
- Erros: nome repetido, uma opção só, opção repetida, cotação 1,00, quantidade de vencedoras 3 com 3
  opções — cada erro no seu campo.
- Data limite no passado: aviso antes de salvar.
- Fazer uma aposta de cliente em Time A (3,50). Editar Time A para 3,80: o site mostra 3,80 e o
  comprovante da aposta continua 3,50.
- Tentar remover Time A: "Desative a opção em vez de remover.". Desativar Time C: some do site.
- "Rebaixados 2026": quantidade 4, seis opções (R1 a R6), cotação 2,00 cada.

## 3. Exposição (US4)

- Em "Campeão Brasileiro 2026": uma aposta simples em Time A (cliente da rede), outra simples em Time
  B (cliente fora da rede) e uma múltipla Time A + um jogo. Abrir a gaveta como Admin: simples,
  múltiplas e total por opção; Time A destacado se tiver o maior retorno. Como Supervisor: só a
  rede.

## 4. Anular opção (US5)

- Em "Campeão Brasileiro 2026", anular Time A: a confirmação mostra 2 apostas. Depois: a simples
  volta como Vencedor com o valor apostado (cotação 1,00) e é paga depois da janela; a múltipla
  segue com o jogo, prêmio recalculado e o registro "Opção anulada" no histórico. Time A some do
  site e fica "Anulada" na edição, sem poder ser alterada.

## 5. Encerrar (US3)

- "Rebaixados 2026": apostas simples em R1, R2, R3, R4 e R5.
- Encerrar marcando 3: "Escolha 4 opções vencedoras.". Marcar as 6: "Para todas vencerem, cancele
  a categoria.".
- Encerrar com R1, R2, R3, R4 e R5, "Dividir por empate" = Sim e R4, R5 empatadas: R1–R3 pagas com
  2,00; R4 e R5 com 1,00 (2,00 × 1 ÷ 2); comprovante "Dividida por empate: 1,00 (original 2,00)".
- Tentar encerrar de novo em outra aba: "A categoria já foi encerrada ou cancelada.".
- Antes da data limite: o modal avisa e deixa encerrar.

## 6. Corrigir resultado (US7)

- Em "Campeão Brasileiro 2026" (Time A anulado), encerrar com Time B; conferir o pagamento.
- Como Supervisor: não há "Corrigir resultado".
- Como Admin: corrigir para Time C com senha errada → "Senha incorreta."; com as mesmas vencedoras →
  "O resultado é o mesmo.".
- Corrigir para Time C com a senha: a aposta em Time B tem o prêmio estornado (o saldo pode ficar
  negativo), a aposta em Time C (se houver) é paga depois da janela, e a gaveta mostra a correção.
  O extrato do cliente tem as transações novas, sem apagar as antigas.

## 7. Cancelar, remover e restaurar (US6)

- Cancelar um especial com uma aposta simples: valor devolvido e situação Cancelado.
- Remover um especial sem apostas: vai para a Lixeira. Restaurar: volta com as opções.
- Cadastrar outro com o mesmo nome do que está na lixeira e tentar restaurar: "Já existe uma
  categoria especial com este nome.".
- Remover um com apostas: "A categoria tem palpites em apostas ativas: cancele a categoria em vez
  de remover.".

## 8. Postman e permissões

- A coleção `docs/postman/wssports_api.postman_collection.json` tem as rotas novas e o corpo novo de
  encerrar.
- Na tela de permissões, o grupo Especiais mostra "Corrigir resultado de especiais", marcado só no
  Admin.
