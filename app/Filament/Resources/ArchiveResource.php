<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArchiveResource\Pages;
use App\Models\Archive;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Filament\Support\Enums\FontWeight;

class ArchiveResource extends Resource
{
    protected static ?string $model = Archive::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Archives';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Document Information')->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpan(2),
                Forms\Components\TextInput::make('document_number')->unique(ignoreRecord: true)->maxLength(255),
                Forms\Components\Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
                Forms\Components\Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->default('draft')->required(),
                Forms\Components\Toggle::make('is_confidential')->label('Confidential Document')->default(false),
            ])->columns(2),

            Forms\Components\Section::make('File Upload')->schema([
                Forms\Components\FileUpload::make('file_path')->label('Document File')->disk('minio')->directory('archives')->visibility('private')->preserveFilenames()->maxSize(102400)->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'])->required(fn($context) => $context === 'create')->columnSpanFull(),
            ])->hidden(fn($context) => $context === 'edit'),

            Forms\Components\Section::make('Archive Metadata')->schema([
                Forms\Components\DatePicker::make('archive_date')->default(now()),
                Forms\Components\TextInput::make('retention_period')->maxLength(50),
                Forms\Components\DateTimePicker::make('retention_until'),
                Forms\Components\KeyValue::make('metadata')->columnSpanFull(),
            ])->columns(3)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('document_number')->searchable()->sortable()->copyable()->weight(FontWeight::Bold),
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(50),
            Tables\Columns\TextColumn::make('category.name')->badge()->color('info'),
            Tables\Columns\TextColumn::make('original_filename')->limit(30),
            Tables\Columns\TextColumn::make('file_size')->formatStateUsing(fn($state) => self::formatBytes($state))->sortable(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn(string $state): string => match ($state) {
                'draft' => 'gray',
                'published' => 'success',
                'archived' => 'warning',
                default => 'gray'
            }),
            Tables\Columns\IconColumn::make('is_confidential')->boolean(),
            Tables\Columns\TextColumn::make('access_count')->sortable()->alignCenter(),
            Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('category')->relationship('category', 'name'),
            Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
            Tables\Filters\TernaryFilter::make('is_confidential'),
            Tables\Filters\TrashedFilter::make(),
        ])->actions([
            Tables\Actions\Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(function (Archive $record) {
                    /** @var \Illuminate\Contracts\Auth\Guard $auth */
                    $auth = auth();
                    /** @var \App\Models\User|null $user */
                    $user = $auth->user();
                    return $auth->check() && $user && $user->can('download', $record);
                })
                ->action(function (Archive $record) {
                    // Authorization already checked by visible(), but double-check for security
                    /** @var \App\Models\User|null $user - Laravel auth()->user() */
                    // @phpstan-ignore-next-line - auth() is Laravel global helper
                    $user = auth('web')->user();
                    if (!$user || !$user->can('download', $record)) {
                        abort(403, 'You are not authorized to download this file.');
                    }

                    $record->incrementAccessCount();
                    return response()->streamDownload(function () use ($record) {
                        echo Storage::disk('minio')->get($record->file_path);
                    }, $record->original_filename, [
                        'Content-Type' => $record->mime_type,
                        'Content-Length' => $record->file_size,
                    ]);
                }),
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArchives::route('/'),
            'create' => Pages\CreateArchive::route('/create'),
            'edit' => Pages\EditArchive::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
