<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo cuti seseorang pada satu tahun.
 *
 * DISIMPAN, TIDAK DIHITUNG SAAT DIBACA. Hak cuti bergantung pada masa
 * kerja dan pada kebijakan yang berlaku saat itu; dihitung ulang tiap
 * kali layarnya dibuka, saldo tahun lalu ikut berubah begitu
 * kebijakannya diubah — dan sengketa upah bertumpu pada angka yang
 * tidak dapat ditunjukkan lagi asalnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class SaldoCuti extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'hr_saldo_cuti';

    protected $guarded = ['id'];

    protected $attributes = ['hak' => 0, 'carry_over' => 0, 'terpakai' => 0];

    protected function casts(): array
    {
        return [
            'tahun'      => 'integer',
            'hak'        => 'integer',
            'carry_over' => 'integer',
            'terpakai'   => 'integer',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function jenis(): BelongsTo   { return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id'); }

    /** Hak tahun berjalan ditambah bawaan tahun lalu, dikurangi yang terpakai. */
    public function sisa(): int
    {
        return $this->hak + $this->carry_over - $this->terpakai;
    }
}
