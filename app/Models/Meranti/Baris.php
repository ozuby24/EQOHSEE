<?php

namespace App\Models\Meranti;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use App\Support\Meranti\Katalog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris modul MERANTI.
 *
 * Kolomnya disebut sekali saja, di App\Support\Meranti\Katalog; tipe
 * cast diturunkan dari sana — tanggal sebagai tanggal, angka tanpa
 * desimal sebagai bilangan bulat, sisanya pecahan. Subkelas hanya
 * menyebut tabel dan kunci katalognya.
 */
#[ScopedBy(MilikPerusahaan::class)]
abstract class Baris extends Model
{
    use BerpemilikPerusahaan;

    /** Kunci modul di Katalog. */
    public const KUNCI = '';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $cast = [];

        foreach (Katalog::modul(static::KUNCI)['kolom'] ?? [] as $k) {
            if ($k['t'] === 'tanggal') $cast[$k['k']] = 'date:Y-m-d';
            elseif ($k['t'] === 'angka' || ($k['t'] === 'hitung' && isset($k['des']))) {
                $cast[$k['k']] = ($k['des'] ?? 2) === 0 ? 'integer' : 'float';
            }
        }

        return $cast;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Nama singkat baris untuk log dan dialog: nilai kolom pertamanya. */
    public function namaBaris(): string
    {
        $k = Katalog::modul(static::KUNCI)['kolom'][0]['k'] ?? 'id';
        $v = $this->getAttribute($k);

        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) ($v ?? $this->getKey());
    }
}
