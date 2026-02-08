<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Professional Mid-Max Login Page - 100% Native Filament
 * 
 * Features:
 * - Username OR Email login (flexible identifier)
 * - Rate limiting (5 attempts per minute)
 * - Account status validation (active/locked/blocked)
 * - Security logging (audit trail)
 * - PHP 8.5+ strict type hints
 */
class Login extends BaseLogin
{
    /**
     * Override form schema to support username OR email login
     * Professional mid-max: Cleaner form structure
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getLoginFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * Customize login field to accept email OR username
     * Professional mid-max: Better UX with Indonesia language
     */
    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email atau Username')
            ->placeholder('Masukkan email atau username Anda')
            ->helperText('Anda bisa login menggunakan email atau username')
            ->prefixIcon('heroicon-o-at-symbol')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes([
                'tabindex' => 1,
                'class' => 'text-base',
            ]);
    }

    /**
     * Customize password field with enhanced UX
     * Professional mid-max: Revealable password, better hint
     */
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->hint(filament()->hasPasswordReset() ? new \Illuminate\Support\HtmlString(
                \Illuminate\Support\Facades\Blade::render(
                    '<x-filament::link :href="filament()->getRequestPasswordResetUrl()" class="text-sm font-medium"> 
                        Lupa password?
                    </x-filament::link>'
                )
            ) : null)
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->prefixIcon('heroicon-o-lock-closed')
            ->required()
            ->extraInputAttributes([
                'tabindex' => 2,
                'class' => 'text-base',
            ]);
    }

    /**
     * Customize remember me checkbox
     * Professional mid-max: Default true for better UX
     */
    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Ingat saya selama 30 hari')
            ->default(true)
            ->extraAttributes(['tabindex' => 3]);
    }

    /**
     * Get page heading - Indonesia language
     */
    public function getHeading(): string
    {
        return 'Selamat Datang Kembali';
    }

    /**
     * Get page subheading - Indonesia language
     */
    public function getSubheading(): ?string
    {
        return 'Masuk ke akun Anda untuk melanjutkan';
    }

    /**
     * Enhanced authentication with security checks
     * Professional mid-max: Strict type hints, comprehensive validation
     */
    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        try {
            $data = $this->form->getState();

            // Rate limiting check (5 attempts per minute per IP)
            $this->rateLimit(5);

            // Attempt authentication using custom provider
            // The 'flexible-eloquent' provider handles email OR username
            if (!Auth::attempt([
                'email' => $data['email'], // Field name stays 'email' but provider checks both
                'password' => $data['password'],
            ], $data['remember'] ?? false)) {
                $this->throwFailureValidationException();
            }

            /** @var User $user */
            $user = Auth::user();

            // Security check 1: Account must be active
            if ($user->account_status !== 'active') {
                Auth::logout();
                throw ValidationException::withMessages([
                    'data.email' => 'Account Anda berstatus: ' . $user->account_status . '. Hubungi administrator.',
                ]);
            }

            // Security check 2: Account must not be locked
            if ($user->isLocked()) {
                Auth::logout();
                $minutesRemaining = 30 - ($user->locked_at?->diffInMinutes(now()) ?? 0);
                throw ValidationException::withMessages([
                    'data.email' => "Account Anda terkunci sementara karena kesalahan login berulang. Coba lagi dalam {$minutesRemaining} menit.",
                ]);
            }

            // Security check 3: Account must not be blocked
            if ($user->isBlocked()) {
                Auth::logout();
                $message = 'Account Anda telah diblokir.';
                if ($user->blocked_reason) {
                    $message .= ' Alasan: ' . $user->blocked_reason;
                }
                throw ValidationException::withMessages([
                    'data.email' => $message,
                ]);
            }

            // Regenerate session (anti-fixation attack)
            request()->session()->regenerate();

            // Update login tracking
            $user->update([
                'last_login_ip' => request()->ip(),
                'last_seen_ip' => request()->ip(),
                'last_seen_at' => now(),
                'failed_login_attempts' => 0,
            ]);

            // Log successful login
            $user->logActivity('login_success', [
                'identity' => $data['email'],
                'context' => ['remember' => $data['remember'] ?? false],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return app(\Filament\Http\Responses\Auth\Contracts\LoginResponse::class);
    }

    /**
     * Override throttle message - Indonesia language
     * Professional mid-max: User-friendly error messages
     */
    protected function getRateLimitedNotificationTitle(): ?string
    {
        return 'Terlalu banyak percobaan login. Silakan coba lagi dalam beberapa menit.';
    }
}
