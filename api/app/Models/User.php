<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'empresa_id', 'perfil', 'ativo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * O atributo #[Fillable] do Laravel 13 preenche colunas fillable não
     * informadas com NULL explícito na query, em vez de deixar o banco
     * aplicar o DEFAULT true da coluna `ativo` - sem isto, User::create()
     * sem passar 'ativo' cria um usuário NULL (falsy), efetivamente
     * inativo. Define o valor padrão aqui, no próprio model.
     */
    protected $attributes = [
        'ativo' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Empresa::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->perfil === 'super_admin';
    }
}
