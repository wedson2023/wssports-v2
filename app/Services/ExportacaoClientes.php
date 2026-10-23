<?php

namespace App\Services;

use App\Http\Resources\ClientesResource;
use App\Models\Clientes;
use App\Models\Saques;
use App\Models\Usuarios;
use App\Support\FusoSistema;
use App\Support\PlanilhaPainel;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Planilha da tela Clientes (spec 019, FR-030, research R-09): a aba atual (Cadastros, Saques,
 * Ranking ou Lixeira) com os mesmos filtros e valores da tela, contato e CPF mascarados para quem não
 * vê os dados completos (corrige os .txt sem máscara do antigo), até 50.000 linhas por arquivo.
 */
class ExportacaoClientes
{
    /**
     * Máximo de linhas por arquivo (o servidor recusa acima disso).
     */
    public const MAXIMO_LINHAS = 50000;

    /**
     * Linhas lidas por vez do ranking.
     */
    private const POR_PAGINA_RANKING = 100;

    private const CRITERIOS = [
        'depositos' => 'Depósitos', 'valor_apostado' => 'Valor apostado',
        'resultado' => 'Resultado para a banca', 'saques' => 'Saques pagos',
    ];

    public function __construct(
        private readonly FiltrosClientes $filtros,
        private readonly ListagemSaques $saques,
        private readonly RankingClientes $ranking,
        private readonly PlanilhaPainel $planilha,
    ) {}

    /**
     * Cadastros ou Lixeira: os clientes do filtro, com o saldo líquido.
     *
     * @param  array<string, mixed>  $filtros  validados pelo FiltrosClientes
     */
    public function clientes(Usuarios $usuario, Request $request, array $filtros, bool $lixeira): string
    {
        $mascarar = ClientesResource::deve_mascarar($request);
        $base = $lixeira ? Clientes::onlyTrashed() : Clientes::query();
        $consulta = $this->filtros->consulta($usuario, $base, $filtros, $request);
        $this->garantir_limite((clone $consulta)->toBase()->getCountForPagination());

        $colunas = [
            'id' => 'ID', 'nome' => 'Nome', 'telefone' => 'Telefone', 'email' => 'E-mail', 'cpf' => 'CPF',
            'saldo' => 'Saldo', 'saldo_promocao_esportes' => 'Promoção esportes', 'saldo_promocao_cassino' => 'Promoção cassino',
            'saldo_liquido' => 'Saldo líquido', 'ativo' => 'Ativo', 'criado_em' => 'Criado em',
            ...($lixeira ? ['excluido_em' => 'Excluído em'] : []),
        ];

        $linhas = (function () use ($consulta, $mascarar): Generator {
            foreach ($consulta->without(['configuracoes', 'afiliado'])->cursor() as $cliente) {
                yield [
                    'id' => (string) $cliente->id,
                    'nome' => $cliente->nome,
                    ...$this->contato($cliente, $mascarar),
                    'saldo' => (float) $cliente->saldo,
                    'saldo_promocao_esportes' => (float) $cliente->saldo_promocao_esportes,
                    'saldo_promocao_cassino' => (float) $cliente->saldo_promocao_cassino,
                    'saldo_liquido' => (float) $cliente->saldo_liquido,
                    'ativo' => $cliente->ativo ? 'Sim' : 'Não',
                    'criado_em' => $this->data($cliente->created_at),
                    'excluido_em' => $this->data($cliente->deleted_at),
                ];
            }
        })();

        $titulo = $lixeira ? 'Clientes excluídos' : 'Clientes';

        return $this->planilha->gerar(
            $titulo,
            $titulo,
            $lixeira ? 'clientes-lixeira' : 'clientes',
            $this->cabecalho($titulo, $filtros),
            $colunas,
            $linhas,
            null,
            ['texto' => ['id', 'nome', 'telefone', 'email', 'cpf', 'ativo', 'criado_em', 'excluido_em'], 'sinal' => ['saldo_liquido']],
        );
    }

