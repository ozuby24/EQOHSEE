<?php

namespace App\Support;

use App\Models\{Company, HazardReport, InspectionItem, KoAction, SmkpFinding, TindakLanjut};
use Illuminate\Support\Carbon;

/**
 * Register temuan dan tindak lanjut seluruh modul, dalam satu bentuk.
 *
 * Platform ini melahirkan temuan dari lima tempat yang berbeda, dan
 * kelimanya menyimpannya dengan bentuknya sendiri-sendiri:
 *
 *   TindakLanjut   sepuluh modul baru · penanggung_jawab · target_selesai
 *   SmkpFinding    audit SMKP         · penanggung_jawab · target_selesai
 *   KoAction       keselamatan operasi· pic_nama         · target_tgl
 *   HazardReport   laporan bahaya     · TANPA pemilik, TANPA tenggat
 *   InspectionItem butir inspeksi     · TANPA pemilik, TANPA tenggat
 *
 * Akibatnya pertanyaan paling dasar dalam sistem K3 — "apa saja yang
 * terlambat di seluruh site" — berubah menjadi lima kueri dengan lima
 * bentuk yang harus disatukan dengan tangan, dan pekerjaan tangan itu
 * tidak pernah benar-benar dikerjakan.
 *
 * Berkas ini TIDAK memindahkan data dan tidak menggandakannya. Ia membaca
 * kelimanya lalu menormalkannya menjadi satu bentuk. Pilihan itu disengaja:
 * memindahkan seluruh temuan ke satu tabel berarti membongkar daur hidup
 * yang sudah dipakai orang di tiap modul — dan pembongkaran semacam itu
 * menukar satu masalah yang terlihat dengan sepuluh masalah yang belum.
 *
 * Yang paling penting di sini bukan penyatuannya melainkan kolom
 * `bertuan`. Temuan tanpa penanggung jawab dan tanpa tenggat bukan temuan
 * yang sedang ditangani perlahan — ia temuan yang tidak sedang ditangani
 * siapa pun. Dalam daftar biasa keduanya terlihat sama persis, sebab
 * keduanya sama-sama berstatus terbuka. Justru itulah yang membuatnya
 * berbahaya, dan justru itu yang dipisahkan di sini.
 */
final class Temuan
{
    /**
     * Sumber yang sudah punya kolom penanggung jawab sendiri.
     *
     * Ditugaskan DI TEMPAT, pada barisnya sendiri. Membuatkan tindak
     * lanjut terpisah untuk baris yang sudah punya kolomnya akan
     * melahirkan dua tempat yang sama-sama mengaku tahu siapa
     * penanggung jawabnya, dan keduanya akan berselisih.
     *
     * sumber => [kelas, kolom nama, kolom tenggat]
     */
    public const DITUGASKAN_DI_TEMPAT = [
        'tindak-lanjut' => [TindakLanjut::class, 'penanggung_jawab', 'target_selesai'],
        'smkp'          => [SmkpFinding::class,  'penanggung_jawab', 'target_selesai'],
        'ko'            => [KoAction::class,     'pic_nama',         'target_tgl'],
    ];

    /**
     * Sumber yang TIDAK punya kolom penanggung jawab sama sekali.
     *
     * Laporan bahaya dan butir inspeksi tidak menyimpan siapa pun dan
     * tidak menyimpan tenggat. Menambahkan kolomnya ke kedua tabel itu
     * tampak paling lurus, dan justru itu yang tidak dilakukan: tabel
     * tindak_lanjut sudah ada, sudah punya daur hidupnya sendiri, dan
     * sudah punya relasi morph `sumber` yang docblock-nya menyebut niat
     * ini sejak awal. Menambah kolom berarti membangun daur hidup kedua
     * di sebelah yang sudah jalan.
     *
     * sumber => [kelas, nama modul untuk tindak lanjutnya]
     */
    public const DITUGASKAN_LEWAT_TINDAK_LANJUT = [
        'hazard'   => [HazardReport::class,   'bahaya'],
        'inspeksi' => [InspectionItem::class, 'inspeksi'],
    ];

    /** Status yang berarti pekerjaannya masih menuntut sesuatu. */
    private const TERBUKA = 'terbuka';
    private const SELESAI = 'selesai';
    private const BATAL   = 'batal';

