<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SparkCategory extends Model {
    protected $fillable = ['name','slug','sort_order'];
    public function sparks(): HasMany { return $this->hasMany(Spark::class); }
}
