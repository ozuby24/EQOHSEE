<?php

namespace App\Services\Asisten\Tugas;

use App\Models\{SmkpAudit, SmkpFinding};
use App\Services\Asisten\{Jawaban, KonteksAudit, Permintaan, Prompt, Rapi};
use App\Support\Smkp;

/**
 * Analisis satu temuan SMKP: akar masalah (rantai 5-Why dan kategorinya),
 * koreksi segera, Rencana Tindak Perbaikan (korektif & pencegahan, PIC per
 * jabatan, durasi, bukti penutupan), dan tingkat risiko.
 *
 * Usulan isian formulir CAR ("isian") disusun di sini dan dijepit ke
 * panjang kolom smkp_findings — auditor yang memilih kolom mana yang
 * diterapkan; tidak ada yang tersimpan tanpa ia menekan Simpan.
 */
final class AnalisisTemuan
{
    public const KATEGORI = [
        'manusia'    => 'Manusia',
        'metode'     => 'Metode',
        'peralatan'  => 'Peralatan',
        'material'   => 'Material',
        'lingkungan' => 'Lingkungan',
        'manajemen'  => 'Manajemen',
    ];

    public const RISIKO = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'ekstrem' => 'Ekstrem'];

    /**
     * @param array{akar_masalah?:string,tindakan?:string} $draf isian formulir yang mungkin belum disimpan
     */
    public function __construct(
        private readonly SmkpAudit $audit,
        private readonly SmkpFinding $temuan,
        private readonly array $draf = [],
    ) {}

    public static function skema(): array
    {
        $aksi = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['tindakan', 'pic', 'hari', 'bukti_penutupan'],
                'properties' => [
                    'tindakan'        => ['type' => 'string', 'description' => 'Tindakan spesifik yang menyasar akar masalah.'],
                    'pic'             => ['type' => 'string', 'description' => 'Penanggung jawab berupa jabatan/fungsi, bukan nama orang.'],
                    'hari'            => ['type' => 'integer', 'description' => 'Usulan durasi penyelesaian dalam hari kalender (1-365).'],
                    'bukti_penutupan' => ['type' => 'string', 'description' => 'Bukti objektif bahwa tindakan selesai dan efektif.'],
                ],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['akar_masalah', 'koreksi_segera', 'korektif', 'preventif', 'target_hari', 'bukti_penutupan', 'tingkat_risiko', 'alasan_risiko'],
            'properties' => [
                'akar_masalah' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['kategori', 'lima_mengapa', 'pernyataan'],
                    'properties' => [
                        'kategori' => ['type' => 'string', 'enum' => array_keys(self::KATEGORI), 'description' => 'Kategori akar masalah dominan.'],
                        'lima_mengapa' => [
                            'type' => 'array',
                            'description' => 'Rantai 5-Why: 3 sampai 5 langkah berurutan dari gejala menuju akar masalah.',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['tanya', 'jawab'],
                                'properties' => [
                                    'tanya' => ['type' => 'string', 'description' => 'Pertanyaan "Mengapa ...?"'],
                                    'jawab' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'pernyataan' => ['type' => 'string', 'description' => 'Pernyataan akar masalah dalam 1-2 kalimat.'],
                    ],
                ],
                'koreksi_segera' => ['type' => 'string', 'description' => 'Koreksi segera (containment) untuk mengendalikan kondisi saat ini.'],
                'korektif'  => ['description' => '1-4 tindakan korektif yang menghilangkan akar masalah.'] + $aksi,
                'preventif' => ['description' => '1-3 tindakan pencegahan agar tidak terulang di area/proses lain.'] + $aksi,
                'target_hari' => ['type' => 'integer', 'description' => 'Usulan target penutupan seluruh RTP dalam hari kalender sejak hari ini (1-365); lebih singkat untuk mayor.'],
                'bukti_penutupan' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Bukti yang diminta auditor untuk menutup temuan dan memverifikasi keefektifan.'],
                'tingkat_risiko' => ['type' => 'string', 'enum' => array_keys(self::RISIKO)],
                'alasan_risiko'  => ['type' => 'string', 'description' => 'Alasan tingkat risiko dalam 1-2 kalimat.'],
            ],
        ];
    }

    public function permintaan(): Permintaan
    {
        $t     = $this->temuan;
        $letak = KonteksAudit::sub((string) $t->kode_kriteria);
        $hasil = (array) ($this->audit->hasil ?? []);

        $teks = [
            'Tugas: analisis satu temuan audit SMKP Minerba — akar masalah dan Rencana Tindak Perbaikan (RTP).',
            '',
            'Konteks audit:',
            KonteksAudit::smkp($this->audit),
            '',
            'Temuan:',
            "- Kode kriteria: {$t->kode_kriteria}",
            '- Kategori: '.match ($t->jenis) { 'kritikal' => 'Ketidaksesuaian Kritikal', 'mayor' => 'Ketidaksesuaian Mayor', 'minor' => 'Ketidaksesuaian Minor', default => (string) $t->jenis },
            '- Status: '.$t->status,
            '- Uraian masalah: '.Prompt::data($t->uraian),
        ];
        // Isian Formulir Ketidaksesuaian yang memperkaya analisis bila sudah diisi auditor.
        foreach (['lokasi' => 'Area / lokasi & departemen', 'bukti' => 'Bukti objektif', 'deskripsi' => 'Deskripsi (persyaratan tidak terpenuhi)'] as $kolom => $label) {
            if (trim((string) $t->{$kolom}) !== '') $teks[] = "- {$label}: ".Prompt::data((string) $t->{$kolom});
        }

        if ($letak) {
            ['elemen' => $e, 'sub' => $s] = $letak;
            $r = Smkp::rekapSub($s, $hasil);
            array_push($teks,
                "- Elemen {$e['kode']} {$e['nama']} · sub-elemen {$s['kode']} {$s['nama']}".(($s['ref'] ?? null) ? " ({$s['ref']} Lampiran II)" : ''),
                '- Capaian sub-elemen: '.rtrim(rtrim(number_format($r['nilai'], 1, ',', ''), '0'), ',').' dari '.$r['maks'].' poin',
                '',
                'Penilaian butir pada sub-elemen ini:',
            );
            foreach (Smkp::butirSub($s) as $b) {
                $h = (array) ($hasil[$b['kode']] ?? []);
                $teks[] = "- {$b['kode']} {$b['nama']}: ".KonteksAudit::nilaiTersimpan($hasil, $b);
                if (trim((string) ($h['bukti'] ?? '')) !== '') $teks[] = '  Bukti: '.Prompt::data($h['bukti']);
                if (trim((string) ($h['ket'] ?? '')) !== '')   $teks[] = '  Catatan: '.Prompt::data($h['ket']);
            }
        }

        $akar     = ($this->draf['akar_masalah'] ?? null) ?? $t->akar_masalah;
        $tindakan = ($this->draf['tindakan'] ?? null) ?? $t->tindakan;
        array_push($teks, '',
            'Analisis akar masalah yang sudah ditulis auditor:', Prompt::data($akar, '(belum ada)'),
            'Tindakan perbaikan yang sudah ditulis auditor:', Prompt::data($tindakan, '(belum ada)'),
            '',
            'Kategori akar masalah: manusia, metode, peralatan, material, lingkungan, atau manajemen. '.
            'Tindakan korektif menghilangkan akar masalah — bukan sekadar memperbaiki gejala. Tindakan harus terukur dan dapat diverifikasi.',
        );

        return new Permintaan('temuan', Prompt::sistem(), implode("\n", $teks), self::skema());
    }

    public function rapikan(Jawaban $j): array
    {
        $d    = $j->data;
        $akar = is_array($d['akar_masalah'] ?? null) ? $d['akar_masalah'] : [];

        $mengapa = [];
        foreach (is_array($akar['lima_mengapa'] ?? null) ? $akar['lima_mengapa'] : [] as $x) {
            $tanya = Rapi::teks($x['tanya'] ?? '', 300);
            $jawab = Rapi::teks($x['jawab'] ?? '', 400);
            if ($tanya === '' && $jawab === '') continue;
            $mengapa[] = ['tanya' => $tanya, 'jawab' => $jawab];
            if (count($mengapa) >= 5) break;
        }

        $kategori = Rapi::pilihan($akar['kategori'] ?? '', array_keys(self::KATEGORI), 'manajemen');
        $korektif = self::aksi($d['korektif'] ?? [], 4);
        $preventif = self::aksi($d['preventif'] ?? [], 3);
        $hariAksi = max([0, ...array_column($korektif, 'hari'), ...array_column($preventif, 'hari')]);
        $hari     = Rapi::bulat($d['target_hari'] ?? null, 1, 365, $hariAksi ?: 30);
        $tanggal  = now()->startOfDay()->addDays($hari)->toDateString();
        $buktiTutup = Rapi::daftar($d['bukti_penutupan'] ?? [], 6, 300);
        $risiko   = Rapi::pilihan($d['tingkat_risiko'] ?? '', array_keys(self::RISIKO), 'sedang');
        $pernyataan = Rapi::teks($akar['pernyataan'] ?? '', 800);
        $koreksi  = Rapi::teks($d['koreksi_segera'] ?? '', 600);

        // Usulan isian formulir CAR — dijepit ke batas validasi simpanTemuan().
        $isianAkar = Rapi::gabung([
            '['.self::KATEGORI[$kategori].'] '.$pernyataan,
            $mengapa ? 'Analisis 5-Why:' : '',
            ...array_map(fn ($m, $i) => ($i + 1).'. '.$m['tanya'].' — '.$m['jawab'], $mengapa, array_keys($mengapa)),
        ], 2000);

        $isianTindakan = Rapi::gabung([
            $koreksi !== '' ? 'Koreksi segera: '.$koreksi : '',
            $korektif ? 'Tindakan korektif:' : '',
            ...array_map(fn ($a) => '- '.$a['tindakan'].self::rincianAksi($a), $korektif),
            $preventif ? 'Tindakan pencegahan:' : '',
            ...array_map(fn ($a) => '- '.$a['tindakan'].self::rincianAksi($a), $preventif),
        ], 2000);

        $isianVerifikasi = Rapi::gabung([
            $buktiTutup ? 'Bukti penutupan yang diperlukan:' : '',
            ...array_map(fn ($b) => '- '.$b, $buktiTutup),
        ], 2000);

        return [
            'tugas'   => 'temuan',
            'id'      => $this->temuan->id,
            'kode'    => $this->temuan->kode_kriteria,
            'akar'    => [
                'kategori'   => $kategori,
                'label'      => self::KATEGORI[$kategori],
                'mengapa'    => $mengapa,
                'pernyataan' => $pernyataan,
            ],
            'koreksi'   => $koreksi,
            'korektif'  => $korektif,
            'preventif' => $preventif,
            'target'    => ['hari' => $hari, 'tanggal' => $tanggal],
            'bukti_penutupan' => $buktiTutup,
            'risiko'    => [
                'tingkat' => $risiko,
                'label'   => self::RISIKO[$risiko],
                'alasan'  => Rapi::teks($d['alasan_risiko'] ?? '', 500),
            ],
            'isian' => [
                'akar_masalah'     => $isianAkar,
                'tindakan'         => $isianTindakan,
                'penanggung_jawab' => Rapi::teks($korektif[0]['pic'] ?? ($preventif[0]['pic'] ?? ''), 150),
                'target_selesai'   => $tanggal,
                'verifikasi'       => $isianVerifikasi,
            ],
            'model' => $j->model,
        ];
    }

    /** @return list<array{tindakan:string,pic:string,hari:int,bukti:string}> */
    private static function aksi(mixed $daftar, int $jumlah): array
    {
        $out = [];
        foreach (is_array($daftar) ? $daftar : [] as $a) {
            $tindakan = Rapi::teks($a['tindakan'] ?? '', 500);
            if ($tindakan === '') continue;
            $out[] = [
                'tindakan' => $tindakan,
                'pic'      => Rapi::teks($a['pic'] ?? '', 150),
                'hari'     => Rapi::bulat($a['hari'] ?? null, 1, 365, 30),
                'bukti'    => Rapi::teks($a['bukti_penutupan'] ?? '', 300),
            ];
            if (count($out) >= $jumlah) break;
        }

        return $out;
    }

    private static function rincianAksi(array $a): string
    {
        $r = array_filter([$a['pic'] !== '' ? 'PIC: '.$a['pic'] : '', $a['hari'].' hari']);

        return ' ('.implode(', ', $r).')';
    }
}