    /**
     * Seluruh temuan dari seluruh sumber, sudah dinormalkan.
     *
     * Tiap sumber dibungkus penangkap galatnya sendiri: satu modul yang
     * skemanya belum lengkap tidak boleh mengosongkan register yang justru
     * dibuka untuk melihat gambaran menyeluruh.
     *
     * @return list<array<string,mixed>>
     */
    public static function semua(?Company $c = null): array
    {
        $hasil = [];

        foreach ([
            'tindakLanjut', 'smkp', 'ko', 'hazard', 'inspeksi',
        ] as $sumber) {
            try {
                foreach (self::{$sumber}($c) as $baris) $hasil[] = $baris;
            } catch (\Throwable $e) {
                $hasil[] = self::baris(
                    sumber: $sumber,
                    modul: $sumber,
                    kode: '—',
                    judul: 'Sumber ini gagal dibaca',
                    uraian: $e->getMessage(),
                    status: self::TERBUKA,
                    prioritas: 'kritis',
                );
            }
        }

        usort($hasil, function ($a, $b) {
            // Terlambat lebih dulu, lalu yang tak bertuan, lalu prioritas,
            // lalu tenggat terdekat. Urutan ini adalah isi laporannya:
            // yang di atas adalah yang paling menuntut hari ini.
            return [
                $a['terbuka'] ? 0 : 1,
                $a['terlambat'] ? 0 : 1,
                $a['bertuan'] ? 1 : 0,
                self::urutPrioritas($a['prioritas']),
                $a['targetSelesai'] ?? '9999-12-31',
            ] <=> [
                $b['terbuka'] ? 0 : 1,
                $b['terlambat'] ? 0 : 1,
                $b['bertuan'] ? 1 : 0,
                self::urutPrioritas($b['prioritas']),
                $b['targetSelesai'] ?? '9999-12-31',
            ];
        });

        return $hasil;
    }

    /** @return array<string,int> */
    public static function ringkas(array $temuan): array
    {
        $terbuka = array_filter($temuan, fn ($t) => $t['terbuka']);

        return [
            'semua'      => count($temuan),
            'terbuka'    => count($terbuka),
            'terlambat'  => count(array_filter($terbuka, fn ($t) => $t['terlambat'])),
            'takBertuan' => count(array_filter($terbuka, fn ($t) => !$t['bertuan'])),
            'selesai'    => count(array_filter($temuan, fn ($t) => $t['status'] === self::SELESAI)),
        ];
    }

    /** @return array<string,int> jumlah temuan terbuka per modul */
    public static function perModul(array $temuan): array
    {
        $n = [];
        foreach ($temuan as $t) {
            if (!$t['terbuka']) continue;
            $n[$t['modul']] = ($n[$t['modul']] ?? 0) + 1;
        }
        arsort($n);

        return $n;
    }

    /* ═══════════ sumber ═══════════ */

    private static function tindakLanjut(?Company $c): array
    {
        return self::kueri(TindakLanjut::class, $c)->with('sumber')
            /* Tindak lanjut yang MELEKAT pada baris lain tidak muncul
               sebagai barisnya sendiri — ia sudah terwakili oleh baris
               yang ditunjuknya, yang kini memakai nama dan tenggatnya.
               Tanpa ini, satu laporan bahaya yang ditugaskan akan terhitung
               dua kali: sekali sebagai bahaya, sekali sebagai tindak
               lanjut, dan angka register menjadi lebih besar daripada
               pekerjaan yang sebenarnya ada.

               Yang menempel lewat kode_pemicu saja TIDAK disembunyikan:
               kode adalah teks yang diketik orang, bisa salah, dan bisa
               sama di dua modul. Hanya kaitan morph yang cukup pasti
               untuk dipakai membuang baris dari daftar. */
            ->whereNull('sumber_type')
            ->get()->map(fn ($t) => self::baris(
            sumber: 'tindak-lanjut',
            modul: $t->modul ?: 'lain',
            kode: $t->kode_pemicu ?: '#'.$t->id,
            judul: $t->judul,
            uraian: $t->uraian,
            prioritas: $t->prioritas,
            status: match ($t->status) {
                'selesai' => self::SELESAI,
                'batal'   => self::BATAL,
                default   => self::TERBUKA,
            },
            penanggungJawab: $t->penanggung_jawab,
            targetSelesai: $t->target_selesai?->toDateString(),
            id: $t->id,
        ))->all();
    }

    private static function smkp(?Company $c): array
    {
        $q = SmkpFinding::query()->with('audit');

        if ($c) {
            $q->whereHas('audit', fn ($a) => $a->withoutGlobalScopes()->where('company_id', $c->id));
        }

        return $q->get()->map(fn ($f) => self::baris(
            sumber: 'smkp',
            modul: 'audit-smkp',
            kode: $f->kode_kriteria ?: '#'.$f->id,
            judul: $f->jenis ? $f->jenis.' — '.self::ringkasTeks($f->uraian) : self::ringkasTeks($f->uraian),
            uraian: $f->tindakan ?: $f->akar_masalah,
            // Ketidaksesuaian besar menuntut lebih cepat daripada observasi.
            prioritas: match (strtolower((string) $f->jenis)) {
                'major', 'mayor' => 'kritis',
                'minor'          => 'tinggi',
                default          => 'sedang',
            },
            status: $f->status === 'Closed' ? self::SELESAI : self::TERBUKA,
            penanggungJawab: $f->penanggung_jawab,
            targetSelesai: $f->target_selesai?->toDateString(),
            id: $f->id,
        ))->all();
    }

