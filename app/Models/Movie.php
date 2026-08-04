<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Movie extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($movie) {
            if (empty($movie->slug)) {
                $movie->slug = static::generateUniqueSlug($movie->title);
            }
        });

        static::updating(function ($movie) {
            if ($movie->isDirty('title') && empty($movie->slug)) {
                $movie->slug = static::generateUniqueSlug($movie->title);
            }
        });
    }

    protected static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected $fillable = [
        'title',
        'slug',
        'original_title',
        'description',
        'poster_url',
        'poster_path',
        'banner_path',
        'trailer_url',
        'duration',
        'release_date',
        'end_date',
        'age_rating',
        'surcharge',
        'director',
        'cast',
        'backdrops',
        'status',
        'is_hidden',
        'manual_override_status',
        'is_hot',
    ];

    protected $hidden = [
        'poster_path',
        'banner_path',
    ];

    protected $appends = [
        'poster_display_url',
        'banner_display_url',
    ];

    /**
     * URL hiển thị poster: ưu tiên file upload, fallback sang poster_url cũ.
     */
    public function getPosterDisplayUrlAttribute(): ?string
    {
        if ($this->poster_path) {
            return asset('storage/' . $this->poster_path);
        }
        if ($this->poster_url) {
            return $this->poster_url;
        }
        return 'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?auto=format&fit=crop&w=600&q=80';
    }

    /**
     * URL hiển thị banner: ưu tiên file upload.
     */
    public function getBannerDisplayUrlAttribute(): ?string
    {
        if ($this->banner_path) {
            return asset('storage/' . $this->banner_path);
        }
        return null;
    }

    protected $casts = [
        'duration' => 'integer',
        'release_date' => 'date',
        'end_date' => 'date',
        'surcharge' => 'decimal:2',
        'backdrops' => 'json',
        'status' => 'boolean',
        'is_hidden' => 'boolean',
        'manual_override_status' => 'integer',
        'is_hot' => 'boolean',
    ];

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'categories_movies')
            ->withTimestamps();
    }

    public function formats(): BelongsToMany
    {
        return $this->belongsToMany(Format::class, 'movie_format')
            ->withTimestamps();
    }

    public function subtitles(): BelongsToMany
    {
        return $this->belongsToMany(Subtitle::class, 'movie_subtitle')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)
            ->where('is_hidden', 0);
    }

    public function scopeNowShowing($query)
    {
        return $query->active()
            ->where('release_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeUpcoming($query)
    {
        return $query->active()
            ->where('release_date', '>', now());
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        });
    }
}
