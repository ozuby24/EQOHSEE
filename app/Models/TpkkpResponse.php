<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TpkkpResponse extends Model
{
    protected $table = 'tpkkp_responses';
    protected $fillable = ['company_id','ext_id','cat','nrp','jabatan','dept','perusahaan','answers','ts'];

    protected function casts(): array { return ['answers'=>'array','ts'=>'datetime']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
