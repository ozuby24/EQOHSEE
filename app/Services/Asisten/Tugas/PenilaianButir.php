<?php

namespace App\Services\Asisten\Tugas;

use App\Models\SmkpAudit;
use App\Services\Asisten\{Jawaban, KonteksAudit, Permintaan, Prompt, Rapi};
use App\Support\Smkp;

/**
 * Saran penilaian satu butir kriteria SMKP: nilai beserta alasannya, bukti
 * yang perlu diverifikasi, pertanyaan untuk auditi, dan draf pernyataan
 * temuan bila butir tampak belum terpenuhi.
 */
final class PenilaianButir
{
    public const JENIS_BUKTI = [
        'dokumen'   => 'Dokumen',
        'rekaman'   => 'Rekaman',
        'wawancara' => 'Wawancara',
        'observasi' => 'Observasi lapangan',
    ];

    public const KEYAKINAN = ['rendah', 'sedang', 'tinggi'];

    /**
     * @param array{elemen:array,sub:array,butir:array} $letak hasil KonteksAudit::butir()
     * @param array{v:string,ket:string,bukti:string}   $isian isian auditor saat ini (boleh belum disimpan)
     */
    public function __construct(
        private readonly SmkpAudit $audit,
        private readonly array $letak,
        private readonly array $isian,
    ) {}

