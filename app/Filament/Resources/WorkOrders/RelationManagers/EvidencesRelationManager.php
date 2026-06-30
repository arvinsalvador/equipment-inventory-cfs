<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EvidencesRelationManager extends RelationManager
{
    protected static string $relationship = 'evidences';

    protected static ?string $title = 'Evidence';

    protected static bool $isReadOnly = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('evidence_type')
                    ->label('Evidence type')
                    ->options(WorkOrderEvidence::evidenceTypeOptions())
                    ->required()
                    ->rule(Rule::in(WorkOrderEvidence::EVIDENCE_TYPES)),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->directory('work-orders/evidence')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->imagePreviewHeight('160')
                    ->openable()
                    ->downloadable()
                    ->required(),
                Textarea::make('caption')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->height(64)
                    ->square(),
                TextColumn::make('evidence_type')
                    ->label('Evidence type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('caption')
                    ->placeholder('None')
                    ->limit(50),
                TextColumn::make('uploadedBy.name')
                    ->label('Uploaded by')
                    ->placeholder('Unknown'),
                TextColumn::make('uploaded_at')
                    ->label('Uploaded date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Upload Evidence')
                    ->authorize(fn (): bool => $this->canUploadEvidence())
                    ->mutateDataUsing(function (array $data): array {
                        /** @var WorkOrder $workOrder */
                        $workOrder = $this->getOwnerRecord();

                        $data['work_order_id'] = $workOrder->id;
                        $data['equipment_id'] = $workOrder->equipment_id;
                        $data['uploaded_by'] = auth()->id();
                        $data['uploaded_at'] = now();

                        return $data;
                    }),
            ]);
    }

    protected function canUploadEvidence(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->getOwnerRecord();

        if (auth()->user()?->can('work-orders.assign')) {
            return ! $workOrder->isCancelled();
        }

        return auth()->user()?->can('uploadEvidence', $workOrder) ?? false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }
}
