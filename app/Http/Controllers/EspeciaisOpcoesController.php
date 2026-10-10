<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\EspeciaisOpcoesRequest;
use App\Http\Resources\EspeciaisOpcoesResource;
use App\Models\Especiais;
use App\Models\EspeciaisOpcoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;

/**
 * Opções de uma categoria especial: cadastro, edição e remoção, só com a categoria aguardando.
 * Alterar a cotação vale para os próximos palpites; os gravados mantêm a cotação deles.
 */
class EspeciaisOpcoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('especiais.gerenciar')];
    }

    public function store(EspeciaisOpcoesRequest $request, Especiais $especial): JsonResponse
    {
        EspeciaisController::garantir_aguardando($especial);

        $opcao = $especial->opcoes()->create($request->validated());

        return (new EspeciaisOpcoesResource($opcao))->response()->setStatusCode(201);
    }

    public function update(EspeciaisOpcoesRequest $request, Especiais $especial, EspeciaisOpcoes $opcao): EspeciaisOpcoesResource
    {
        $this->garantir_da_categoria($especial, $opcao);
        EspeciaisController::garantir_aguardando($especial);

        $opcao->update($request->validated());

        return new EspeciaisOpcoesResource($opcao);
    }

    public function destroy(Especiais $especial, EspeciaisOpcoes $opcao): Response
    {
        $this->garantir_da_categoria($especial, $opcao);
        EspeciaisController::garantir_aguardando($especial);

        $com_palpite = EspeciaisController::palpites_em_apostas_ativas($especial->palpites()->getQuery())
            ->where('especiais_opcoes_id', $opcao->id)
            ->exists();

        if ($com_palpite) {
            throw ValidationException::withMessages(['opcao' => 'Desative a opção em vez de remover.']);
        }

        $opcao->delete();

        return response()->noContent();
    }

    /**
     * A opção da URL precisa ser da categoria da URL.
     */
    private function garantir_da_categoria(Especiais $especial, EspeciaisOpcoes $opcao): void
    {
        abort_unless($opcao->especiais_id === $especial->id, 404, 'Categoria ou opção não encontrada.');
    }
}
