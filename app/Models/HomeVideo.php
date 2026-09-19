<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeVideo extends Model
{
    /** Folder under storage/app/public the files are uploaded to. */
    public const DIR = 'home-video';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'priority' => 'integer',
    ];

    protected $appends = ['video_src', 'poster_src'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function getVideoSrcAttribute(): ?string
    {
        return $this->video ? asset('storage/'.self::DIR.'/'.$this->video) : null;
    }

    public function getPosterSrcAttribute(): ?string
    {
        return $this->poster ? asset('storage/'.self::DIR.'/'.$this->poster) : null;
    }

    /**
     * The link only if it is safe to put in an href: a site-relative path or
     * an http(s) URL. Anything else (javascript:, data:) is dropped.
     */
    public function safeLink(): ?string
    {
        $url = trim((string) $this->link_url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) ? $url : null;
    }
}
