<?php

namespace App\Enums;

enum SubscriptionStatusEnum: string
{
    case OnTrial = 'on_trial';

    case Active = 'active';

    case Paused = 'paused';

    case PastDue = 'past_due';

    case Unpaid = 'unpaid';

    case Cancelled = 'cancelled';

    case Expired = 'expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OnTrial => 'En periodo de prueba',
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::PastDue => 'Con deuda',
            self::Unpaid => 'No pagada',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OnTrial, self::Active => 'success',
            default => 'danger',
        };
    }
}
