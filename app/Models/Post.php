<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'author_id',
        'category',
        'featured_image',
        'is_published',
        'published_at',
        'view_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'view_count' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    /**
     * Get the author of the post.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope a query to only include published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '>', now());
    }

    public function isPubliclyVisible(): bool
    {
        return (bool) $this->is_published
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function publicationStatus(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }

        return $this->published_at?->isFuture() ? 'scheduled' : 'published';
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Increment view count.
     */
    public function incrementViews()
    {
        $this->increment('view_count');
    }

    /**
     * Get excerpt or truncated content.
     */
    public function getExcerptAttribute($value)
    {
        if ($value) {
            return $value;
        }
        return Str::limit(strip_tags($this->content), 150);
    }
}
