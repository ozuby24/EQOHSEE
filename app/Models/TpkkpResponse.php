<?php
namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class TpkkpResponse extends Model
{
    protected $table = 'tpkkp_responses';
    protected $fillable = ['company_id','ext_id','cat','nrp','jabatan','dept','perusahaan','answers','ts'];

    protected function casts(): array { return ['answers'=>'array','ts'=>'datetime']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