    private static function ko(?Company $c): array
    {
        $q = KoAction::query()->with('object');

        if ($c) {
            $q->whereHas('object', fn ($o) => $o->withoutGlobalScopes()->where('company_id', $c->id));
        }

        return $q->get()->map(fn ($a) => self::baris(
            sumber: 'ko',
            modul: 'keselamatan-operasi',
            kode: $a->object?->kode ?: '#'.$a->id,
            judul: self::ringkasTeks($a->uraian),
            uraian: $a->tindakan,
            prioritas: strtolower((string) $a->prioritas) ?: 'sedang',
            status: match ($a->status) {
                'Selesai'    => self::SELESAI,
                'Dibatalkan' => self::BATAL,
                default      => self::TERBUKA,
            },
            penanggungJawab: $a->pic_nama ?: $a->pic?->name,
            targetSelesai: $a->target_tgl?->toDateString(),
            id: $a->id,
        ))->all();
    }

    /**
     * Laporan bahaya yang belum ditutup.
     *
     * Tidak punya kolom penanggung jawab maupun tenggat sama sekali, jadi
     * seluruhnya masuk sebagai tak bertuan. Itu bukan kekurangan pembacaan
     * di sini melainkan keadaan sebenarnya: laporan bahaya di aplikasi ini
     * memang belum dapat ditugaskan kepada siapa pun.
     */
    private static function hazard(?Company $c): array
    {
        $tugas = self::tugasMelekat(HazardReport::class, $c);

        return self::kueri(HazardReport::class, $c)
            ->whereNotIn('status', [Hazard::STATUS[2]])
            ->get()->map(fn ($h) => self::baris(
                sumber: 'hazard',
                modul: 'hazard',
                kode: $h->kode ?: '#'.$h->id,
                judul: self::ringkasTeks($h->deskripsi),
                uraian: $h->rekomendasi,
                prioritas: match ($h->risiko) {
                    'Tinggi' => 'kritis',
                    'Sedang' => 'tinggi',
                    default  => 'sedang',
                },
                status: self::TERBUKA,
                /* Pemiliknya diwarisi dari tindak lanjut yang MENUNJUKNYA
                   lewat relasi morph. Laporan bahaya sendiri tidak
                   menyimpan siapa pun; sesudah ditugaskan, yang menyimpan
                   nama dan tenggatnya adalah tindak lanjut itu. */
                penanggungJawab: $tugas[$h->id]['nama'] ?? null,
                targetSelesai: $tugas[$h->id]['tenggat'] ?? null,
                id: $h->id,
            ))->all();
    }

    /**
     * Butir inspeksi yang tidak sesuai dan belum dinaikkan menjadi bahaya.
     *
     * Yang sudah dinaikkan sengaja dilewati: ia sudah muncul lewat laporan
     * bahayanya, dan menghitungnya dua kali membuat jumlah temuan terbuka
     * lebih besar daripada yang sebenarnya — angka yang justru dipakai
     * menilai kinerja.
     */
    private static function inspeksi(?Company $c): array
    {
        $tugas = self::tugasMelekat(InspectionItem::class, $c);

        $q = InspectionItem::query()
            ->with('inspection')
            ->where('kondisi', Hazard::KONDISI[1])
            ->whereNull('hazard_report_id');

        if ($c) {
            $q->whereHas('inspection', fn ($i) => $i->withoutGlobalScopes()->where('company_id', $c->id));
        }

        return $q->get()->map(fn ($x) => self::baris(
            sumber: 'inspeksi',
            modul: 'inspeksi',
            kode: $x->inspection?->kode ?: '#'.$x->id,
            judul: self::ringkasTeks($x->temuan ?: $x->uraian),
            uraian: $x->tindakan,
            prioritas: match ($x->risiko) {
                'Tinggi' => 'kritis',
                'Sedang' => 'tinggi',
                default  => 'sedang',
            },
            status: self::TERBUKA,
            penanggungJawab: $tugas[$x->id]['nama'] ?? null,
            targetSelesai: $tugas[$x->id]['tenggat'] ?? null,
            id: $x->id,
        ))->all();
    }

    /* ═══════════ perkakas ═══════════ */