    /**
     * Saques do filtro, na ordem da fila.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function saques(Usuarios $usuario, Request $request, array $filtros): string
    {
        $mascarar = ClientesResource::deve_mascarar($request);
        $consulta = $this->saques->consulta($usuario, $filtros);
        $this->garantir_limite((clone $consulta)->toBase()->getCountForPagination());

        $colunas = [
            'cliente_id' => 'ID cliente', 'cliente' => 'Cliente', 'telefone' => 'Telefone', 'tipo_chave' => 'Tipo da chave',
            'chave' => 'Chave Pix', 'titular' => 'Titular', 'valor' => 'Valor', 'saldo' => 'Saldo do cliente',
            'situacao' => 'Situação', 'pedido_em' => 'Pedido em', 'decidido_por' => 'Decidido por',
            'decidido_em' => 'Decidido em', 'motivo' => 'Motivo',
        ];

        $linhas = (function () use ($consulta, $mascarar): Generator {
            // em blocos, com cliente e quem decidiu já carregados (o cursor não carrega relações)
            foreach ($consulta->lazy(500) as $saque) {
                /** @var Saques $saque */
                yield [
                    'cliente_id' => (string) $saque->clientes_id,
                    'cliente' => $saque->cliente?->nome,
                    'telefone' => $saque->cliente ? $this->contato($saque->cliente, $mascarar)['telefone'] : null,
                    'tipo_chave' => $saque->pix_tipo_chave?->value,
                    'chave' => $saque->pix_chave,
                    'titular' => $saque->pix_nome_titular,
                    'valor' => (float) $saque->valor,
                    'saldo' => (float) ($saque->cliente?->saldo ?? 0),
                    'situacao' => $saque->situacao?->value,
                    'pedido_em' => $this->data($saque->created_at),
                    'decidido_por' => $saque->decidido_por_usuario?->nome,
                    'decidido_em' => $this->data($saque->decidido_em),
                    'motivo' => $saque->motivo_recusa ?? $saque->motivo_falha,
                ];
            }
        })();

        return $this->planilha->gerar(
            'Saques',
            'Saques',
            'saques',
            $this->cabecalho('Saques', $filtros),
            $colunas,
            $linhas,
            null,
            ['texto' => ['cliente_id', 'cliente', 'telefone', 'tipo_chave', 'chave', 'titular', 'situacao', 'pedido_em', 'decidido_por', 'decidido_em', 'motivo']],
        );
    }

    /**
     * Ranking do período e critério, na ordem da tela.
     */
    public function ranking(Usuarios $usuario, string $criterio, Carbon $inicio, Carbon $fim): string
    {
        $primeira = $this->ranking->pagina($usuario, $criterio, $inicio, $fim, self::POR_PAGINA_RANKING, 1);
        $this->garantir_limite($primeira->total());

        $colunas = [
            'posicao' => 'Posição', 'id' => 'ID', 'nome' => 'Nome', 'saldo' => 'Saldo', 'saldo_liquido' => 'Saldo líquido',
            'ativo' => 'Ativo',
            ...match ($criterio) {
                'valor_apostado' => ['esportes' => 'Esportes', 'cassino' => 'Cassino', 'total' => 'Total apostado'],
                'resultado' => ['resultado' => 'Resultado para a banca'],
                default => ['quantidade' => 'Quantidade', 'valor' => 'Valor'],
            },
        ];

        $linhas = (function () use ($usuario, $criterio, $inicio, $fim, $primeira): Generator {
            for ($pagina = 1, $resultado = $primeira; ; $pagina++) {
                if ($pagina > 1) {
                    $resultado = $this->ranking->pagina($usuario, $criterio, $inicio, $fim, self::POR_PAGINA_RANKING, $pagina);
                }

                foreach ($resultado->items() as $item) {
                    $bloco = $item[$criterio];

                    yield [
                        'posicao' => $item['posicao'],
                        'id' => (string) $item['id'],
                        'nome' => $item['nome'],
                        'saldo' => (float) $item['saldo'],
                        'saldo_liquido' => (float) $item['saldo_liquido'],
                        'ativo' => $item['ativo'] ? 'Sim' : 'Não',
                        ...(is_array($bloco) ? array_map(fn ($valor) => is_string($valor) ? (float) $valor : $valor, $bloco) : ['resultado' => (float) $bloco]),
                    ];
                }

                if ($pagina >= $resultado->lastPage()) {
                    return;
                }
            }
        })();

        $cabecalho = [
            ['Gerado em', $this->data(now())],
            ['Critério', self::CRITERIOS[$criterio]],
            ['Período', $this->data($inicio).' a '.$this->data($fim)],
        ];

        return $this->planilha->gerar(
            'Ranking de clientes',
            'Ranking',
            'clientes-ranking',
            $cabecalho,
            $colunas,
            $linhas,
            null,
            ['texto' => ['id', 'nome', 'ativo'], 'inteiro' => ['posicao', 'quantidade'], 'sinal' => ['saldo_liquido', 'resultado']],
        );
    }

    /**
     * Acima do limite, a planilha não é gerada.
     */
    private function garantir_limite(int $quantidade): void
    {
        abort_if(
            $quantidade > self::MAXIMO_LINHAS,
            422,
            'O filtro tem '.number_format($quantidade, 0, ',', '.').' registros; o máximo por planilha é '
                .number_format(self::MAXIMO_LINHAS, 0, ',', '.').'. Use filtros para reduzir.',
        );
    }

    /**
     * Data de geração, aba e filtros aplicados.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array{0: string, 1: string}>
     */
    private function cabecalho(string $aba, array $filtros): array
    {
        $ignorar = ['ordenar_por', 'direcao', 'por_pagina', 'pagina', 'page'];
        $aplicados = [];

        foreach ($filtros as $chave => $valor) {
            if ($valor !== null && $valor !== '' && ! in_array($chave, $ignorar, true)) {
                $aplicados[] = $chave.': '.(is_bool($valor) ? ($valor ? 'sim' : 'não') : $valor);
            }
        }

        return [
            ['Gerado em', $this->data(now())],
            ['Aba', $aba],
            ['Filtros', $aplicados === [] ? 'Nenhum' : implode(' · ', $aplicados)],
        ];
    }

    /**
     * Telefone, e-mail e CPF sem o sufixo de exclusão, mascarados pela mesma regra do resource.
     *
     * @return array{telefone: ?string, email: ?string, cpf: ?string}
     */
    private function contato(Clientes $cliente, bool $mascarar): array
    {
        $sem_sufixo = fn (?string $valor) => $valor === null ? null : preg_replace(ClientesResource::SUFIXO_EXCLUSAO, '', $valor);
        $telefone = $sem_sufixo($cliente->telefone);
        $email = $sem_sufixo($cliente->email);
        $cpf = $sem_sufixo($cliente->cpf);

        return [
            'telefone' => $telefone === null ? null : '+'.$cliente->ddi.' '.($mascarar ? ClientesResource::mascarar_telefone($cliente->ddi, $telefone) : $telefone),
            'email' => $mascarar ? ClientesResource::mascarar_email($email) : $email,
            'cpf' => $mascarar ? ClientesResource::mascarar_cpf($cpf) : $cpf,
        ];
    }

    private function data(mixed $momento): ?string
    {
        return $momento === null ? null : Carbon::parse($momento)->setTimezone(FusoSistema::FUSO)->format('d/m/Y H:i');
    }
}
