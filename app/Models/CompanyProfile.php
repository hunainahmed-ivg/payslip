<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = [
        'company_name', 'tax_id', 'registration_number', 'address',
        'header_image_path', 'footer_image_path',
        'template_type', 'custom_html',
        'primary_color', 'accent_color', 'font_family', 'page_margin',
    ];

    protected $appends = ['header_image_url', 'footer_image_url'];

    public function getHeaderImageUrlAttribute(): ?string
    {
        return $this->header_image_path ? Storage::url($this->header_image_path) : null;
    }

    public function getFooterImageUrlAttribute(): ?string
    {
        return $this->footer_image_path ? Storage::url($this->footer_image_path) : null;
    }
}