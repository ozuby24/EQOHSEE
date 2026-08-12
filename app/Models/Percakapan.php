<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * Satu utas percakapan.
 *
 * Sekarang hanya jenis 'bantuan' yang dipakai; 'langsung' dan 'grup' memakai
 * tabel yang sama supaya daftar, hitungan belum dibaca, dan lampiran tidak
 * perlu ditulis ulang untuk tiap jenis.
 */
class Percakapan extends Model
{
    protected $table = 'percakapan';

    protected $fillable = ['jenis', 'judul', 'company_id', 'status', 'pesan_terakhir_at'];

    protected $casts = ['pesan_terakhir_at' => 'datetime'];

    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class);
    }

    public function peserta(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'percakapan_peserta')
            ->withPivot('dibaca_sampai_id')->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Pesan yang belum dibaca seorang peserta. */
    public function belumDibaca(User $u): int
    {
        $batas = $this->peserta()->where('users.id', $u->id)->first()?->pivot?->dibaca_sampai_id;

        return $this->pesan()
            ->when($batas, fn ($q) => $q->where('id', '>', $batas))
            // Pesan sendiri tidak pernah dihitung belum dibaca.
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $u->id))
            ->count();
    }

    /**
     * Grup perusahaan — satu per perusahaan, keanggotaannya menyesuaikan
     * sendiri setiap dipanggil.
     *
     * Keanggotaan tidak disimpan sebagai daftar tetap yang perlu diperbarui
     * dari luar (mis. saat admin memindahkan seseorang di Personalia).
     * Setiap grup dibuka, baris peserta disamakan dengan siapa saja yang
     * company_id-nya cocok sekarang. Cara ini tahan terhadap perpindahan
     * perusahaan lewat jalur mana pun — termasuk yang belum ada saat kode
     * ini ditulis — dengan biaya dua kueri ringan per kunjungan.
     */
    public static function grupPerusahaan(Company $perusahaan): self
    {
        $p = static::firstOrCreate(
            ['jenis' => 'grup', 'company_id' => $perusahaan->id],
            ['judul' => $perusahaan->name]
        );

        // Nama grup ikut nama perusahaan; nama yang beku menyesatkan
        // setelah perusahaan berganti nama.
        if ($p->judul !== $perusahaan->name) {
            $p->judul = $perusahaan->name;
            $p->save();
        }

        $seharusnya = User::where('company_id', $perusahaan->id)->pluck('id');
        $sekarang   = $p->peserta()->pluck('users.id');

        $tambah = $seharusnya->diff($sekarang);
        $buang  = $sekarang->diff($seharusnya);

        if ($tambah->isNotEmpty()) $p->peserta()->attach($tambah);
        if ($buang->isNotEmpty())  $p->peserta()->detach($buang);

        return $p;
    }

    /**
     * Melepas seorang pengguna dari grup perusahaan mana pun yang bukan
     * miliknya sekarang.
     *
     * grupPerusahaan() menyegarkan keanggotaan dari sudut pandang satu
     * perusahaan — cukup selama ada anggota lain yang sesekali membuka
     * grup itu. Perusahaan yang ditinggalkan sendirian (tanpa anggota
     * tersisa yang pernah membuka Pesan lagi) tidak akan pernah
     * tersentuh dari sisi itu, dan pengguna yang sudah pindah akan tetap
     * terdaftar di grup lama tanpa batas waktu. Dipanggil dari sudut
     * pandang pengguna yang berpindah, bukan dari sudut pandang
     * perusahaan, cara ini menutup celah itu.
     */
    public static function keluarkanDariGrupLama(User $u): void
    {
        static::where('jenis', 'grup')
            ->when(
                $u->company_id,
                fn ($q) => $q->where('company_id', '!=', $u->company_id),
                fn ($q) => $q
            )
            ->whereHas('peserta', fn ($q) => $q->where('users.id', $u->id))
            ->get()
            ->each(fn (self $p) => $p->peserta()->detach($u->id));
    }

    /** Percakapan langsung antara dua pengguna — dibuat sekali, dipakai ulang. */
    public static function langsungAntara(User $a, User $b): self
    {
        abort_if($a->id === $b->id, 422, 'Tidak dapat memulai percakapan dengan diri sendiri.');

        $p = static::where('jenis', 'langsung')
            ->whereHas('peserta', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('peserta', fn ($q) => $q->where('users.id', $b->id))
            ->first();

        if ($p) return $p;

        $p = static::create(['jenis' => 'langsung']);
        $p->peserta()->attach([$a->id, $b->id]);

        return $p;
    }
}
