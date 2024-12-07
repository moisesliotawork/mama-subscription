<?php

namespace App\Filament\App\Resources\UserSubscriptionResource\Pages;

use App\Filament\App\Resources\UserSubscriptionResource;
use App\Models\Subscription;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Http;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class UserSubscriptionPayment extends Page
{
    protected static string $resource = UserSubscriptionResource::class;
    protected static string $view = 'filament.pages.user-subscription-payment';

    public Subscription $subscription;

    public $bank;
    public $phone;
    public $identity;
    public $amount;
    public $otp;

    public function mount($record): void
    {
        $this->subscription = Subscription::findOrFail($record);
        $this->amount = $this->subscription->service_price_cents / 100; // Convertir a dólares
    }

    protected function createStripeSession()
    {
        Stripe::setApiKey(config('stripe.secret_key'));

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $this->subscription->service_name, // Usar información directamente de la suscripción
                        ],
                        'unit_amount' => $this->subscription->service_price_cents, // Usar precio directamente de la suscripción
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => static::getUrl(['record' => $this->subscription->id]),
            'cancel_url' => static::getUrl(['record' => $this->subscription->id]),
        ]);

        return redirect($session->url);
    }

    public function submitBolivaresPayment(array $data)
    {
        $this->bank = $data['bank'];
        $this->phone = $data['phone'];
        $this->identity = $data['identity'];

        try {
            $otpResponse = $this->generateOtp();

            if ($otpResponse['status'] !== 'success') {
                Notification::make()
                    ->title('Error')
                    ->body('No se pudo generar el OTP. Intente nuevamente.')
                    ->danger()
                    ->send();
                return;
            }

            $this->dispatchBrowserEvent('open-otp-modal');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Interno')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmOtp(array $data)
    {
        $this->otp = $data['otp'];

        try {
            $paymentResponse = $this->processImmediateDebit();

            if ($paymentResponse['status'] === 'success') {
                Notification::make()
                    ->title('Pago Completado')
                    ->body('El pago se procesó exitosamente.')
                    ->success()
                    ->send();

                return redirect(static::getUrl(['record' => $this->subscription->id]));
            } else {
                Notification::make()
                    ->title('Error')
                    ->body('No se pudo completar el pago. Intente nuevamente.')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Interno')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function generateOtp()
    {
        $tokenAuthorization = hash_hmac(
            'sha256',
            "{$this->bank}{$this->amount}{$this->phone}{$this->identity}",
            config('banking.token_key')
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $tokenAuthorization,
            'Commerce' => config('banking.commerce_id'),
        ])->post(config('banking.otp_url'), [
                    'bank' => $this->bank,
                    'phone' => $this->phone,
                    'identity' => $this->identity,
                    'amount' => $this->amount,
                ]);

        return $response->json();
    }

    protected function processImmediateDebit()
    {
        $tokenAuthorization = hash_hmac(
            'sha256',
            "{$this->bank}{$this->identity}{$this->phone}{$this->amount}{$this->otp}",
            config('banking.token_key')
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $tokenAuthorization,
            'Commerce' => config('banking.commerce_id'),
        ])->post(config('banking.debit_url'), [
                    'bank' => $this->bank,
                    'identity' => $this->identity,
                    'phone' => $this->phone,
                    'amount' => $this->amount,
                    'otp' => $this->otp,
                ]);

        return $response->json();
    }

    protected function getActions(): array
    {
        return [
            Action::make('payInUSD')
                ->label('Pagar en USD')
                ->color('success')
                ->action(function () {
                    $this->createStripeSession();
                }),

            Action::make('payInBolivares')
                ->label('Pagar en Bolívares')
                ->color('warning')
                ->form([
                    TextInput::make('bank')->label('Banco')->required(),
                    TextInput::make('phone')->label('Teléfono')->required(),
                    TextInput::make('identity')->label('Cédula')->required(),
                    TextInput::make('amount')->label('Monto')->disabled()->default(fn() => $this->amount),
                ])
                ->action(function (array $data) {
                    $this->submitBolivaresPayment($data);
                }),

            Action::make('confirmOtp')
                ->label('Confirmar OTP')
                ->color('info')
                ->form([
                    TextInput::make('otp')->label('Código OTP')->required(),
                ])
                ->action(function (array $data) {
                    $this->confirmOtp($data);
                })
                ->hidden(fn() => !$this->otp),
        ];
    }
}
