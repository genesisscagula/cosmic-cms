<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    // Gi-allow nato ang 'global_header' nga masulod sa mass assignment保護
    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'domain',
        'preview_slug',
        'industry',
        'location',
        'business_description',
        'contact_email',
        'contact_phone',
        'timezone',
        'locale',
        'settings',
        'api_token',
        'deployment_secret',
        'deployment_verified_at',
        'last_deployed_at',
        'last_preview_deployed_at',
        'deployment_error',
        'preview_deployment_error',
        'theme_settings',
        'global_header',
        'global_footer',
        'published_theme_settings',
        'published_global_header',
        'published_global_footer',
    ];

    // Gi-automatic cast nato ang JSON string ngadto sa PHP/React Array packet
    protected $casts = [
        'theme_settings' => 'array',
        'settings' => 'array',
        'global_header' => 'array', 
        'global_footer' => 'array',
        'published_theme_settings' => 'array',
        'published_global_header' => 'array',
        'published_global_footer' => 'array',
        'deployment_secret' => 'encrypted',
        'deployment_verified_at' => 'datetime',
        'last_deployed_at' => 'datetime',
        'last_preview_deployed_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'website_user')
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function mediaPack()
    {
        return $this->hasOne(MediaPack::class);
    }

    public function mediaFolders()
    {
        return $this->hasMany(MediaFolder::class);
    }

    public function mediaAssets()
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function globalElements()
    {
        return $this->hasMany(GlobalElements::class);
    }

    public function salesEvents()
    {
        return $this->hasMany(SalesEvent::class);
    }

    public function contactSubmissions()
    {
        return $this->hasMany(ContactSubmission::class);
    }

    public function previewLinks()
    {
        return $this->hasMany(WebsitePreviewLink::class);
    }

    public function ownershipTransfers()
    {
        return $this->hasMany(WebsiteOwnershipTransfer::class);
    }

    public function analyticsDaily()
    {
        return $this->hasMany(WebsiteAnalyticsDaily::class);
    }

    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class);
    }

    public function contentTypes()
    {
        return $this->hasMany(ContentType::class)->orderBy('sort_order')->orderBy('name');
    }

    public function contentEntries()
    {
        return $this->hasMany(ContentEntry::class);
    }

    public function commerceSetting()
    {
        return $this->hasOne(WebsiteCommerceSetting::class);
    }

    public function commerceProducts()
    {
        return $this->hasMany(CommerceProduct::class);
    }

    public function commerceProductCategories()
    {
        return $this->hasMany(CommerceProductCategory::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function commerceShippingZones()
    {
        return $this->hasMany(CommerceShippingZone::class)
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function commerceTaxRules()
    {
        return $this->hasMany(CommerceTaxRule::class)
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function commerceOrders()
    {
        return $this->hasMany(CommerceOrder::class)->latest();
    }

    public function commerceCoupons()
    {
        return $this->hasMany(CommerceCoupon::class);
    }
}
