<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Satu anggota tim investigasi. */
class Tim extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_tim';

    protected $fillable = ['investigasi_id', 'user_id', 'peran_tim'];

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
    public function user()        { return $this->belongsTo(User::class); }
}
