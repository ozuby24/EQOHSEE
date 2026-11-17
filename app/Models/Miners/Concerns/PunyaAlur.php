<?php

namespace App\Models\Miners\Concerns;

use App\Models\Company;
use App\Models\Miners\Alur;
use App\Support\Miners\Penyetuju;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Dokumen yang menempuh alur persetujuan.
 *
 * Dipakai MCU, induksi, Mine Permit, SIMPER, pengajuan lanjutan SIMPER,
 * dan perpanjangan Mine Permit — keenamnya menempuh alur yang bentuknya
 * sama dan hanya berbeda rantainya. Kelas pemakainya menyebut jenisnya
 * lewat `$jenisDokumen`; rantainya dibaca dari kunciRantai(), yang bagi
 * pengajuan lanjutan berbeda menurut jenis pengajuannya.
 *
 * IUP PENERBIT DISIMPAN PADA DOKUMENNYA (`iup_id`). Dialah yang
 * menentukan siapa penyetujunya — bukan perusahaan pembuatnya. Dokumen
 * yang dibuat mitra (IUJP) disetujui HSE dan KTT IUP induknya; diambil
 * dari pembuatnya, yang disurati adalah OHSE mitra itu sendiri.
 */
trait PunyaAlur
{
    public static function bootPunyaAlur(): void
    {
        static::creating(function ($model) {
            if ($model->iup_id === null) {
                $model->iup_id = $model->iupPenerbitAwal();
            }
        });
    }

    /** Kata pendek yang menandai jenis dokumen ini pada mnr_alur. */
    public static function jenisDokumen(): string
    {
        return static::$jenisDokumen;
    }

    /** Kunci rantai pada Alur::LANGKAH. Pengajuan lanjutan menimpanya. */
    public function kunciRantai(): string
    {
        return static::$jenisDokumen;
    }

    /** @return list<string> */
    public function rantaiAlur(): array
    {
        return Alur::rantai($this->kunciRantai());
    }

    /**
     * IUP penerbit saat dokumen dibuat: induk perusahaannya bila ia
     * mitra, perusahaannya sendiri bila ia IUP.
     */
    public function iupPenerbitAwal(): ?int
    {
        $c = $this->company_id ?? auth()->user()?->company_id;

        return Penyetuju::iupDari($c ? (int) $c : null);
    }

