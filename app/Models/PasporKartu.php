<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Concerns\Bertahap;
use App\Models\Concerns\Ditinjau;
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
     * Diperiksa saat pengajuan, bukan saat penyimpanan draf: draf memang
     * boleh setengah jadi, dan menolak simpanan setengah jadi berarti
     * memaksa orang mengumpulkan seluruh berkas sebelum boleh mencatat
     * apa pun.
     *
     * Hanya SIMPER yang menuntut ketiganya. Kartu masuk biasa tidak
     * memerlukan SIM kepolisian maupun sertifikat mengemudi defensif,
     * dan menuntutnya akan membuat seluruh pekerja non-pengemudi
     * tertahan oleh syarat yang tidak berlaku bagi mereka.
     *
     * @return list<string>
     */
    public function syaratKurang(): array
    {
        $kurang = [];

        if (blank($this->berkas_induksi)) $kurang[] = 'bukti induksi';

        if ($this->jenis !== 'SIMPER') return $kurang;

        if (blank($this->sim_polisi)) {
            $kurang[] = 'nomor SIM kepolisian';
        } elseif ($this->sim_polisi_expired
               && Authority::sisaHari($this->sim_polisi_expired) < 0) {
            $kurang[] = 'SIM kepolisian sudah kadaluarsa';
        }

        if (blank($this->berkas_ddt)) $kurang[] = 'sertifikat defensive driving';

        return $kurang;
    }
}
