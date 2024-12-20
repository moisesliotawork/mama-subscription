<?php

namespace App\Filament\Store\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;
use Filament\Facades\Filament;

class CustomerCreate extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Usuarios';
    protected static string $view = 'filament.pages.customer-create'; // Vista personalizada

    protected static ?string $title = 'Crear Clientes';


    public $email;
    public $first_name;
    public $last_name;
    public $phone_number;
    public $birth_date;
    public $showAdditionalFields = false; // Controla la visibilidad de los campos adicionales
    public $buttonLabel = 'Enviar Magic Link'; // Texto del botón

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->email = '';
        $this->first_name = '';
        $this->last_name = '';
        $this->phone_number = '';
        $this->birth_date = '';
        $this->showAdditionalFields = false;
        $this->buttonLabel = 'Enviar Magic Link';
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('email')
                ->label('Correo Electrónico')
                ->email()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, $set) {
                    $userExists = User::where('email', $state)->exists();

                    if ($userExists) {
                        $set('showAdditionalFields', false);
                        $set('buttonLabel', 'Enviar Magic Link');
                    } else {
                        $set('showAdditionalFields', true);
                        $set('buttonLabel', 'Registrar Cliente');
                    }
                }),

            Forms\Components\TextInput::make('first_name')
                ->label('Nombre')
                ->required()
                ->hidden(fn($get) => !$get('showAdditionalFields')),

            Forms\Components\TextInput::make('last_name')
                ->label('Apellido')
                ->required()
                ->hidden(fn($get) => !$get('showAdditionalFields')),

            Forms\Components\TextInput::make('phone_number')
                ->label('Número de Teléfono')
                ->required()
                ->hidden(fn($get) => !$get('showAdditionalFields')),

            Forms\Components\DatePicker::make('birth_date')
                ->label('Fecha de Nacimiento')
                ->nullable()
                ->hidden(fn($get) => !$get('showAdditionalFields')),
        ];
    }

    public function submit()
    {
        // Obtener la tienda actual usando Filament::getTenant()
        $currentStore = Filament::getTenant();

        if (!$currentStore) {
            Notification::make()
                ->title('Error')
                ->body('No se pudo identificar la tienda actual.')
                ->danger()
                ->send();
            return;
        }

        $currentStoreId = $currentStore->id; // ID de la tienda actual

        // Buscar el usuario por correo electrónico
        $user = User::where('email', $this->email)->first();

        if ($user) {
            // Validar si el usuario en sesión coincide
            if ($user->id === auth()->id()) {
                Notification::make()
                    ->title('Error')
                    ->body('No puedes registrar al usuario en sesión como cliente.')
                    ->danger()
                    ->send();
                return;
            }

            // Verificar si el usuario ya está asociado a la tienda como `customer`
            $existingCustomerRole = $user->stores()
                ->wherePivot('store_id', $currentStoreId)
                ->wherePivot('role', 'customer')
                ->exists();

            if ($existingCustomerRole) {
                Notification::make()
                    ->title('Cliente existente')
                    ->body('El usuario ya está asociado como cliente a esta tienda.')
                    ->warning()
                    ->send();
                return;
            }

            // Asociar al usuario como cliente (nueva entrada con rol `customer`)
            $user->assignRole('customer');
            $user->stores()->attach($currentStoreId, ['role' => 'customer']);
            $this->sendMagicLink($user);

            Notification::make()
                ->title('Cliente registrado')
                ->body('El usuario fue asociado como cliente a la tienda y se le envió un enlace mágico.')
                ->success()
                ->send();
        } else {
            // Crear un nuevo cliente
            $newUser = User::create([
                'email' => $this->email,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone_number' => $this->phone_number,
                'birth_date' => $this->birth_date ?: null,
                'password' => bcrypt('default_password'),
            ]);

            $newUser->assignRole('customer');
            $newUser->stores()->attach($currentStoreId, ['role' => 'customer']);
            $this->sendMagicLink($newUser);

            Notification::make()
                ->title('Cliente registrado')
                ->body('El cliente fue registrado exitosamente y se le envió un enlace mágico.')
                ->success()
                ->send();
        }

        $this->resetForm();
    }




    protected function sendMagicLink(User $user): void
    {
        $action = new LoginAction($user);
        $magicLinkUrl = MagicLink::create($action)->url;

        $user->notify(new \App\Notifications\MagicLinkNotification($magicLinkUrl));
    }
}
