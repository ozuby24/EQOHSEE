<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Concerns\Bertahap;
use App\Models\Concerns\Ditinjau;
use App\Support\AlurMiner;
use App\Support\Authority;
use App\Support\Tahap;
use Illuminate\Database\Eloquent\Model;

/**
 * Kartu masuk area tambang — ID card, SIMPER, atau mine permit.
 *
 * Satu orang dapat memegang lebih dari satu: kartu masuk area dan
 * SIMPER kendaraan adalah dua izin berbeda dengan masa berlaku
 * berbeda, dan menyimpannya sebagai satu baris membuat yang satu
 * menghapus yang lain saat diperpanjang.
 *
 * PENERBITANNYA PUNYA ALUR, DAN ITU BUKAN TAMBAHAN ADMINISTRATIF.
 * Yang menentukan seseorang boleh mengemudi di area tambang bukan
 * kartunya melainkan yang mendahului kartu itu: SIM kepolisian yang
 * masih berlaku, bukti induksi, dan sertifikat mengemudi defensif.
 * Selama kartu dapat dicatat tanpa ditinjau, ketiga syarat itu hanya
 * medan yang boleh dikosongkan — dan kartu yang terbit tanpa dasar
 * tidak dapat ditelusuri siapa yang meloloskannya.
 */
class PasporKartu extends Model
{
    use BerindukPerusahaan;
    use Ditinjau;
    use Bertahap;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'paspor_kartu';

    /** `status` sengaja tidak dapat diisi massal — lihat trait Ditinjau. */
    protected $fillable = [
        'paspor_id', 'jenis', 'sebab_terbit', 'nomor', 'tgl_terbit', 'tgl_expired',
        'golongan', 'area', 'sim_polisi', 'sim_polisi_expired', 'pengalaman_kerja',
        'berkas_induksi', 'berkas_ddt', 'email_atasan', 'berkas', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tgl_terbit'         => 'date',
            'tgl_expired'        => 'date',
            'sim_polisi_expired' => 'date',
        ];
    }

    public function paspor() { return $this->belongsTo(Paspor::class); }

    /**
     * Unit yang boleh dikemudikan, beserta penilaiannya masing-masing.
     *
     * Hanya bermakna bagi kartu SIMPER (Mine License). Kartu masuk area
     * tidak menyebut unit, dan relasi ini akan kosong di sana — kosong
     * yang benar, bukan yang terlupa.
     */
    public function unit() { return $this->hasMany(PasporKartuUnit::class, 'paspor_kartu_id'); }

    /**
     * Yang memutuskan penerbitan kartu hanya OHSE.
     *
     * Menimpa penjaga baku Ditinjau, yang membolehkan administrator ATAU
     * Kepala Teknik Tambang meninjau apa pun. Di modul ini wewenangnya
     * lebih sempit dan disengaja begitu — lihat App\Support\Tahap.
     */
    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!Tahap::penentu($u))    return false;
        if (!$this->menungguTinjauan()) return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }

    /**
     * Syarat yang belum terpenuhi untuk MENGAJUKAN kartu ini.
     *
     * Diserahkan ke App\Support\AlurMiner, tempat urutan tahapannya
     * tinggal. Sebelumnya syaratnya diperiksa DI SINI dan hanya melihat
     * apakah medan `berkas_induksi` terisi — sebuah teks yang diketik
     * tangan. Siapa pun dapat mengetik apa saja ke dalamnya dan kartunya
     * lolos, sementara induksi yang sesungguhnya tercatat di tabelnya
     * sendiri tidak pernah dilihat sama sekali. Penjagaan yang memeriksa
     * keterangan tentang sebuah berkas, bukan berkasnya, adalah
     * penjagaan yang hanya menahan orang yang jujur.
     *
     * @return list<string>
     */
    public function syaratKurang(): array
    {
        return AlurMiner::halanganKartu($this);
    }
}
