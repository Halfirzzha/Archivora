<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\LoginActivitiesRelationManager;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->description('User personal information and contact details')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Full name')
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->placeholder('user@example.com')
                        ->suffixIcon('heroicon-o-envelope'),

                    Forms\Components\TextInput::make('username')
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder('Optional unique username')
                        ->suffixIcon('heroicon-o-at-symbol')
                        ->helperText('Can be used for login instead of email'),

                    Forms\Components\FileUpload::make('avatar')
                        ->label('Avatar')
                        ->image()
                        ->disk('public')
                        ->directory('avatars')
                        ->imageEditor()
                        ->circleCropper()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->columnSpanFull()
                        ->imagePreviewHeight('120')
                        ->saveUploadedFileUsing(function (Forms\Components\FileUpload $component, $file, ?User $record): ?string {
                            // Delete old avatar before uploading new one (local disk only)
                            if ($record && $record->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($record->avatar)) {
                                try {
                                    \Illuminate\Support\Facades\Storage::disk('public')->delete($record->avatar);
                                    \Illuminate\Support\Facades\Log::info('Avatar replaced - old file deleted', [
                                        'user_id' => $record->id,
                                        'old_avatar' => $record->avatar,
                                        'admin_id' => \Illuminate\Support\Facades\Auth::id(),
                                    ]);
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Failed to delete old avatar', [
                                        'user_id' => $record->id,
                                        'old_avatar' => $record->avatar,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                            return $component->saveUploadedFile($file);
                        }),

                    Forms\Components\TextInput::make('phone_country_code')
                        ->default('+62')
                        ->maxLength(5)
                        ->placeholder('+62')
                        ->prefixIcon('heroicon-o-phone'),

                    Forms\Components\TextInput::make('phone_number')
                        ->tel()
                        ->maxLength(20)
                        ->placeholder('81234567890')
                        ->telRegex('/^[0-9]{8,15}$/'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Security & Authentication')
                ->description('Password and security settings')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn($state) => filled($state))
                        ->required(fn(string $context): bool => $context === 'create')
                        ->maxLength(255)
                        ->placeholder('Enter strong password')
                        ->revealable()
                        ->helperText(fn(string $context): ?string => $context === 'edit' ? 'Leave blank to keep current password' : null),

                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->same('password')
                        ->dehydrated(false)
                        ->required(fn(string $context): bool => $context === 'create')
                        ->placeholder('Confirm password')
                        ->revealable()
                        ->visible(fn($get) => filled($get('password'))),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('Authorization & Role')
                ->description('User permissions and access level')
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\Select::make('role')
                        ->required()
                        ->default('user')
                        ->options([
                            'user' => 'User',
                            'admin' => 'Administrator',
                            'developer' => 'Developer',
                        ])
                        ->native(false)
                        ->suffixIcon('heroicon-o-user-circle')
                        ->helperText('Define user access level in the system'),

                    Forms\Components\Select::make('account_status')
                        ->required()
                        ->default('active')
                        ->options([
                            'active' => 'Active',
                            'blocked' => 'Blocked',
                            'suspended' => 'Suspended',
                            'terminated' => 'Terminated',
                        ])
                        ->native(false)
                        ->suffixIcon('heroicon-o-shield-exclamation')
                        ->helperText('Current account status'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Account Management')
                ->description('Blocking and suspension details')
                ->icon('heroicon-o-lock-closed')
                ->schema([
                    Forms\Components\Textarea::make('blocked_reason')
                        ->rows(3)
                        ->maxLength(65535)
                        ->placeholder('Reason for blocking/suspending account')
                        ->columnSpanFull()
                        ->visible(fn($get) => in_array($get('account_status'), ['blocked', 'suspended', 'terminated'])),

                    Forms\Components\DateTimePicker::make('blocked_until')
                        ->native(false)
                        ->timezone('Asia/Jakarta')
                        ->helperText('Auto-unblock after this time')
                        ->visible(fn($get) => $get('account_status') === 'blocked'),

                    Forms\Components\Select::make('blocked_by')
                        ->relationship('blockedBy', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Select blocking admin')
                        ->suffixIcon('heroicon-o-user')
                        ->helperText('Admin who blocked this account')
                        ->visible(fn($get) => in_array($get('account_status'), ['blocked', 'suspended'])),
                ])
                ->columns(2)
                ->collapsed()
                ->visible(fn($get) => in_array($get('account_status'), ['blocked', 'suspended', 'terminated'])),

            Forms\Components\Section::make('Security Audit Trail')
                ->description('IP tracking and login security (read-only)')
                ->icon('heroicon-o-eye')
                ->schema([
                    Forms\Components\TextInput::make('last_login_ip')
                        ->disabled()
                        ->placeholder('N/A')
                        ->prefixIcon('heroicon-o-globe-alt')
                        ->helperText('Last successful login IP'),

                    Forms\Components\TextInput::make('last_seen_ip')
                        ->disabled()
                        ->placeholder('N/A')
                        ->prefixIcon('heroicon-o-eye')
                        ->helperText('Last activity IP'),

                    Forms\Components\TextInput::make('last_failed_login_ip')
                        ->disabled()
                        ->placeholder('N/A')
                        ->prefixIcon('heroicon-o-exclamation-triangle')
                        ->helperText('Last failed login attempt IP'),

                    Forms\Components\DateTimePicker::make('last_seen_at')
                        ->disabled()
                        ->native(false)
                        ->timezone('Asia/Jakarta')
                        ->helperText('Last activity timestamp'),

                    Forms\Components\TextInput::make('failed_login_attempts')
                        ->disabled()
                        ->numeric()
                        ->default(0)
                        ->prefixIcon('heroicon-o-hashtag')
                        ->helperText('Failed login counter'),

                    Forms\Components\DateTimePicker::make('locked_at')
                        ->disabled()
                        ->native(false)
                        ->timezone('Asia/Jakarta')
                        ->helperText('Account locked timestamp'),
                ])
                ->columns(3)
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF')
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn($record) => $record->username ? '@' . $record->username : null)
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->formatStateUsing(fn($record) => $record->phone_number ? $record->phone_country_code . ' ' . $record->phone_number : 'N/A')
                    ->icon('heroicon-o-phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'success' => 'admin',
                        'primary' => 'developer',
                        'gray' => 'user',
                    ])
                    ->icons([
                        'heroicon-o-shield-check' => 'admin',
                        'heroicon-o-code-bracket' => 'developer',
                        'heroicon-o-user' => 'user',
                    ]),

                Tables\Columns\TextColumn::make('account_status')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'blocked',
                        'warning' => 'suspended',
                        'gray' => 'terminated',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'active',
                        'heroicon-o-lock-closed' => 'blocked',
                        'heroicon-o-exclamation-circle' => 'suspended',
                        'heroicon-o-x-circle' => 'terminated',
                    ]),

                Tables\Columns\TextColumn::make('failed_login_attempts')
                    ->label('Failed Attempts')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn($state): string => match (true) {
                        $state >= 5 => 'danger',
                        $state >= 3 => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_login_ip')
                    ->label('Last Login IP')
                    ->icon('heroicon-o-globe-alt')
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->since()
                    ->icon('heroicon-o-clock')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('locked_at')
                    ->dateTime('d M Y H:i')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Administrator',
                        'developer' => 'Developer',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('account_status')
                    ->options([
                        'active' => 'Active',
                        'blocked' => 'Blocked',
                        'suspended' => 'Suspended',
                        'terminated' => 'Terminated',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('locked')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('locked_at'))
                    ->toggle(),

                Tables\Filters\Filter::make('last_seen_at')
                    ->form([
                        Forms\Components\DatePicker::make('seen_from')
                            ->label('Active from')
                            ->native(false),
                        Forms\Components\DatePicker::make('seen_until')
                            ->label('Active until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['seen_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('last_seen_at', '>=', $date),
                            )
                            ->when(
                                $data['seen_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('last_seen_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['seen_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Active from ' . Carbon::parse($data['seen_from'])->toFormattedDateString())
                                ->removeField('seen_from');
                        }
                        if ($data['seen_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Active until ' . Carbon::parse($data['seen_until'])->toFormattedDateString())
                                ->removeField('seen_until');
                        }

                        return $indicators;
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->icon('heroicon-o-eye'),

                    Tables\Actions\EditAction::make()
                        ->icon('heroicon-o-pencil'),

                    Tables\Actions\Action::make('block')
                        ->label('Block Account')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(fn($record) => $record->account_status !== 'blocked' && !$record->isBlocked())
                        ->requiresConfirmation()
                        ->modalHeading('Block User Account')
                        ->modalDescription('This will immediately block the user from accessing the system.')
                        ->modalIcon('heroicon-o-exclamation-triangle')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Blocking Reason')
                                ->required()
                                ->rows(3)
                                ->placeholder('Enter the reason for blocking this account'),
                            Forms\Components\DateTimePicker::make('until')
                                ->label('Block Until (Optional)')
                                ->native(false)
                                ->timezone('Asia/Jakarta')
                                ->helperText('Leave empty for permanent block'),
                        ])
                        ->action(function (User $record, array $data): void {
                            $record->block(
                                reason: $data['reason'],
                                until: $data['until'] ?? null,
                                blockedBy: Auth::id()
                            );

                            Notification::make()
                                ->success()
                                ->title('Account Blocked')
                                ->body("{$record->name}'s account has been blocked successfully.")
                                ->send();
                        }),

                    Tables\Actions\Action::make('unblock')
                        ->label('Unblock Account')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->visible(fn($record) => $record->account_status === 'blocked' || $record->isBlocked())
                        ->requiresConfirmation()
                        ->modalHeading('Unblock User Account')
                        ->modalDescription('This will restore access to the user account.')
                        ->action(function (User $record): void {
                            $record->unblock();

                            Notification::make()
                                ->success()
                                ->title('Account Unblocked')
                                ->body("{$record->name}'s account has been unblocked.")
                                ->send();
                        }),

                    Tables\Actions\Action::make('unlock')
                        ->label('Unlock Account')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->visible(fn($record) => $record->isLocked())
                        ->requiresConfirmation()
                        ->action(function (User $record): void {
                            $record->unlock();

                            Notification::make()
                                ->success()
                                ->title('Account Unlocked')
                                ->body("{$record->name}'s account has been unlocked and failed attempts reset.")
                                ->send();
                        }),

                    Tables\Actions\Action::make('reset_password')
                        ->label('Reset Password')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Reset User Password')
                        ->modalDescription('Generate a new password for this user.')
                        ->form([
                            Forms\Components\TextInput::make('new_password')
                                ->label('New Password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->revealable()
                                ->placeholder('Enter new password'),
                            Forms\Components\TextInput::make('confirm_password')
                                ->label('Confirm Password')
                                ->password()
                                ->same('new_password')
                                ->required()
                                ->revealable(),
                        ])
                        ->action(function (User $record, array $data): void {
                            $record->update([
                                'password' => Hash::make($data['new_password']),
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Password Reset')
                                ->body("{$record->name}'s password has been reset successfully.")
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->icon('heroicon-o-trash'),

                    Tables\Actions\RestoreAction::make()
                        ->icon('heroicon-o-arrow-uturn-left'),

                    Tables\Actions\ForceDeleteAction::make()
                        ->icon('heroicon-o-trash'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('block_selected')
                        ->label('Block Selected')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Block Selected Users')
                        ->modalDescription('This will block all selected users from accessing the system.')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Blocking Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                $record->block(
                                    reason: $data['reason'],
                                    blockedBy: Auth::id()
                                );
                            }

                            Notification::make()
                                ->success()
                                ->title('Users Blocked')
                                ->body(count($records) . ' user(s) have been blocked.')
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\RestoreBulkAction::make(),

                    Tables\Actions\ForceDeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(function ($records) {
                            // Export logic here (CSV/Excel)
                            Notification::make()
                                ->info()
                                ->title('Export Started')
                                ->body('Selected users are being exported.')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No users found')
            ->emptyStateDescription('Start by creating a new user account.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LoginActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('account_status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
