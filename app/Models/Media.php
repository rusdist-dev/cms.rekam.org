<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Media extends TenantModel
{
    use HasFactory, HasTranslations;

    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'filename', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by',
    ];

    protected $casts = [
        'alt' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected array $translatable = ['alt'];

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
