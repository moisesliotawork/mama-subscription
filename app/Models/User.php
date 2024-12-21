<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use NorbyBaru\Passwordless\CanUsePasswordlessAuthenticatable;
use NorbyBaru\Passwordless\Traits\PasswordlessAuthenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail, HasTenants, CanUsePasswordlessAuthenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes, PasswordlessAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'identity_document',
        'email',
        'password',
        'birth_date',
        'address',
        'selfie_path',
        'ci_picture_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->{$user->getKeyName()})) {
                $user->{$user->getKeyName()} = (string) Str::ulid();
            }
        });
    }

    public function name(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->first_name . ' ' . $this->last_name,
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $panelId = $panel->getId();

        //dd($panelId, auth()->user()->roles->pluck('name'));

        // Lógica para el panel de admin
        if ($panelId === 'admin') {
            return $this->hasRole('admin');
        }

        // Lógica para el panel de app
        if ($panelId === 'app') {
            return $this->hasRole('customer');
        }

        // Lógica para el panel de owner_store
        if ($panelId === 'store') {
            return $this->hasRole('owner_store') || $this->hasRole('employee');
        }

        // Retorna false por defecto si no coincide con ninguno de los paneles
        return false;
    }

    // Relación con las tiendas donde el usuario es 'owner', 'employee' o 'customer'
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Obtener tiendas donde el usuario es 'employee'
    public function employeeStores()
    {
        return $this->stores()->wherePivot('role', 'employee');
    }

    // Obtener tiendas donde el usuario es 'customer'
    public function customerStores()
    {
        return $this->stores()->wherePivot('role', 'customer');
    }

    // Obtener tiendas donde el usuario es 'owner_store'
    public function ownedStores()
    {
        return $this->stores()->wherePivot('role', 'owner_store');
    }

    /**
     * Implementación para Filament: Obtener los tenantes (stores) para un panel
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($panel->getId() === 'store') {
            // Obtener las tiendas a las que el usuario tiene acceso (owner o empleado)
            return $this->stores;
        }

        return collect(); // Retornar vacío para otros paneles
    }

    /**
     * Implementación para Filament: Verificar si el usuario puede acceder a un tenante específico
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->ownedStores()
            ->where('stores.id', $tenant->getKey()) // Especificar tabla 'stores'
            ->exists()
            || $this->stores()
                ->where('stores.id', $tenant->getKey()) // Especificar tabla 'stores'
                ->exists();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

}