    public static function skema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['nilai_saran', 'tidak_berlaku', 'keyakinan', 'alasan', 'bukti_verifikasi', 'pertanyaan', 'draf_temuan', 'catatan_ringkas'],
            'properties' => [
                'nilai_saran' => [
                    'type' => 'integer',
                    'description' => 'Saran nilai poin, bilangan bulat dari 0 sampai nilai maksimum butir, berdasarkan bukti yang tersedia saat ini.',
                ],
                'tidak_berlaku' => [
                    'type' => 'boolean',
                    'description' => 'true hanya bila butir jelas tidak berlaku bagi kegiatan perusahaan (N/A), misalnya tidak ada tambang bawah tanah.',
                ],
                'keyakinan' => [
                    'type' => 'string', 'enum' => self::KEYAKINAN,
                    'description' => 'Seberapa kuat bukti yang tersedia mendukung saran nilai.',
                ],
                'alasan' => [
                    'type' => 'string',
                    'description' => 'Alasan saran nilai, dikaitkan langsung dengan kriteria butir dan bukti/catatan auditor; sebut kekurangannya bila nilai belum penuh. 2-5 kalimat.',
                ],
                'bukti_verifikasi' => [
                    'type' => 'array',
                    'description' => '3-6 bukti objektif yang perlu diverifikasi auditor untuk butir ini.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['jenis', 'uraian'],
                        'properties' => [
                            'jenis'  => ['type' => 'string', 'enum' => array_keys(self::JENIS_BUKTI)],
                            'uraian' => ['type' => 'string', 'description' => 'Bukti spesifik yang diperiksa, mis. nama dokumen/rekaman, siapa yang diwawancarai (jabatan), atau lokasi observasi.'],
                        ],
                    ],
                ],
                'pertanyaan' => [
                    'type' => 'array',
                    'description' => '3-5 pertanyaan terbuka untuk auditi yang menguji penerapan butir ini.',
                    'items' => ['type' => 'string'],
                ],
                'draf_temuan' => [
                    'type' => 'string',
                    'description' => 'Draf pernyataan temuan (kondisi yang ditemukan, kriteria yang tidak terpenuhi, bukti) bila butir tampak belum terpenuhi penuh; string kosong bila butir tampak terpenuhi atau tidak berlaku.',
                ],
                'catatan_ringkas' => [
                    'type' => 'string',
                    'description' => 'Ringkasan paling banyak 400 karakter yang siap disalin ke kolom catatan auditor.',
                ],
            ],
        ];
    }

    public function permintaan(): Permintaan
    {
        ['elemen' => $e, 'sub' => $s, 'butir' => $b] = $this->letak;
        $maks  = (int) $b['maks'];
        $hasil = (array) ($this->audit->hasil ?? []);

        // Butir lain pada sub-elemen yang sama — konteks, tanpa catatan bebasnya.
        $saudara = [];
        foreach (Smkp::butirSub($s) as $x) {
            if ($x['kode'] === $b['kode']) continue;
            $saudara[] = "- {$x['kode']} {$x['nama']} (maks {$x['maks']}): ".KonteksAudit::nilaiTersimpan($hasil, $x);
        }

        $ref = ($s['ref'] ?? null) ? ", {$s['ref']}" : '';
        $teks = [
            'Tugas: tinjau satu butir kriteria audit SMKP Minerba dan beri saran penilaian kepada auditor.',
            '',
            'Konteks audit:',
            KonteksAudit::smkp($this->audit),
            '',
            "Kriteria (Lampiran II Kepdirjen Minerba 185.K/37.04/DJB/2019{$ref}):",
            "- Elemen {$e['kode']} {$e['nama']} (bobot {$e['bobot']})",
            "- Sub-elemen {$s['kode']} {$s['nama']}",
            "- Butir {$b['kode']} {$b['nama']} — skala nilai 0 sampai {$maks} poin",
            '',
            'Nilai auditor saat ini: '.KonteksAudit::nilai($this->isian['v'] ?? '', $maks),
            ...(isset(Smkp::perluDinilaiUlang([$b['kode'] => $hasil[$b['kode']] ?? null])[$b['kode']])
                ? ['Nilai tersimpan sebelumnya: '.KonteksAudit::nilaiTersimpan($hasil, $b).' — jangan dipakai sebagai nilai sekarang.']
                : []),
            'Bukti objektif yang dicatat auditor:',
            Prompt::data($this->isian['bukti'] ?? '', '(belum ada)'),
            'Catatan auditor:',
            Prompt::data($this->isian['ket'] ?? '', '(belum ada)'),
        ];

        if ($saudara) {
            array_push($teks, '', 'Butir lain pada sub-elemen yang sama:', ...$saudara);
        }

        if ($kriteria = self::kriteriaNilai($b['kode'])) {
            array_push($teks, '', 'Kriteria penilaian resmi butir ini — pilih nilai yang deskripsinya paling sesuai dengan bukti:', ...$kriteria);
        }

        array_push($teks, '',
            "Nilai penuh ({$maks}) hanya bila bukti menunjukkan butir ditetapkan, diterapkan, dan dipelihara. ".
            'Bila bukti dan catatan masih kosong atau tidak cukup, beri nilai_saran konservatif, keyakinan "rendah", dan fokuskan jawaban pada bukti yang perlu diperiksa.'
        );

        return new Permintaan('butir', Prompt::sistem(), implode("\n", $teks), self::skema());
    }

    /**
     * Deskripsi tiap nilai butir dari Kepdirjen (rubrik.json), satu baris per
     * nilai. Butir dengan dua versi (pemegang IUP / IUJP) menyertakan keduanya.
     *
     * @return array<int,string>
     */
    private static function kriteriaNilai(string $kode): array
    {
        $r = Smkp::rubrik($kode);
        if (!$r) return [];

        $baris = [];
        foreach ($r['varian'] ?? [['label' => null] + $r] as $v) {
            if ($v['label']) $baris[] = "Untuk {$v['label']}:";
            foreach ($v['nilai'] ?? [] as $n) {
                $baris[] = "- Nilai {$n['n']}: ".str_replace("\n", ' ', $n['teks']);
            }
            if (!empty($v['na'])) $baris[] = '- N/A: '.$v['na'];
        }
        if (!empty($r['catatan'])) $baris[] = 'Catatan: '.$r['catatan'];
        $baris[] = "(Kepdirjen 185.K/37.04/DJB/2019 hal. {$r['hal']})";

        return $baris;
    }

    /** Bentuk jawaban yang dikirim ke peramban. */
    public function rapikan(Jawaban $j): array
    {
        $d    = $j->data;
        $b    = $this->letak['butir'];
        $maks = (int) $b['maks'];
        $na   = ($d['tidak_berlaku'] ?? false) === true;

        $bukti = [];
        foreach (is_array($d['bukti_verifikasi'] ?? null) ? $d['bukti_verifikasi'] : [] as $x) {
            $uraian = Rapi::teks($x['uraian'] ?? '', 400);
            if ($uraian === '') continue;
            $jenis = Rapi::pilihan($x['jenis'] ?? '', array_keys(self::JENIS_BUKTI), 'dokumen');
            $bukti[] = ['jenis' => $jenis, 'label' => self::JENIS_BUKTI[$jenis], 'uraian' => $uraian];
            if (count($bukti) >= 8) break;
        }

        $saran = Rapi::bulat($d['nilai_saran'] ?? null, 0, $maks, 0);

        return [
            'tugas' => 'butir',
            'kode'  => $b['kode'],
            'maks'  => $maks,
            'nilai' => [
                'saran'     => $na ? null : $saran,
                'na'        => $na,
                'v'         => $na ? Smkp::NA : (string) $saran,
                'keyakinan' => Rapi::pilihan($d['keyakinan'] ?? '', self::KEYAKINAN, 'rendah'),
                'alasan'    => Rapi::teks($d['alasan'] ?? '', 1500),
            ],
            'bukti'       => $bukti,
            'pertanyaan'  => Rapi::daftar($d['pertanyaan'] ?? [], 6, 300),
            'draf_temuan' => Rapi::teks($d['draf_temuan'] ?? '', 1500),
            'catatan'     => Rapi::teks($d['catatan_ringkas'] ?? '', 600),
            'model'       => $j->model,
        ];
    }
}
