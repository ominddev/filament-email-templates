<?php

namespace Visualbuilder\EmailTemplates\Resources\EmailTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\View\View;
use Visualbuilder\EmailTemplates\Models\EmailTemplate;
use Visualbuilder\EmailTemplates\Resources\EmailTemplateResource;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('back')->label(__('Back'))
            ->url(EmailTemplateResource::getUrl())
            ,
            Actions\ViewAction::make()->label(__('Preview'))->modalContent(fn (EmailTemplate $record): View => view(
                'vb-email-templates::forms.components.iframe',
                ['record' => $record],
            ))->form(null),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make()
                ->before(function (EmailTemplate $record, EmailTemplateResource $emailTemplateResource) {
                    $emailTemplateResource->handleLogoDelete($record->logo);
                }),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['logo_type'] = 'browse_another';

        if(!is_null($data['logo']) && Str::isUrl($data['logo'])) {
            $data['logo_type'] = 'paste_url';
            $data['logo_url'] = $data['logo'];
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $emailTemplateResource = new EmailTemplateResource();
        $sortedData = $emailTemplateResource->handleLogo($data);

        // deleting previous logo
        if(isset($sortedData['logo'])){
            if ($record->logo != $sortedData['logo']) {
                $emailTemplateResource->handleLogoDelete($record->logo);
            }
        }
        else{
            unset($sortedData['logo_url']);
        }

        $record->update($sortedData);

        return $record;
    }
}
