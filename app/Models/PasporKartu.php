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
        'golongan', 'area', 'sim_polisi', 'sim_polisi_expired', 'berkas_sim', 'pengalaman_kerja',
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

    /**
     * Keadaan masa berlakunya, dihitung atas tanggal EFEKTIF.
     *
     * Bukan atas tanggal yang tercetak. Kartu yang berlaku sampai
     * Desember tetapi berpijak pada MCU yang habis Agustus sudah tidak
     * sah pada September, dan menampilkannya sebagai "aman" persis cara
     * orang lolos gerbang dengan berkas yang secara resmi masih berlaku
     * tetapi secara medis tidak lagi berdasar.
     *
     * Menuntut relasi `paspor.mcu` sudah dimuat untuk Mine Permit —
     * lihat expiredDasar(). Yang memuatnya bertanggung jawab; tanpa itu
     * satu kueri tambahan per kartu.
     */
    public function keadaan(): string    { return Authority::keadaan($this->expiredEfektif()); }

    /**
     * Keterangannya, beserta SEBAB pembatasnya bila ada.
     *
     * "Habis 12 September" tanpa sebab membuat orang memperpanjang
     * kartunya, padahal yang perlu diperpanjang MCU-nya.
     */
    public function keterangan(): string
    {
        $dasar = Authority::keterangan($this->expiredEfektif());

        return $this->dibatasiDasar()
            ? $dasar.' — dibatasi '.$this->namaDasar()
            : $dasar;
    }

    /* ═══════════ masa berlaku turunan ═══════════ */

    /**
     * Tanggal yang MEMBATASI kartu ini dari berkas yang mendasarinya.
     *
     * Mine Permit berdiri di atas MCU; SIMPER berdiri di atas SIM
     * kepolisian. Keduanya bukan pelengkap administratif melainkan dasar
     * izinnya: MCU yang habis berarti orangnya tidak lagi dinyatakan
     * sehat untuk bekerja di tambang, dan SIM yang habis berarti ia tidak
     * lagi boleh mengemudi di jalan umum mana pun.
     *
     * Karena itu kartu TIDAK DAPAT hidup lebih lama daripada dasarnya.
     * Kartu berlaku sampai Desember yang berpijak pada MCU yang habis
     * Agustus sudah tidak sah pada bulan September — meskipun tanggal
     * yang tercetak padanya mengatakan sebaliknya, dan meskipun tidak
     * ada satu pun galat yang muncul.
     *
     * Visitor tidak punya dasar semacam itu dan memulangkan null.
     */
    public function expiredDasar(): ?\Illuminate\Support\Carbon
    {
        return match ($this->jenis) {
            AlurMiner::KARTU_PERMIT  => $this->paspor?->mcuTerakhir()?->tgl_expired,
            AlurMiner::KARTU_LICENSE => $this->sim_polisi_expired,
            default                  => null,
        };
    }

    /** Nama dasar itu, untuk dijelaskan di layar. */
    public function namaDasar(): ?string
    {
        return match ($this->jenis) {
            AlurMiner::KARTU_PERMIT  => 'MCU',
            AlurMiner::KARTU_LICENSE => 'SIM kepolisian',
            default                  => null,
        };
    }

    /**
     * Tanggal habis yang SEBENARNYA berlaku — yang mana pun lebih dulu.
     *
     * Inilah yang dipantau, bukan `tgl_expired` yang tercetak. Yang
     * tercetak hanya benar selama dasarnya masih hidup.
     */
    public function expiredEfektif(): ?\Illuminate\Support\Carbon
    {
        $kartu = $this->tgl_expired;
        $dasar = $this->expiredDasar();

        if (!$kartu) return $dasar;
        if (!$dasar) return $kartu;

        return $dasar->lt($kartu) ? $dasar : $kartu;
    }

    /**
     * Apakah dasarnya habis lebih dulu daripada kartunya.
     *
     * Dipisahkan supaya layarnya dapat mengatakan SEBABNYA. "Habis 12
     * September" tanpa keterangan membuat orang memperpanjang kartunya,
     * padahal yang perlu diperpanjang MCU-nya.
     */
    public function dibatasiDasar(): bool
    {
        $kartu = $this->tgl_expired;
        $dasar = $this->expiredDasar();

        return $kartu !== null && $dasar !== null && $dasar->lt($kartu);
    }

    /** Keadaan kedaluwarsa menurut pita kartu, atas tanggal efektifnya. */
    public function keadaanKartu(): string
    {
        return Authority::keadaanKartu($this->expiredEfektif());
    }

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
