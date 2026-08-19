<?php

namespace App\Http\Controllers;

use App\Models\{Document, KoObject, Paspor, SmkpFinding};
use App\Support\{Authority, Dasbor, DasborGrafik, PemantauanBerkas};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Dasbor menyeluruh — satu layar untuk keadaan seluruh situs.
 *
 * TERPISAH DARI DASBOR PEMBELAJARAN. Halaman /dashboard menjawab
 * pertanyaan seorang peserta tentang kursusnya sendiri; halaman ini
 * menjawab pertanyaan seorang pengawas tentang situsnya. Menggabungkan
 * keduanya membuat angka kursus dan angka izin kerja berebut tempat yang
 * sama, dan yang kalah selalu yang tidak sedang dicari orangnya.
 *
 * SUSUNANNYA MENGIKUTI URUTAN MENDESAKNYA, bukan urutan modulnya:
 * apa yang harus dikerjakan hari ini, lalu bagaimana keadaan
 * keseluruhannya, lalu barulah rinciannya per modul. Dasbor yang
 * disusun menurut nama modul memaksa pembacanya menyaring sendiri tiap
 * pagi — pekerjaan yang diulang tiap orang, dengan hasil berbeda.
 */
class DasborController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $kini = Carbon::now();

        /* Rentang dipilih dari layar dan dibatasi daftar tertutup. Nilai
           bebas dari URL berarti seseorang dapat meminta 100.000 hari
           dan menunggu selamanya sambil mengunci basis datanya. */
        $hari = (int) $request->get('hari', DasborGrafik::RENTANG_BAWAAN);
        if (!in_array($hari, DasborGrafik::RENTANG, true)) $hari = DasborGrafik::RENTANG_BAWAAN;

        $orang  = Paspor::with(['kartu', 'mcu', 'company'])->get();
        $baris  = PemantauanBerkas::baris($orang);
        $berkas = PemantauanBerkas::ringkas($baris);

        return Inertia::render('Dasbor/Halaman', [
            'judul'    => 'Dasbor EQOHSEE',
            'subjudul' => 'Keadaan seluruh situs dalam satu layar',

            'hari'       => $hari,
            'opsiHari'   => DasborGrafik::RENTANG,

            'ringkas' => [
                'manpower'      => $berkas['manpower'],
                'manpowerAktif' => $berkas['manpowerAktif'],
                'berkasHabis'   => $berkas['habis'],
                'berkasDekat'   => $berkas['mendekati'],
                'perusahaan'    => count(PemantauanBerkas::perPerusahaan($baris)),
            ],

            /* Sebaran masa berlaku seluruh berkas kelayakan, memakai
               pita yang sama dengan halaman pemantauan. Dua skala warna
               untuk satu makna membuat orang membaca kuning di sini
               sebagai sesuatu yang lain di sana. */
            'kepatuhanBerkas' => collect([
                Authority::HABIS, Authority::MENDESAK, Authority::DEKAT, Authority::PANJANG,
            ])->map(fn ($k) => [
                'kode'  => $k,
                'label' => Authority::LABEL_KARTU[$k] ?? $k,
                'nilai' => $berkas['perKeadaan'][$k] ?? 0,
            ])->values()->all(),

            'perPerusahaan' => collect(PemantauanBerkas::perPerusahaan($baris))
                ->take(8)
                ->map(fn ($c) => [
                    'label'     => $c['perusahaan'],
                    'nilai'     => $c['manpower'],
                    'habis'     => $c['habis'],
                    'mendekati' => $c['mendekati'],
                ])->values()->all(),

            'meter' => $this->meter($kini),

            /* ── grafik, dikelompokkan seperti tab di layarnya ── */
            'grafik' => [
                'keselamatan' => [
                    'hazardBulanan'  => DasborGrafik::hazardBulanan($kini),
                    'hazardRisiko'   => DasborGrafik::hazardRisiko(),
                    'hazardKategori' => DasborGrafik::hazardKategori(),
                    'inspeksiStatus' => DasborGrafik::inspeksiStatus(),
                    'temuanStatus'   => DasborGrafik::temuanStatus(),
                ],
                'produksi' => [
                    'harian'   => DasborGrafik::produksi($kini, $hari),
                    'angkutan' => DasborGrafik::angkutan($kini, $hari),
                    'energi'   => DasborGrafik::energi($kini, $hari),
                ],
                'lingkungan' => [
                    'air'        => DasborGrafik::air($kini, $hari),
                    'bakuMutu'   => DasborGrafik::lingkungan(),
                ],
                'aset' => [
                    'koStatus'        => DasborGrafik::koStatus(),
                    'workOrderStatus' => DasborGrafik::workOrderStatus(),
                    'gudangKategori'  => DasborGrafik::gudangKategori(),
                    'biayaBulanan'    => DasborGrafik::biayaBulanan($kini),
                ],
                'orang' => [
                    'mcuHasil'      => DasborGrafik::mcuHasil(),
                    'sertifikat'    => DasborGrafik::sertifikatJatuhTempo($kini),
                    'dokumenStatus' => DasborGrafik::dokumenStatus(),
                ],
            ],

            'modul' => collect(Dasbor::modul($user))->map(fn ($m) => $m + [
                'url'   => route($m['rute']),
                'warna' => self::NADA[$m['nada']] ?? self::NADA['kabar'],
            ])->all(),
        ]);
    }

    /** Warna tiap nada ubin — artinya dipesan, dan hanya empat. */
    private const NADA = [
        'gawat' => '#DC2626',
        'ingat' => '#D97706',
        'kabar' => '#0EA5E9',
        'baik'  => '#16A34A',
    ];

    /**
     * Tiga angka kepatuhan, sebagai persentase.
     *
     * Persen, bukan jumlah: "12 objek lewat jadwal" berarti berbeda pada
     * situs dengan 15 objek dan pada situs dengan 400. Pembilangnya
     * tetap disebut supaya persentase dari data yang sedikit tidak
     * terbaca sebagai kesimpulan yang kuat.
     *
     * @return list<array<string,mixed>>
     */
    private function meter(Carbon $kini): array
    {
        $pmTotal   = KoObject::whereNotNull('pm_berikutnya')->count();
        $pmLewat   = KoObject::whereNotNull('pm_berikutnya')
            ->whereDate('pm_berikutnya', '<', $kini)->count();

        $temuan    = SmkpFinding::count();
        $temuanTtp = SmkpFinding::where('status', 'Closed')->count();

        $doc       = Document::where('status', 'berlaku')->count();
        $docLewat  = Document::where('status', 'berlaku')->whereNotNull('tanggal_tinjau')
            ->whereDate('tanggal_tinjau', '<', $kini)->count();

        return [
            [
                'nama'  => 'Perawatan terjadwal',
                'ket'   => 'Objek KO yang belum lewat jadwal PM',
                'nilai' => self::persen($pmTotal - $pmLewat, $pmTotal),
                'dari'  => $pmTotal, 'baik' => $pmTotal - $pmLewat,
            ],
            [
                'nama'  => 'Temuan audit ditutup',
                'ket'   => 'Temuan SMKP yang sudah selesai',
                'nilai' => self::persen($temuanTtp, $temuan),
                'dari'  => $temuan, 'baik' => $temuanTtp,
            ],
            [
                'nama'  => 'Dokumen dalam masa tinjau',
                'ket'   => 'Dokumen berlaku yang belum lewat tinjau',
                'nilai' => self::persen($doc - $docLewat, $doc),
                'dari'  => $doc, 'baik' => $doc - $docLewat,
            ],
        ];
    }

    /**
     * Persentase yang jujur saat penyebutnya nol.
     *
     * Tanpa data, jawabannya BUKAN 100% — itu angka yang paling
     * meyakinkan bentuknya dan paling salah artinya. Null memaksa
     * layarnya menuliskan "belum ada data".
     */
    private static function persen(int $atas, int $bawah): ?float
    {
        return $bawah > 0 ? round($atas / $bawah * 100, 1) : null;
    }
}
