<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionTemplate extends Model
{
    protected $fillable = ['nama','jenis','kategori','deskripsi','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function items(): HasMany       { return $this->hasMany(InspectionTemplateItem::class, 'template_id')->orderBy('order_index'); }
    public function inspections(): HasMany { return $this->hasMany(Inspection::class, 'template_id'); }
}
