<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Spark extends Model {
    protected $fillable = ['spark_category_id','name','slug','description','thumbnail','layout_json','credits','is_featured','is_published','sort_order'];
    protected function casts(): array { return ['layout_json'=>'array','credits'=>'integer','is_featured'=>'boolean','is_published'=>'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(SparkCategory::class, 'spark_category_id'); }
    public function owners(): BelongsToMany { return $this->belongsToMany(User::class, 'user_sparks')->withPivot(['credits_paid','unlocked_at'])->withTimestamps(); }
}