    /**
     * Satu baris register, bentuknya sama untuk seluruh sumber.
     *
     * `bertuan` menuntut KEDUANYA — penanggung jawab dan tenggat. Salah
     * satu saja tidak cukup: nama tanpa tanggal tidak pernah jatuh tempo,
     * dan tanggal tanpa nama tidak pernah ada yang ditagih.
     */
    private static function baris(
        string $sumber,
        string $modul,
        string $kode,
        string $judul,
        ?string $uraian = null,
        string $prioritas = 'sedang',
        string $status = self::TERBUKA,
        ?string $penanggungJawab = null,
        ?string $targetSelesai = null,
        ?int $id = null,
    ): array {
        $terbuka = $status === self::TERBUKA;
        $bertuan = $penanggungJawab !== null && $penanggungJawab !== ''
                && $targetSelesai !== null;

        $terlambat = $terbuka
            && $targetSelesai !== null
            && Carbon::parse($targetSelesai)->isPast();

        return [
            'sumber'          => $sumber,
            /* Identitas baris asalnya. Tanpa ini register hanya dapat
               MELAPORKAN; dengan ini ia dapat menugaskan, sebab penugasan
               harus tahu baris mana yang dimaksud — dan harus tahunya
               lewat kunci yang dijaga scope perusahaan, bukan lewat kode
               yang diketik orang dan bisa sama di dua perusahaan. */
            'id'              => $id,
            'modul'           => $modul,
            'kode'            => $kode,
            'judul'           => $judul,
            'uraian'          => $uraian ?: null,
            'prioritas'       => $prioritas,
            'status'          => $status,
            'terbuka'         => $terbuka,
            'bertuan'         => $bertuan,
            'penanggungJawab' => $penanggungJawab ?: null,
            'targetSelesai'   => $targetSelesai,
            'terlambat'       => $terlambat,
            'hariTerlambat'   => $terlambat
                ? (int) Carbon::parse($targetSelesai)->diffInDays(now())
                : 0,

            /* Yang sudah selesai atau batal tidak lagi menuntut siapa pun,
               jadi tidak ada gunanya ditugaskan. Ditentukan di sini, bukan
               di tampilan: tombol yang muncul lalu ditolak server adalah
               cara paling cepat membuat orang berhenti memercayai
               tombolnya. */
            'dapatDitugaskan' => $id !== null && $terbuka,
        ];
    }

    /**
     * Penanggung jawab dan tenggat dari tindak lanjut yang melekat.
     *
     * Satu kueri untuk seluruh baris, bukan satu per baris: register ini
     * justru dibuka ketika temuannya banyak.
     *
     * Bila satu baris punya lebih dari satu tindak lanjut, yang dipakai
     * adalah yang tenggatnya PALING DEKAT. Yang paling dekat itulah yang
     * menentukan kapan baris ini mulai terlambat, dan terlambat adalah
     * pertanyaan yang dibawa orang ke halaman ini.
     *
     * @return array<int,array{nama:?string,tenggat:?string}>
     */
    private static function tugasMelekat(string $kelas, ?Company $c): array
    {
        $q = TindakLanjut::query()
            ->where('sumber_type', $kelas)
            ->whereNotNull('sumber_id')
            ->orderByDesc('target_selesai');      // terdekat ditulis terakhir, jadi menang

        if ($c) $q->where('company_id', $c->id);

        $out = [];

        foreach ($q->get() as $t) {
            $out[(int) $t->sumber_id] = [
                'nama'    => $t->penanggung_jawab,
                'tenggat' => $t->target_selesai?->toDateString(),
            ];
        }

        return $out;
    }

    /**
     * Kalimat panjang dipendekkan menjadi judul yang muat satu baris.
     *
     * Dipotong pada batas kata, bukan pada huruf ke sekian: potongan di
     * tengah kata terbaca seperti data yang rusak, dan register ini justru
     * dibuka orang untuk memutuskan mana yang perlu dibuka lebih dulu.
     */
    private static function ringkasTeks(?string $teks, int $batas = 90): string
    {
        $teks = trim(preg_replace('/\s+/', ' ', (string) $teks));

        if ($teks === '') return 'Tanpa uraian';
        if (mb_strlen($teks) <= $batas) return $teks;

        $potong = mb_substr($teks, 0, $batas);
        $spasi  = mb_strrpos($potong, ' ');

        return rtrim($spasi !== false ? mb_substr($potong, 0, $spasi) : $potong, ' ,.;:').'…';
    }

    private static function urutPrioritas(string $p): int
    {
        return match ($p) {
            'kritis' => 0,
            'tinggi' => 1,
            'sedang' => 2,
            default  => 3,
        };
    }

    /** Kueri satu model, dibatasi perusahaan bila kolomnya ada. */
    private static function kueri(string $kelas, ?Company $c)
    {
        $q = $kelas::query();

        if ($c) $q->where('company_id', $c->id);

        return $q;
    }
}
