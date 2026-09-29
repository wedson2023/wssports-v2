<?php

namespace App\Models;

use App\Enums\Funcao;
use Database\Factories\UsuariosFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class Usuarios extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UsuariosFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    /**
     * Papéis e permissões pertencem ao guard da API (JWT).
     */
    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'login',
        'password',
        'telefone',
        'endereco',
        'ativo',
        'usuarios_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Usuário imediatamente acima na hierarquia.
     */
    public function superior(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    /**
     * Usuários imediatamente abaixo na hierarquia.
     */
    public function subordinados(): HasMany
    {
        return $this->hasMany(Usuarios::class, 'usuarios_id');
    }

    /**
     * Função do usuário, lida do seu único papel no spatie.
     */
    public function funcao(): ?Funcao
    {
        return Funcao::tryFrom((string) $this->getRoleNames()->first());
    }

    /**
     * Ids de todos os usuários abaixo deste, em qualquer nível.
     *
     * Percorre a árvore nível a nível (uma consulta por nível), sem depender de CTE
     * recursiva. Usuários excluídos logicamente são ignorados.
     *
     * @return list<int>
     */
    public function ids_sub_hierarquia(): array
    {
        $ids_encontrados = [];
        $ids_nivel_atual = [$this->id];

        while ($ids_nivel_atual !== []) {
            $ids_nivel_atual = static::whereIn('usuarios_id', $ids_nivel_atual)->pluck('id')->all();
            $ids_encontrados = [...$ids_encontrados, ...$ids_nivel_atual];
        }

        return $ids_encontrados;
    }

    /**
     * Indica se o alvo está na sub-hierarquia deste usuário (nunca inclui ele mesmo).
     */
    public function gerencia(Usuarios $alvo): bool
    {
        return in_array($alvo->id, $this->ids_sub_hierarquia(), true);
    }

    /**
     * Verificação única de acesso ao sistema: só usuários ativos e não excluídos.
     */
    public function pode_acessar(): bool
    {
        return $this->ativo && ! $this->trashed();
    }

    /**
     * Ativa ou desativa este usuário e toda a sua sub-hierarquia, de forma atômica.
     */
    public function alterar_situacao_em_cascata(bool $ativo): void
    {
        DB::transaction(function () use ($ativo) {
            static::whereIn('id', $this->ids_com_sub_hierarquia())->update(['ativo' => $ativo]);
        });

        $this->ativo = $ativo;
        $this->syncOriginalAttribute('ativo');
    }

    /**
     * Exclui logicamente (soft delete) este usuário e toda a sua sub-hierarquia, de forma atômica.
     */
    public function excluir_em_cascata(): void
    {
        DB::transaction(function () {
            static::whereIn('id', $this->ids_com_sub_hierarquia())->delete();
        });
    }

    /**
     * Dá ao usuário as permissões padrão da sua função que o cadastrante também possui,
     * para que ninguém repasse uma permissão que não tem.
     */
    public function atribuir_permissoes_padrao(Usuarios $cadastrante): void
    {
        $permissoes = array_filter(
            $this->funcao()->permissoes_padrao(),
            fn (string $permissao) => $cadastrante->hasPermissionTo($permissao),
        );

        $this->givePermissionTo($permissoes);
    }

    /**
     * @return list<int>
     */
    private function ids_com_sub_hierarquia(): array
    {
        return [$this->id, ...$this->ids_sub_hierarquia()];
    }
}
