<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil'),
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Profile')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Infolists\Components\ImageEntry::make('avatar')
                            ->circular()
                            ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF')
                            ->size(80),
                        Infolists\Components\TextEntry::make('name')
                            ->icon('heroicon-o-user')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('email')
                            ->icon('heroicon-o-envelope')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('username')
                            ->icon('heroicon-o-at-symbol')
                            ->placeholder('Not set')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Phone Number')
                            ->icon('heroicon-o-phone')
                            ->formatStateUsing(fn($record) => $record->phone_number ? $record->phone_country_code . ' ' . $record->phone_number : 'Not set'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Authorization & Security')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Infolists\Components\TextEntry::make('role')
                            ->badge()
                            ->color(fn($state): string => match ($state) {
                                'admin' => 'success',
                                'developer' => 'primary',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('account_status')
                            ->badge()
                            ->color(fn($state): string => match ($state) {
                                'active' => 'success',
                                'blocked' => 'danger',
                                'suspended' => 'warning',
                                'terminated' => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('failed_login_attempts')
                            ->label('Failed Login Attempts')
                            ->badge()
                            ->color(fn($state): string => match (true) {
                                $state >= 5 => 'danger',
                                $state >= 3 => 'warning',
                                default => 'gray',
                            }),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Account Management')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Infolists\Components\TextEntry::make('blocked_reason')
                            ->placeholder('Not blocked')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('blocked_until')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('blockedBy.name')
                            ->label('Blocked By')
                            ->icon('heroicon-o-user')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('locked_at')
                            ->label('Locked At')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->placeholder('Not locked')
                            ->color('danger'),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record->blocked_reason || $record->locked_at),

                Infolists\Components\Section::make('Security Tracking')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_login_ip')
                            ->icon('heroicon-o-globe-alt')
                            ->placeholder('Never logged in')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('last_seen_ip')
                            ->icon('heroicon-o-eye')
                            ->placeholder('Never seen')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('last_failed_login_ip')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->placeholder('No failed attempts')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('last_seen_at')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->since()
                            ->placeholder('Never'),
                        Infolists\Components\TextEntry::make('last_failed_login_at')
                            ->label('Last Failed Login')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->placeholder('Never'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Infolists\Components\Section::make('Account Metadata')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->since(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->since(),
                        Infolists\Components\TextEntry::make('deleted_at')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->placeholder('Active account')
                            ->color('danger'),
                        Infolists\Components\TextEntry::make('email_verified_at')
                            ->dateTime('d M Y H:i')
                            ->timezone('Asia/Jakarta')
                            ->placeholder('Not verified'),
                    ])
                    ->columns(4)
                    ->collapsible(),
            ]);
    }
}
