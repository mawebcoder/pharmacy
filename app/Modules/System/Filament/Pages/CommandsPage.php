<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Pages;

use App\Modules\System\Enums\CommandRunStatus;
use App\Modules\System\Jobs\RunAdminCommand;
use App\Modules\System\Models\CommandRun;
use App\Modules\System\Support\ArtisanCommandLine;
use App\Modules\System\SystemServiceProvider;
use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * «فرمان‌ها» (owner, 2026-11-28), for super admins only: a box to run any Artisan command (ArtisanCommandLine), in
 * the background as the deploy user (RunAdminCommand), and the list of runs with their output. The list refreshes
 * itself while a run is waiting or running.
 *
 * @property-read Schema $form
 */
final class CommandsPage extends Page implements HasTable
{
    use InteractsWithTable;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'فرمان‌ها';

    protected static ?string $title = 'فرمان‌ها';

    protected static ?string $slug = 'commands';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can(SystemServiceProvider::RUN_COMMANDS) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('command')
                    ->label('فرمان')
                    ->prefix('php artisan')
                    // Written left to right like a terminal, so "php artisan" sits on the left.
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('media:migrate-to-s3 --dry-run')
                    ->helperText('در پس‌زمینه با کاربر سرور اجرا می‌شود و نتیجه در فهرست زیر می‌آید. فرمان‌هایی که تمام نمی‌شوند (مثل tinker) یا پایگاه داده را پاک می‌کنند (مثل migrate:fresh) اجرا نمی‌شوند.')
                    ->required()
                    ->maxLength(1000)
                    ->autocomplete(false)
                    // Suggestions as you type, without the commands that are refused.
                    ->datalist(fn (): array => array_keys(ArtisanCommandLine::available()))
                    ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false'])
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $refusal = ArtisanCommandLine::refusal((string) $value);

                        if ($refusal !== null) {
                            $fail($refusal);
                        }
                    }]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('run')
                ->footer([
                    Actions::make([Action::make('run')->label('اجرا')->icon(Heroicon::OutlinedPlay)->submit('run')])->key('form-actions'),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function run(): void
    {
        $line = ArtisanCommandLine::normalize((string) $this->form->getState()['command']);
        $admin = auth('admin')->user();

        $run = (new CommandRun)
            ->setCommand($line)
            ->setStatus(CommandRunStatus::Queued)
            ->setAdminId($admin === null ? null : (int) $admin->getAuthIdentifier())
            ->setAdminName($admin === null ? null : (string) $admin->getAttribute('name'));
        $run->save();
        RunAdminCommand::dispatch($run->getId());

        $this->form->fill();
        Notification::make()->success()->title('فرمان در صف اجرا قرار گرفت.')->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => CommandRun::query())
            ->defaultSort(CommandRun::COLUMN_ID, 'desc')
            ->poll(fn (): ?string => CommandRun::query()->whereIn(CommandRun::COLUMN_STATUS, [CommandRunStatus::Queued, CommandRunStatus::Running])->exists() ? '3s' : null)
            ->columns([
                TextColumn::make(CommandRun::COLUMN_COMMAND)
                    ->label('فرمان')
                    ->fontFamily('mono')
                    ->extraAttributes(['dir' => 'ltr'])
                    ->wrap(),
                TextColumn::make(CommandRun::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (CommandRunStatus $state): string => $state->label())
                    ->color(fn (CommandRunStatus $state): string => $state->color()),
                TextColumn::make(CommandRun::COLUMN_EXIT_CODE)->label('کد خروج')->placeholder('—'),
                TextColumn::make(CommandRun::COLUMN_SYSTEM_USER)->label('کاربر سرور')->placeholder('—'),
                TextColumn::make(CommandRun::COLUMN_ADMIN_NAME)->label('اجرا توسط')->placeholder('—'),
                TextColumn::make(CommandRun::COLUMN_CREATED_AT)
                    ->label('زمان')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->description(fn (CommandRun $record): ?string => $record->getStartedAt() !== null && $record->getFinishedAt() !== null
                        ? 'مدت: '.$record->getStartedAt()->diffForHumans($record->getFinishedAt(), CarbonInterface::DIFF_ABSOLUTE)
                        : null),
            ])
            ->recordActions([
                Action::make('output')
                    ->label('خروجی')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->modalHeading(fn (CommandRun $record): string => 'php artisan '.$record->getCommand())
                    ->modalContent(fn (CommandRun $record): HtmlString => new HtmlString(
                        '<pre dir="ltr" style="max-height:60vh;overflow:auto;white-space:pre-wrap;font-size:12px;line-height:1.6">'
                        .e($record->getOutput() ?? ($record->getStatus()->isActive() ? 'هنوز تمام نشده است…' : '—'))
                        .'</pre>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),
            ])
            ->emptyStateHeading('هنوز فرمانی اجرا نشده است');
    }
}
