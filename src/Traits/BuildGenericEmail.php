<?php

namespace Visualbuilder\EmailTemplates\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Visualbuilder\EmailTemplates\Models\EmailTemplate;
use Visualbuilder\EmailTemplates\Facades\TokenHelper;

trait BuildGenericEmail
{

    public $emailTemplate;
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->emailTemplate =  EmailTemplate::findEmailByKey($this->template, App::currentLocale());

        if($this->attachment ?? false) {
            $this->attach(
                $this->attachment->getPath(),
                [
                'as' => $this->attachment->filename,
                'mime' => $this->attachment->mime_type,
            ]
            );
        }


        // preparing logo
        $logo = $this->emailTemplate->resolveLogoUrl($this->emailTemplate->logo);

        $data = [
            'content' => TokenHelper::replace($this->emailTemplate->content, $this),
            'preHeaderText' => TokenHelper::replace($this->emailTemplate->preheader, $this),
            'title' => TokenHelper::replace($this->emailTemplate->title, $this),
            'theme' => $this->emailTemplate->theme->colours,
            'logo'  => $logo,
        ];

        return $this->from($this->emailTemplate->from['email'], $this->emailTemplate->from['name'])
            ->view($this->emailTemplate->view_path)
            ->subject(TokenHelper::replace($this->emailTemplate->subject, $this))
            ->to($this->sendTo)
            ->with(['data' => $data]);
    }
}