    public function iup(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'iup_id');
    }

    public function alur(): HasMany
    {
        return $this->hasMany(Alur::class, 'dokumen_id')
            ->where('dokumen', static::$jenisDokumen)
            ->orderBy('urutan');
    }

    /**
     * Terbitkan seluruh langkah sekaligus, beserta yang ditugasinya.
     *
     * SELURUHNYA sekaligus, bukan satu per satu saat gilirannya tiba —
     * sama seperti Safe Track membuat seluruh baris `*_flows` saat
     * dokumen dibuat. Yang mengajukan berhak melihat berapa langkah lagi
     * berkasnya harus melewati sebelum terbit.
     *
     * Langkah pengaju ditugaskan kepada pembuatnya; langkah lain kepada
     * penyetuju bernama pada skema IUP-nya. Yang ditugasi DIBACA ULANG
     * saat dokumen dikirim (tugaskanUlang): skema yang diubah di antara
     * draf dan kiriman harus berlaku pada kirimannya.
     */
    public function terbitkanAlur(): void
    {
        if ($this->alur()->exists()) return;

        foreach ($this->rantaiAlur() as $i => $peran) {
            $this->alur()->create([
                'dokumen'            => static::$jenisDokumen,
                'urutan'             => $i + 1,
                'peran'              => $peran,
                'keadaan'            => 'menunggu',
                'ditugaskan_user_id' => $peran === 'pengaju'
                    ? ($this->user_id ?? auth()->id())
                    : Penyetuju::id($this->iup_id ? (int) $this->iup_id : null, $peran),
            ]);
        }

        $this->unsetRelation('alur');
    }

    /**
     * Baca ulang penyetuju langkah yang masih menunggu dari skema IUP.
     *
     * Langkah yang sudah ditindak tidak disentuh: yang ditugasinya adalah
     * bagian dari riwayat, bukan pengaturan.
     */
    public function tugaskanUlang(): void
    {
        $iup = $this->iup_id ? (int) $this->iup_id : null;

        foreach ($this->alur()->get() as $l) {
            if ($l->keadaan !== 'menunggu') continue;

            $tugas = $l->peran === 'pengaju'
                ? ($this->user_id ?? $l->ditugaskan_user_id)
                : Penyetuju::id($iup, $l->peran);

            if ((int) $l->ditugaskan_user_id !== (int) $tugas) {
                $l->update(['ditugaskan_user_id' => $tugas]);
            }
        }

        $this->unsetRelation('alur');
    }

    /**
     * Kembalikan seluruh langkah ke keadaan menunggu.
     *
     * Dipakai saat berkas yang ditolak atau dikembalikan DIKIRIM ULANG.
     * Alurnya diulang DARI AWAL, bukan dilanjutkan dari langkah yang
     * menghentikannya — resubmit Safe Track menyetel seq0 lalu seq1 dan
     * mengosongkan sisanya. Berkas yang sudah diperbaiki adalah berkas
     * yang berbeda dari yang pernah dilihat langkah-langkah sebelumnya.
     *
     * Catatan peninjau lama IKUT DIHAPUS dari langkahnya; alasan
     * penolakan tetap terbaca pada catatan dokumen dan surel yang sudah
     * terkirim.
     */
    public function ulangAlur(): void
    {
        $this->terbitkanAlur();

        $this->alur()->update([
            'keadaan'        => 'menunggu',
            'user_id'        => null,
            'bertindak_pada' => null,
            'catatan'        => null,
        ]);

        $this->tugaskanUlang();
        $this->load('alur');
    }

    /** Langkah pengaju — baris pertama, yang disetujui oleh "Kirim". */
    public function langkahPengaju(): ?Alur
    {
        return $this->alur->firstWhere('peran', 'pengaju') ?? $this->alur->first();
    }

    /** Pengajunya sudah menekan "Kirim". */
    public function sudahDikirim(): bool
    {
        return $this->langkahPengaju()?->keadaan === 'setuju';
    }

    /** Ada langkah yang mengembalikan pengajuan ini. */
    public function dikembalikan(): bool
    {
        return $this->alur->contains(fn (Alur $a) => $a->keadaan === 'dikembalikan');
    }

    /** Ada langkah yang menolak pengajuan ini. */
    public function ditolakAlur(): bool
    {
        return $this->alur->contains(fn (Alur $a) => $a->keadaan === 'tolak');
    }

    /** Ada langkah yang menolak atau mengembalikan. */
    public function alurTertahan(): bool
    {
        return $this->alur->contains(fn (Alur $a) => in_array($a->keadaan, ['tolak', 'dikembalikan'], true));
    }

    /**
     * Langkah yang sedang giliran, atau null.
     *
     * Null bila alurnya tuntas, dan juga bila ia TERTAHAN — ditolak atau
     * dikembalikan. Langkah sesudah yang menolak tetap berkeadaan
     * "menunggu", tetapi tidak sedang menunggu siapa pun: yang
     * ditunggu adalah pengajunya mengirim ulang.
     */
    public function langkahBerjalan(): ?Alur
    {
        if ($this->alurTertahan()) return null;

        return $this->alur->firstWhere('keadaan', 'menunggu');
    }

    /** Seluruh langkah sudah disetujui. */
    public function alurTuntas(): bool
    {
        $alur = $this->alur;

        return $alur->isNotEmpty() && $alur->every(fn (Alur $a) => $a->keadaan === 'setuju');
    }
}
