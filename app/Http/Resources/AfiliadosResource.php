<?php

namespace App\Http\Resources;

use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Afiliado na gestão do painel (contracts/api §2.1). Sem clientes.ver_dados_completos, CPF,
 * telefone, e-mail e chave Pix saem mascarados, como nos clientes (FR-046). Os totais só aparecem
 * quando a consulta os calcula (ListagemAfiliados).
 */
class AfiliadosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mascarar = ClientesResource::deve_mascarar($request);
        $email = $this->sem_sufixo($this->email);
        $cpf = $this->sem_sufixo($this->cpf);
        $telefone = $this->telefone;
        $chave_pix = $this->meio_pagamento?->pix_chave;

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'login' => $this->sem_sufixo($this->login),
            'codigo' => $this->sem_sufixo($this->codigo),
            'email' => $mascarar ? ClientesResource::mascarar_email($email) : $email,
            'cpf' => $mascarar ? ClientesResource::mascarar_cpf($cpf) : $cpf,
            'ddi' => $this->ddi,
            'telefone' => $mascarar && $telefone !== null ? ClientesResource::mascarar_telefone($this->ddi, $telefone) : $telefone,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'endereco' => $this->endereco,
            // perfil na rede social (spec 022); nulos nos afiliados antigos
            'rede_social' => $this->rede_social,
            'link_perfil' => $this->link_perfil,
            'quantidade_seguidores' => $this->quantidade_seguidores,
            'sobre' => $this->sobre,
            'situacao' => $this->situacao,
            'motivo_recusa' => $this->motivo_recusa,
            'origem_cadastro' => $this->origem_cadastro,
            'gerente' => $this->whenLoaded('gerente', fn () => $this->gerente === null ? null : [
                'id' => $this->gerente->id,
                'nome' => $this->gerente->nome,
            ]),
            // afiliado sem gerente responsável: os clientes dele ficam só para o Admin (spec 019)
            'sem_gerente' => $this->usuarios_id === null,
            'comissao' => $this->whenLoaded('comissao_vigente', fn () => $this->comissao_vigente === null ? null : [
                'tipo_ganho' => $this->comissao_vigente->tipo_ganho,
                'percentual' => $this->comissao_vigente->percentual,
                'vigente_desde' => $this->comissao_vigente->vigente_desde,
            ]),
            'meio_pagamento' => $this->whenLoaded('meio_pagamento', fn () => $this->meio_pagamento === null ? null : [
                'pix_tipo_chave' => $this->meio_pagamento->pix_tipo_chave,
                'pix_chave' => $mascarar ? ClientesResource::mascarar_ultimos($chave_pix) : $chave_pix,
            ]),
            'totais_periodo' => $this->when($this->tem_atributo('cadastros_periodo'), fn () => [
                'cadastros' => (int) $this->cadastros_periodo,
                'depositos' => self::valor($this->depositos_periodo),
                'saques' => self::valor($this->saques_periodo),
                'comissao' => self::valor($this->comissao_periodo),
            ]),
            'valor_a_receber' => $this->when($this->tem_atributo('valor_a_receber'), fn () => self::valor($this->valor_a_receber)),
            // base dos lançamentos em aberto, a coluna "Entradas" do antigo (spec 022, FR-006)
            'entradas' => $this->when($this->tem_atributo('entradas'), fn () => self::valor($this->entradas)),
            'ultima_prestacao' => $this->whenLoaded('ultima_prestacao', fn () => $this->ultima_prestacao === null ? null : [
                'valor_pago' => $this->ultima_prestacao->valor_pago,
                'periodo_fim' => $this->ultima_prestacao->periodo_fim,
            ]),
            'cadastrado_por' => $this->whenLoaded('autor_cadastro', fn () => self::usuario($this->autor_cadastro)),
            'decidido_por' => $this->whenLoaded('autor_decisao', fn () => self::usuario($this->autor_decisao)),
            'decidido_em' => $this->decidido_em,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->trashed(), $this->deleted_at),
        ];
    }

    /**
     * Soma vinda do banco (texto ou nulo) com 2 casas, sem ponto flutuante.
     */
    public static function valor(mixed $valor): string
    {
        return bcadd((string) ($valor ?? '0'), '0', 2);
    }

    /**
     * @return array{id: int, nome: string}|null
     */
    private static function usuario(?Usuarios $usuario): ?array
    {
        return $usuario === null ? null : ['id' => $usuario->id, 'nome' => $usuario->nome];
    }

    private function tem_atributo(string $nome): bool
    {
        return array_key_exists($nome, $this->resource->getAttributes());
    }

    private function sem_sufixo(?string $valor): ?string
    {
        return $valor === null ? null : preg_replace(ClientesResource::SUFIXO_EXCLUSAO, '', $valor);
    }
}
