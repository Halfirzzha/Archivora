<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoginActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'loginActivities';

    protected static ?string $title = 'Login Activity History';

    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('event')
                    ->disabled()
                    ->maxLength(50),
                Forms\Components\TextInput::make('ip_address')
                    ->disabled()
                    ->maxLength(45),
                Forms\Components\Textarea::make('user_agent')
                    ->disabled()
                    ->rows(2),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event')
            ->columns([
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'login_success' => 'success',
                        'login_failure' => 'danger',
                        'logout' => 'gray',
                        'account_locked' => 'warning',
                        'account_registered' => 'info',
                        default => 'gray',
                    })
                    ->icons([
                        'heroicon-o-check-circle' => 'login_success',
                        'heroicon-o-x-circle' => 'login_failure',
                        'heroicon-o-arrow-right-on-rectangle' => 'logout',
                        'heroicon-o-lock-closed' => 'account_locked',
                        'heroicon-o-user-plus' => 'account_registered',
                    ])
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->icon('heroicon-o-globe-alt')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Unknown'),

                Tables\Columns\TextColumn::make('user_agent')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y H:i:s')
                    ->timezone('Asia/Jakarta')
                    ->sortable()
                    ->since()
                    ->icon('heroicon-o-clock'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'login_success' => 'Login Success',
                        'login_failure' => 'Login Failure',
                        'logout' => 'Logout',
                        'account_locked' => 'Account Locked',
                        'account_registered' => 'Account Registered',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('logged_from')
                            ->label('From Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('logged_until')
                            ->label('Until Date')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['logged_from'],
                                fn($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['logged_until'],
                                fn($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                // Read-only table, no create action
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Clear Selected Logs')
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No login activities recorded')
            ->emptyStateDescription('Login activities will appear here once the user logs in.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
