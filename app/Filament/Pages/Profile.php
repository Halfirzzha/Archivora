<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Professional Mid-Max Profile Page - 100% Native Filament
 * 
 * Features:
 * - Organized Sections with Groups (responsive design)
 * - Avatar upload with preview
 * - Phone number encryption
 * - Password change with strength validation
 * - Real-time account info display
 * - PHP 8.5+ strict type hints
 */
class Profile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Profil Saya';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.profile';

    protected static ?string $title = 'Pengaturan Profil';

    public ?array $data = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        // Security check 1: Account must be active
        if ($user->account_status !== 'active') {
            Auth::logout();
            abort(403, 'Account Anda berstatus ' . $user->account_status . '. Hubungi administrator.');
        }

        // Security check 2: Account must not be locked
        if ($user->isLocked()) {
            Auth::logout();
            abort(403, 'Account Anda terkunci sementara. Coba lagi nanti.');
        }

        // Authorization check (uses UserPolicy)
        $this->authorize('update', $user);

        // Log profile access for security audit
        $user->loginActivities()->create([
            'event' => 'profile_accessed',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Fill form with current user data
        $this->form->fill([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'phone_country_code' => $user->phone_country_code,
            'phone_number' => $user->phone_number,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Professional Section 1: Personal Information
                Forms\Components\Section::make('Informasi Pribadi')
                    ->description('Perbarui detail pribadi dan foto profil Anda')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        // Avatar upload - Professional UX with automatic cleanup
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Foto Profil')
                            ->image()
                            ->disk('public')
                            ->directory('avatars')
                            ->imageEditor()
                            ->circleCropper()
                            ->avatar()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->columnSpanFull()
                            ->imagePreviewHeight('120')
                            ->panelLayout('compact')
                            ->saveUploadedFileUsing(function (Forms\Components\FileUpload $component, $file, ?User $record): ?string {
                                // Get current user
                                $user = Auth::user();

                                // Delete old avatar before uploading new one (local disk only)
                                if ($user && $user->avatar && Storage::disk('public')->exists($user->avatar)) {
                                    try {
                                        Storage::disk('public')->delete($user->avatar);
                                        Log::info('Avatar replaced - old file deleted', [
                                            'user_id' => $user->id,
                                            'old_avatar' => $user->avatar,
                                            'ip' => request()->ip(),
                                        ]);
                                    } catch (\Exception $e) {
                                        Log::error('Failed to delete old avatar', [
                                            'user_id' => $user->id,
                                            'old_avatar' => $user->avatar,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }

                                // Store new file using Filament default behavior
                                $uploadedPath = $component->saveUploadedFile($file);

                                if ($uploadedPath) {
                                    Log::info('New avatar uploaded', [
                                        'user_id' => $user?->id,
                                        'new_avatar' => $uploadedPath,
                                        'ip' => request()->ip(),
                                    ]);
                                }

                                return $uploadedPath;
                            }),

                        // Professional Group: Name & Username side by side (responsive)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Lengkap')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Masukkan nama lengkap Anda')
                                    ->prefixIcon('heroicon-o-identification')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('username')
                                    ->label('Username')
                                    ->unique(
                                        table: 'users',
                                        column: 'username',
                                        ignoreRecord: true,
                                        ignorable: fn(): ?User => Auth::user()
                                    )
                                    ->maxLength(50)
                                    ->nullable()
                                    ->placeholder('Username unik (opsional)')
                                    ->prefixIcon('heroicon-o-at-symbol')
                                    ->helperText('Bisa digunakan untuk login sebagai pengganti email')
                                    ->rules([
                                        'nullable',
                                        'alpha_dash',
                                        'min:3',
                                        'max:50',
                                    ])
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        // Email field - disabled for security
                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->disabled()
                            ->dehydrated(false)
                            ->prefixIcon('heroicon-o-envelope')
                            ->helperText('Email tidak dapat diubah untuk keamanan')
                            ->columnSpanFull()
                            ->extraInputAttributes(['class' => 'bg-gray-50 dark:bg-gray-900/50']),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // Professional Section 2: Contact Information
                Forms\Components\Section::make('Informasi Kontak')
                    ->description('Perbarui nomor telepon Anda')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\TextInput::make('phone_country_code')
                                    ->label('Kode Negara')
                                    ->default('+62')
                                    ->maxLength(5)
                                    ->placeholder('+62')
                                    ->prefixIcon('heroicon-o-globe-alt')
                                    ->helperText('Sertakan simbol +')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('phone_number')
                                    ->label('Nomor Telepon')
                                    ->tel()
                                    ->maxLength(20)
                                    ->nullable()
                                    ->placeholder('81234567890')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->telRegex('/^[0-9]{8,15}$/')
                                    ->helperText('Masukkan tanpa kode negara')
                                    ->columnSpan(2),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // Professional Section 3: Security (Password Change)
                Forms\Components\Section::make('Pengaturan Keamanan')
                    ->description('Ubah password untuk menjaga keamanan akun')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        // Current password verification
                        Forms\Components\TextInput::make('current_password')
                            ->label('Password Saat Ini')
                            ->password()
                            ->revealable()
                            ->required(fn(?callable $get = null): bool => filled($get ? $get('new_password') : null))
                            ->rules([
                                function (): \Closure {
                                    return function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (filled($value) && !Hash::check($value, Auth::user()->password)) {
                                            $fail('Password saat ini tidak benar.');
                                        }
                                    };
                                },
                            ])
                            ->dehydrated(false)
                            ->prefixIcon('heroicon-o-key')
                            ->helperText('Wajib diisi saat mengubah password')
                            ->columnSpanFull(),

                        // New password fields in group (responsive)
                        Forms\Components\Fieldset::make('Ubah Password')
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('new_password')
                                            ->label('Password Baru')
                                            ->password()
                                            ->revealable()
                                            ->minLength(8)
                                            ->maxLength(255)
                                            ->dehydrated(false)
                                            ->prefixIcon('heroicon-o-lock-closed')
                                            ->helperText('Min 8 karakter: huruf besar, kecil, angka & simbol (@$!%*#?&)')
                                            ->placeholder('Kosongkan jika tidak ingin mengubah')
                                            ->rules([
                                                'nullable',
                                                'min:8',
                                                'regex:/[a-z]/', // lowercase
                                                'regex:/[A-Z]/', // uppercase
                                                'regex:/[0-9]/', // number
                                                'regex:/[@$!%*#?&]/', // special char
                                            ])
                                            ->validationMessages([
                                                'regex' => 'Password harus mengandung huruf besar, kecil, angka & simbol khusus (@$!%*#?&)',
                                            ])
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('new_password_confirmation')
                                            ->label('Konfirmasi Password Baru')
                                            ->password()
                                            ->revealable()
                                            ->same('new_password')
                                            ->required(fn(?callable $get = null): bool => filled($get ? $get('new_password') : null))
                                            ->dehydrated(false)
                                            ->prefixIcon('heroicon-o-lock-closed')
                                            ->placeholder('Masukkan ulang password baru')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                // Professional Section 4: Account Information (Read-only)
                Forms\Components\Section::make('Informasi Akun')
                    ->description('Lihat detail akun Anda (hanya baca)')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        // Role & Status badges
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Placeholder::make('role')
                                    ->label('Peran Akun')
                                    ->content(fn(): string => ucfirst(Auth::user()->role))
                                    ->extraAttributes(['class' => 'text-lg font-bold text-primary-600']),

                                Forms\Components\Placeholder::make('account_status')
                                    ->label('Status Akun')
                                    ->content(fn(): string => ucfirst(Auth::user()->account_status))
                                    ->extraAttributes([
                                        'class' => 'text-lg font-bold ' . (Auth::user()->account_status === 'active' ? 'text-success-600' : 'text-warning-600')
                                    ]),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        // Activity tracking info
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Terdaftar Sejak')
                                    ->content(fn(): string => Auth::user()->created_at->format('d M Y H:i'))
                                    ->extraAttributes(['class' => 'text-sm text-gray-600 dark:text-gray-400']),

                                Forms\Components\Placeholder::make('last_login_ip')
                                    ->label('IP Login Terakhir')
                                    ->content(fn(): string => Auth::user()->last_login_ip ?? 'Belum Pernah')
                                    ->extraAttributes(['class' => 'text-sm text-gray-600 dark:text-gray-400']),

                                Forms\Components\Placeholder::make('last_seen_at')
                                    ->label('Aktivitas Terakhir')
                                    ->content(fn(): string => Auth::user()->last_seen_at?->diffForHumans() ?? 'Tidak Diketahui')
                                    ->extraAttributes(['class' => 'text-sm text-gray-600 dark:text-gray-400']),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    /**
     * Update user profile with security logging
     * Professional mid-max: Strict type hints, comprehensive change tracking
     */
    public function updateProfile(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorize('update', $user);

        $data = $this->form->getState();

        // Track changes for logging (old vs new values)
        $changes = [];

        // Update basic fields with change tracking
        $fieldsToUpdate = ['name', 'username', 'avatar', 'phone_country_code', 'phone_number'];
        foreach ($fieldsToUpdate as $field) {
            if (isset($data[$field]) && $user->getAttribute($field) !== $data[$field]) {
                $changes[$field] = [
                    'old' => $user->getAttribute($field),
                    'new' => $data[$field],
                ];
                $user->setAttribute($field, $data[$field]);
            }
        }

        // Handle password change dengan hashing
        if (!empty($data['new_password'])) {
            // Current password already validated in form rules
            $user->password = Hash::make($data['new_password']);
            $changes['password'] = 'changed';

            // Log password change secara terpisah
            $user->loginActivities()->create([
                'event' => 'password_change',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'context' => ['method' => 'profile_update'],
            ]);

            Notification::make()
                ->success()
                ->title('Password Berhasil Diubah')
                ->body('Password Anda telah diperbarui dengan sukses.')
                ->icon('heroicon-o-shield-check')
                ->iconColor('success')
                ->send();
        }

        // Save user
        $user->save();

        // Show success notification if profile fields changed
        if (!empty($changes) && !isset($changes['password'])) {
            Notification::make()
                ->success()
                ->title('Profil Berhasil Diperbarui')
                ->body('Data profil Anda telah disimpan dengan sukses.')
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->send();
        }

        // Refresh form with updated data (excluding password fields)
        $this->form->fill([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'phone_country_code' => $user->phone_country_code,
            'phone_number' => $user->phone_number,
        ]);
    }

    /**
     * Get form actions (Save button)
     * Professional mid-max: Return type array with proper typing
     *
     * @return array<Forms\Components\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Simpan Perubahan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size('lg')
                ->action('updateProfile')
                ->keyBindings(['mod+s']),
        ];
    }

    /**
     * Check if user can access this page
     * Professional mid-max: Explicit return type
     */
    public static function canAccess(): bool
    {
        return Auth::check();
    }
}
