<?php

namespace App\Services;

use App\Enums\SituacaoAoVivo;
use App\Enums\SituacaoConfronto;
use App\Exceptions\FalhaProvedorException;
use App\Support\CodigosCotacao;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Valida as respostas do provedor sem o Validator do Laravel (lento para milhares de confrontos
 * com até 323 cotações cada) e traduz os nomes do provedor (fonte_id, casa, horario...) para os
 * do sistema (codigo_externo, time_casa, data_inicio...). Resposta que não é uma lista recusa a
 * carga inteira; item inválido é ignorado e o motivo é devolvido para o log.
 */
class ValidacaoCargaProvedor
{
    /**
     * @var list<string>
     */
    private array $motivos = [];

    /**
     * @param  array<int|string, mixed>  $resposta
     * @return array{campeonatos: list<array<string, mixed>>, motivos: list<string>}
     */
    public function validar_campeonatos(array $resposta): array
    {
        $this->iniciar($resposta, 'campeonatos');

        $campeonatos = [];

        foreach ($resposta as $campeonato) {
            if (! is_array($campeonato) || ! $this->codigo_valido($campeonato['fonte_id'] ?? null)
                || ! $this->texto_valido($campeonato['nome'] ?? null) || ! $this->texto_valido($campeonato['pais'] ?? null)) {
                $this->motivos[] = 'Campeonato ignorado: código, nome ou país inválido ('.$this->descrever($campeonato).').';

                continue;
            }

            $campeonatos[] = [
                'codigo_externo' => (int) $campeonato['fonte_id'],
                'nome' => trim($campeonato['nome']),
                'pais' => trim($campeonato['pais']),
                'bandeira' => $this->texto_valido($campeonato['bandeira'] ?? null) ? trim($campeonato['bandeira']) : null,
            ];
        }

        return ['campeonatos' => $campeonatos, 'motivos' => $this->motivos];
    }

    /**
     * @param  array<int|string, mixed>  $resposta
     * @return array{confrontos: list<array<string, mixed>>, motivos: list<string>}
     */
    public function validar_confrontos(array $resposta): array
    {
        $this->iniciar($resposta, 'confrontos');

        $confrontos = [];

        foreach ($resposta as $confronto) {
            $dados = $this->dados_comuns($confronto);

            if ($dados === null) {
                continue;
            }

            if (SituacaoConfronto::tryFrom((string) ($confronto['situacao'] ?? '')) === null) {
                $this->ignorar($confronto, 'situação inválida');

                continue;
            }

            $confrontos[] = [...$dados, 'situacao' => $confronto['situacao']];
        }

        return ['confrontos' => $confrontos, 'motivos' => $this->motivos];
    }

    /**
     * @param  array<int|string, mixed>  $resposta
     * @return array{cotacoes: list<array<string, mixed>>, motivos: list<string>}
     */
    public function validar_cotacoes(array $resposta): array
    {
        $this->iniciar($resposta, 'cotações');

        $cotacoes = [];

        foreach ($resposta as $item) {
            if (! is_array($item) || ! $this->codigo_valido($item['fonte_id'] ?? null)) {
                $this->ignorar($item, 'código inválido');

                continue;
            }

            $valores = $this->cotacoes($item);

            if ($valores === null) {
                $this->ignorar($item, 'cotações inválidas');

                continue;
            }

            $cotacoes[] = [
                'codigo_externo' => (int) $item['fonte_id'],
                'cotacoes' => $valores,
                'jogadores' => $this->jogadores((array) ($item['jogador'] ?? []), (int) $item['fonte_id']),
            ];
        }

        return ['cotacoes' => $cotacoes, 'motivos' => $this->motivos];
    }

    /**
     * @param  array<int|string, mixed>  $resposta
     * @return array{confrontos: list<array<string, mixed>>, motivos: list<string>}
     */
    public function validar_ao_vivo(array $resposta): array
    {
        $this->iniciar($resposta, 'ao vivo');

        $confrontos = [];

        foreach ($resposta as $confronto) {
            $valido = $this->confronto_ao_vivo($confronto);

            if ($valido !== null) {
                $confrontos[] = $valido;
            }
        }

        return ['confrontos' => $confrontos, 'motivos' => $this->motivos];
    }

