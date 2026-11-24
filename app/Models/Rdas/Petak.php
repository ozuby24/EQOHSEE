<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/** Petak tanam dengan poligon peta dan salinan hasil petak ukur terakhir. */
#[ScopedBy(MilikPerusahaan::class)]
class Petak extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_petak';

    protected $fillable = ['company_id', 'blok_id', 'kode', 'luas_ha', 'kelerengan', 'poligon', 'lat', 'lng', 'bibit_tanam', 'bibit_sulam', 'persen_tumbuh', 'sehat_ha', 'status_tumbuh', 'dipantau_at'];

    protected function casts(): array
    {
        return ['luas_ha' => 'float', 'poligon' => 'array', 'lat' => 'float', 'lng' => 'float', 'bibit_tanam' => 'integer', 'bibit_sulam' => 'integer', 'persen_tumbuh' => 'float', 'sehat_ha' => 'float', 'dipantau_at' => 'date'];
    }

    public function blok(): BelongsTo { return $this->belongsTo(Blok::class, 'blok_id'); }
    public function pantau(): HasMany { return $this->hasMany(Pantau::class, 'petak_id'); }
}
