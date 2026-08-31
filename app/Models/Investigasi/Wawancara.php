<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Satu wawancara narasumber. */
class Wawancara extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_wawancara';

    public const PERAN = [
        'korban'                => 'Korban',
        'saksi_langsung'        => 'Saksi Langsung',
        'saksi_tidak_langsung'  => 'Saksi Tidak Langsung',
        'pengawas'              => 'Pengawas / PIC',
        'manajemen'             => 'Manajemen',
    ];

    protected $fillable = [
        'investigasi_id', 'narasumber', 'jabatan', 'peran',
        'tanggal', 'tempat', 'catatan', 'pewawancara_id',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
    public function pewawancara() { return $this->belongsTo(User::class, 'pewawancara_id'); }

    public function jawaban()
    {
        return $this->hasMany(WawancaraJawaban::class, 'wawancara_id')->orderBy('id');
    }
}