    /**
     * Resultados (spec 009, contrato em specs/009-apuracao-resultados/contracts/provedor.md).
     * Placar e gols decidem dinheiro: valor presente e inválido ignora o item inteiro.
     * `gols` nulo significa que o provedor não informou os gols (os gravados são mantidos).
     *
     * @param  array<int|string, mixed>  $resposta
     * @return array{resultados: list<array<string, mixed>>, motivos: list<string>}
     */
    public function validar_resultados(array $resposta): array
    {
        $this->iniciar($resposta, 'resultados');

        $resultados = [];

        foreach ($resposta as $item) {
            $valido = $this->resultado($item);

            if ($valido !== null) {
                $resultados[] = $valido;
            }
        }

        return ['resultados' => $resultados, 'motivos' => $this->motivos];
    }

    /**
     * Cancelados (spec 009): `[{"fonte_id": [ids]}]`, como no sistema antigo. Lista vazia ou sem
     * códigos = nenhum jogo cancelado.
     *
     * @param  array<int|string, mixed>  $resposta
     * @return array{codigos: list<int>, motivos: list<string>}
     */
    public function validar_cancelados(array $resposta): array
    {
        $this->iniciar($resposta, 'cancelados');

        $primeiro = $resposta[0] ?? [];
        $lista = is_array($primeiro) ? ($primeiro['fonte_id'] ?? []) : null;

        if (! is_array($lista)) {
            throw new FalhaProvedorException('Resposta do provedor (cancelados) sem a lista de códigos.');
        }

        $codigos = [];

        foreach ($lista as $codigo) {
            if (! $this->codigo_valido($codigo)) {
                $this->motivos[] = 'Código de jogo cancelado ignorado: inválido.';

                continue;
            }

            $codigos[(int) $codigo] = (int) $codigo;
        }

        return ['codigos' => array_values($codigos), 'motivos' => $this->motivos];
    }

