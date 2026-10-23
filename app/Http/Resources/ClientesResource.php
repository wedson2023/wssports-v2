<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação do cliente: nunca expõe a senha nem a data de corte dos tokens.
 * No painel, sem clientes.ver_dados_completos, CPF, telefone e e-mail saem mascarados.
 */
class ClientesResource extends JsonResource
{
    /**
     * Sufixo acrescentado aos dados únicos na exclusão lógica.
     */
    public const SUFIXO_EXCLUSAO = '/_deleted_\d+$/';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mascarar = self::deve_mascarar($request);

        $telefone = $this->sem_sufixo($this->telefone);
        $cpf = $this->sem_sufixo($this->cpf);
        $email = $this->sem_sufixo($this->email);

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ddi' => $this->ddi,
            'telefone' => $mascarar ? self::mascarar_telefone($this->ddi, $telefone) : $telefone,
            'email' => $mascarar ? self::mascarar_email($email) : $email,
            'cpf' => $mascarar ? self::mascarar_cpf($cpf) : $cpf,
            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),
            'genero' => $this->genero,
            'afiliados_id' => $this->afiliados_id,
            'afiliado' => $this->whenLoaded('afiliado', fn () => $this->afiliado === null ? null : [
                'id' => $this->afiliado->id,
                'nome' => $this->afiliado->nome,
                'codigo' => $this->sem_sufixo($this->afiliado->codigo),
            ]),
            'aceita_promocao' => $this->configuracoes?->aceita_promocao,
            'ativo' => $this->ativo,
            'saldo' => $this->saldo,
            'saldo_promocao_esportes' => $this->saldo_promocao_esportes,
            'saldo_promocao_cassino' => $this->saldo_promocao_cassino,
            // calculado só na lista e no detalhe do painel (spec 019, FR-006)
            'saldo_liquido' => $this->when(isset($this->saldo_liquido), fn () => bcadd((string) $this->saldo_liquido, '0', 2)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->trashed(), $this->deleted_at),
        ];
    }

    /**
     * Só mascara para usuários do painel sem a permissão de ver dados completos.
     */
    public static function deve_mascarar(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario instanceof Usuarios
            && ! Funcao::usuario_pode($usuario, 'clientes.ver_dados_completos');
    }

    public static function mascarar_ultimos(?string $valor, int $visiveis = 4): ?string
    {
        if ($valor === null || $valor === '') {
            return $valor;
        }

        $tamanho = mb_strlen($valor);

        return str_repeat('*', max($tamanho - $visiveis, 0)).mb_substr($valor, -$visiveis);
    }

    private function sem_sufixo(?string $valor): ?string
    {
        return $valor === null ? null : preg_replace(self::SUFIXO_EXCLUSAO, '', $valor);
    }

    public static function mascarar_cpf(?string $cpf): ?string
    {
        return $cpf === null ? null : '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**';
    }

    public static function mascarar_telefone(string $ddi, string $telefone): string
    {
        if ($ddi !== '55') {
            return self::mascarar_ultimos($telefone);
        }

        return '('.substr($telefone, 0, 2).') '.str_repeat('*', strlen($telefone) - 6).'-'.substr($telefone, -4);
    }

    public static function mascarar_email(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return $email;
        }

        [$usuario, $dominio] = explode('@', $email, 2);

        return mb_substr($usuario, 0, 1).'***@'.$dominio;
    }
}
