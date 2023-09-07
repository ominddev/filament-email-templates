<?php

namespace Visualbuilder\EmailTemplates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Visualbuilder\EmailTemplates\Database\Factories\EmailTemplateFactory;
use Visualbuilder\EmailTemplates\Traits\TokenHelper;

/**
 * @property int $id
 * @property string $key
 * @property string $from
 * @property string $name
 * @property string $view
 * @property string $send_to
 * @property object $cc
 * @property object $bcc
 * @property string $subject
 * @property string $title
 * @property string $preheader
 * @property string $language
 * @property string $content
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 */
class EmailTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TokenHelper;

    /**
     * @var array
     */
    protected $fillable = [
        'from',
        'key',
        'name',
        'view',
        'subject',
        'title',
        'preheader',
        'content',
        'language',
        'send_to',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    /**
     * @var string[]
     */
    protected $dates = ['deleted_at'];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['theme'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTableFromConfig();
    }


    public function setTableFromConfig()
    {
        $this->table = config('email-templates.table_name');
    }


    public static function findEmailByKey($key, $language = null)
    {
        $cacheKey = "email_by_key_{$key}_{$language}";
        //For multi site domains this key will need to include the site_id
        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($key, $language) {
            return self::query()
                ->language($language ?? config('email-templates.default_locale'))
                ->where("key", $key)
                ->firstOrFail();
        });
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public static function getSendToSelectOptions()
    {
        return collect(config('emailTemplate.recipients'));
    }

    /**
     * @return EmailTemplateFactory
     */
    protected static function newFactory()
    {
        return EmailTemplateFactory::new();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name ?? class_basename($this);
    }

    /**
     * Get the assigned theme or the default
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function theme()
    {
        return $this->belongsTo(EmailTemplateTheme::class, 'vb_email_templates_themes_id')->withDefault(function ($model) {
            return EmailTemplateTheme::where('is_default', true)->first();
        });
    }

    /**
     * Gets base64 encoded content - to add to an iframe
     *
     * @return string
     */
    public function getBase64EmailPreviewData()
    {
        /**
         * Iframes normally use src attribute to load content from a url
         * This means an extra http request
         *  Below method includes the content directly as base64 encoded
         */

        $data = $this->getEmailPreviewData();
        $content = view($this->view_path, ['data' => $data])->render();

        return base64_encode($content);
    }

    /**
     * @return array
     */
    public function getEmailPreviewData()
    {
        $model = self::createEmailPreviewData();

        return [
            'user'          => $model->user,
            'content'       => $this->replaceTokens($this->content, $model),
            'subject'       => $this->replaceTokens($this->subject, $model),
            'preHeaderText' => $this->replaceTokens($this->preheader, $model),
            'title'         => $this->replaceTokens($this->title, $model),
            'theme'         => $this->theme->colours,
        ];
    }

    /**
     * @return object
     */
    public static function createEmailPreviewData()
    {
        $model = (object) [];

        $userModel = config('email-templates.recipients')[0];
        //Setup some data for previewing email template
        $model->user = $userModel::first();

        $model->tokenUrl = URL::to('/');
        $model->verificationUrl = URL::to('/');
        $model->expiresAt = now();
        /* Not used in preview but need to add something */
        $model->plainText = Str::random(32);

        return $model;
    }

    /**
     * Efficient method to return requested template locale or default language template in one query
     *
     * @param  Builder  $query
     * @param $language
     *
     * @return Builder
     */
    public function scopeLanguage(Builder $query, $language)
    {
        $languages = [$language, config('email-templates.default_locale')];

        return $query->whereIn('language', $languages)
            ->orderBy('language');
        //  ->orderByRaw('field(language, ?, ?)', $languages); // order by field is not present in sqlite
    }

    /**
     * @return Attribute
     */
    public function viewPath(): Attribute
    {
        return new Attribute(
            get: fn() => config('email-templates.template_view_path').'.'.$this->view
        );
    }

    /**
     * @return bool
     */
    public function getMailableExistsAttribute(): bool
    {
        $className = Str::studly($this->key);
        $filePath = app_path(config('email-templates.mailable_directory')."/{$className}.php");

        return File::exists($filePath);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getMailableClass()
    {
        $className = Str::studly($this->key);
        $directory = str_replace('/', '\\', config('email-templates.mailable_directory', 'Mail/Visualbuilder/EmailTemplates')); // Convert slashes to namespace format
        $fullClassName = "\\App\\{$directory}\\{$className}";

        if (!class_exists($fullClassName)) {
            throw new \Exception("Mailable class {$fullClassName} does not exist.");
        }

        return $fullClassName;
    }
}