    /**
     * A resposta de cada rota é uma lista; qualquer outra coisa recusa a carga inteira.
     *
     * @param  array<int|string, mixed>  $resposta
     */
    private function iniciar(array $resposta, string $nome): void
    {
        $this->motivos = [];

        if (! array_is_list($resposta)) {
            throw new FalhaProvedorException("Resposta do provedor ({$nome}) não é uma lista.");
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function confronto_ao_vivo(mixed $confronto): ?array
    {
        $dados = $this->dados_comuns($confronto);

        if ($dados === null) {
            return null;
        }

        // o provedor marca o ao vivo no esporte ("FUTEBOL AO VIVO"); no sistema o ao vivo é a tabela
        $dados['esporte'] = preg_replace('/\s+AO VIVO$/iu', '', $dados['esporte']);

        if (SituacaoAoVivo::tryFrom((string) ($confronto['situacao'] ?? '')) === null) {
            return $this->ignorar($confronto, 'situação inválida');
        }

        foreach (['minuto_exato', 'placar_casa', 'placar_fora'] as $campo) {
            if (! $this->inteiro_nao_negativo($confronto[$campo] ?? null)) {
                return $this->ignorar($confronto, "{$campo} inválido");
            }
        }

        $cotacoes = $this->cotacoes($confronto);

        if ($cotacoes === null) {
            return $this->ignorar($confronto, 'cotações inválidas');
        }

        // nome do provedor => nome do sistema
        $opcionais = [
            'g1_tempo_casa' => 'gols_primeiro_tempo_casa',
            'g1_tempo_fora' => 'gols_primeiro_tempo_fora',
            'g2_tempo_casa' => 'gols_segundo_tempo_casa',
            'g2_tempo_fora' => 'gols_segundo_tempo_fora',
            'escanteio_casa' => 'escanteios_casa',
            'escanteio_fora' => 'escanteios_fora',
        ];

        foreach ($opcionais as $campo => $coluna) {
            $dados[$coluna] = $this->inteiro_nao_negativo($confronto[$campo] ?? null) ? (int) $confronto[$campo] : null;
        }

        return [
            ...$dados,
            'situacao' => $confronto['situacao'],
            'minuto' => (int) $confronto['minuto_exato'],
            'cronometro' => $this->texto_valido($confronto['tempo'] ?? null) ? mb_substr(trim($confronto['tempo']), 0, 10) : null,
            'placar_casa' => (int) $confronto['placar_casa'],
            'placar_fora' => (int) $confronto['placar_fora'],
            'cotacoes' => $cotacoes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resultado(mixed $item): ?array
    {
        if (! is_array($item) || ! $this->codigo_valido($item['fonte_id'] ?? null)) {
            return $this->ignorar($item, 'código inválido');
        }

        if (SituacaoConfronto::tryFrom((string) ($item['situacao'] ?? '')) === null) {
            return $this->ignorar($item, 'situação inválida');
        }

        // nome do provedor => nome do sistema
        $campos = [
            'placar_casa' => 'placar_casa',
            'placar_fora' => 'placar_fora',
            'g1_tempo_casa' => 'gols_primeiro_tempo_casa',
            'g1_tempo_fora' => 'gols_primeiro_tempo_fora',
            'g2_tempo_casa' => 'gols_segundo_tempo_casa',
            'g2_tempo_fora' => 'gols_segundo_tempo_fora',
            'escanteio_casa' => 'escanteios_casa',
            'escanteio_fora' => 'escanteios_fora',
        ];

        $dados = [
            'codigo_externo' => (int) $item['fonte_id'],
            'situacao' => $item['situacao'],
        ];

        foreach ($campos as $campo => $coluna) {
            $valor = $item[$campo] ?? null;

            if ($valor !== null && ! $this->inteiro_nao_negativo($valor)) {
                return $this->ignorar($item, "{$campo} inválido");
            }

            $dados[$coluna] = $valor === null ? null : (int) $valor;
        }

        if (($dados['placar_casa'] === null) !== ($dados['placar_fora'] === null)) {
            return $this->ignorar($item, 'placar com um só time informado');
        }

        $eventos = $item['eventos'] ?? null;

        if ($eventos !== null && ! is_array($eventos)) {
            return $this->ignorar($item, 'eventos inválidos');
        }

        $dados['gols'] = $eventos === null ? null : $this->gols($eventos, $dados['codigo_externo']);

        return $dados;
    }

    /**
     * Gols do resultado; evento inválido é ignorado sozinho. A ordem vem do número no início de
     * "10º Gol" (o antigo lia só o primeiro caractere e errava a partir do 10º gol). O provedor
     * manda gols sem o autor (nome nulo): eles entram assim mesmo, porque contam na ordem dos gols
     * (primeiro e último gol).
     *
     * @param  array<int, mixed>  $eventos
     * @return list<array<string, mixed>>
     */
    private function gols(array $eventos, int $codigo_confronto): array
    {
        $validos = [];

        foreach ($eventos as $evento) {
            $ordem = is_array($evento) && is_string($evento['tipo_evento'] ?? null) && preg_match('/^\s*(\d+)/', $evento['tipo_evento'], $numero) === 1
                ? (int) $numero[1]
                : 0;
            if ($ordem < 1 || $ordem > 255) {
                $this->motivos[] = "Gol ignorado no confronto {$codigo_confronto}: ordem inválida.";

                continue;
            }

            // o minuto vem como texto, com acréscimos ("45+1"); não entra em nenhuma regra
            $minuto = $evento['minuto'] ?? null;

            $validos[] = [
                'ordem' => $ordem,
                'nome' => $this->texto_valido($evento['nome'] ?? null) ? mb_substr(trim($evento['nome']), 0, 150) : null,
                'minuto' => is_int($minuto) || $this->texto_valido($minuto) ? mb_substr(trim((string) $minuto), 0, 10) : null,
                'tempo_jogo' => $this->texto_valido($evento['tempo_jogo'] ?? null) ? mb_substr(trim($evento['tempo_jogo']), 0, 20) : null,
            ];
        }

        // uma ordem repetida vale uma vez só (a última ocorrência)
        return array_values(collect($validos)->keyBy('ordem')->sortKeys()->all());
    }

    /**
     * Campos comuns aos confrontos do pré-jogo e do ao vivo: código, campeonato, times, escudos,
     * esporte e data de início.
     *
     * @return array<string, mixed>|null
     */
    private function dados_comuns(mixed $confronto): ?array
    {
        if (! is_array($confronto) || ! $this->codigo_valido($confronto['fonte_id'] ?? null)) {
            return $this->ignorar($confronto, 'código inválido');
        }

        if (! $this->codigo_valido($confronto['campeonatos_id'] ?? null)) {
            return $this->ignorar($confronto, 'campeonato inválido');
        }

        foreach (['casa', 'fora', 'tipo_esporte'] as $campo) {
            if (! $this->texto_valido($confronto[$campo] ?? null)) {
                return $this->ignorar($confronto, "{$campo} inválido");
            }
        }

        $data_inicio = $this->data_utc($confronto['horario'] ?? null);

        if ($data_inicio === null) {
            return $this->ignorar($confronto, 'horário inválido');
        }

        return [
            'codigo_externo' => (int) $confronto['fonte_id'],
            'campeonato_codigo_externo' => (int) $confronto['campeonatos_id'],
            'time_casa' => mb_substr(trim($confronto['casa']), 0, 150),
            'escudo_casa' => $this->texto_valido($confronto['escudo_casa'] ?? null) ? mb_substr(trim($confronto['escudo_casa']), 0, 255) : null,
            'time_fora' => mb_substr(trim($confronto['fora']), 0, 150),
            'escudo_fora' => $this->texto_valido($confronto['escudo_fora'] ?? null) ? mb_substr(trim($confronto['escudo_fora']), 0, 255) : null,
            'esporte' => mb_substr(trim($confronto['tipo_esporte']), 0, 50),
            'data_inicio' => $data_inicio,
        ];
    }

    /**
     * Cotações no formato do provedor: um campo por código (odd1 a odd323) no próprio item.
     * Código ausente ou nulo vale zero; valor não numérico ou negativo invalida o item. Devolve
     * só os diferentes de zero, com 2 casas.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, float>|null
     */
    private function cotacoes(array $item): ?array
    {
        $validas = [];

        foreach (CodigosCotacao::cotacoes() as $codigo) {
            $valor = $item[$codigo] ?? null;

            if ($valor === null || $valor === '') {
                continue;
            }

            if (! is_numeric($valor) || (float) $valor < 0) {
                return null;
            }

            if ((float) $valor > 0) {
                $validas[$codigo] = round((float) $valor, 2);
            }
        }

        return $validas;
    }

    /**
     * Jogador inválido é ignorado sozinho; o confronto continua valendo.
     *
     * @param  array<int, mixed>  $jogadores
     * @return list<array<string, mixed>>
     */
    private function jogadores(array $jogadores, int $codigo_confronto): array
    {
        $validos = [];

        foreach ($jogadores as $jogador) {
            if (! is_array($jogador) || ! $this->codigo_valido($jogador['atletas_id'] ?? null)
                || ! $this->texto_valido($jogador['nome'] ?? null) || ! $this->texto_valido($jogador['opcao'] ?? null)
                || ! $this->texto_valido($jogador['tipo'] ?? null) || ! is_numeric($jogador['odd'] ?? null) || (float) $jogador['odd'] <= 0) {
                $this->motivos[] = "Jogador ignorado no confronto {$codigo_confronto}: dados inválidos.";

                continue;
            }

            $validos[] = [
                'codigo_externo' => (int) $jogador['atletas_id'],
                'nome' => mb_substr(trim($jogador['nome']), 0, 150),
                'opcao' => mb_substr(trim($jogador['opcao']), 0, 60),
                'tipo' => mb_substr(trim($jogador['tipo']), 0, 60),
                'odd' => round((float) $jogador['odd'], 2),
            ];
        }

        return $validos;
    }

    /**
     * Data do provedor (UTC, "AAAA-MM-DD HH:MM:SS"; aceita também o formato ISO) normalizada
     * para "AAAA-MM-DD HH:MM:SS" em UTC.
     */
    private function data_utc(mixed $valor): ?string
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $valor) !== 1) {
            return null;
        }

        try {
            $utc = new DateTimeZone('UTC');

            return (new DateTimeImmutable($valor, $utc))->setTimezone($utc)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function ignorar(mixed $confronto, string $motivo): null
    {
        $this->motivos[] = "Confronto ignorado ({$this->descrever($confronto)}): {$motivo}.";

        return null;
    }

    private function descrever(mixed $item): string
    {
        return is_array($item) && isset($item['fonte_id']) && is_scalar($item['fonte_id']) ? 'código '.(string) $item['fonte_id'] : 'sem código';
    }

    private function codigo_valido(mixed $valor): bool
    {
        return is_int($valor) ? $valor > 0 : (is_string($valor) && ctype_digit($valor) && (int) $valor > 0);
    }

    private function texto_valido(mixed $valor): bool
    {
        return is_string($valor) && trim($valor) !== '';
    }

    private function inteiro_nao_negativo(mixed $valor): bool
    {
        return (is_int($valor) && $valor >= 0) || (is_string($valor) && ctype_digit($valor));
    }
}
