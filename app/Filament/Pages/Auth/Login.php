<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected bool $accountDisabled = false;

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        $allowed = parent::isUserAllowedToAccessPanel($user);

        $this->accountDisabled = ! $allowed;

        return $allowed;
    }

    protected function throwFailureValidationException(): never
    {
        if ($this->accountDisabled) {
            throw ValidationException::withMessages([
                'data.email' => 'Akun Anda dinonaktifkan.',
            ]);
        }

        parent::throwFailureValidationException();
    }
}
