<?php

namespace App\Http\Controllers;

use App\Models\{Company, HazardReport, Inspection};
use App\Support\{Db, Ekspor, Hazard, RegisterPerbaikan, Waktu};
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HazardExportController extends Controller
{
    /* ---------- Ekspor Hazard Report ---------- */
    public function hazardCsv(Request $r)
    {
        $data = $this->saringHazard($r)->get();

        $baris = $data->map(fn($h) => [
            $h->kode,
            optional($h->tanggal)->format('Y-m-d'),
            $h->waktu, $h->pelapor_nama, $h->pelapor_nrp, $h->pelapor_jabatan,
            Hazard::golongan($h->pelapor_jabatan), $h->pelapor_departemen, $h->pelapor_perusahaan,
            $h->company?->name ?: $h->terlapor, $h->lokasi, $h->risiko, $h->kategori,
            $h->deskripsi,
            implode(' | ', $h->unsafe_action_list),
            implode(' | ', $h->unsafe_condition_list),
            $h->hirarki, $h->rekomendasi, $h->status,
            $h->catatan_penutupan, optional($h->closed_at)->format('Y-m-d H:i'),
        ]);

        return Ekspor::csv('hazard-report-'.date('Ymd-Hi'), [
            'Kode','Tanggal','Waktu','Pelapor','NRP','Jabatan','Golongan','Departemen','Perusahaan Pelapor',
            'Ditujukan Kepada','Lokasi','Risiko','Kategori','Deskripsi',
            'Bentuk Unsafe Action','Bentuk Unsafe Condition','Hirarki','Rekomendasi','Status',
            'Catatan Penutupan','Ditutup Pada',
        ], $baris);
    }

    /* ---------- Register Tindakan Perbaikan (.xlsx) ---------- */

    /**
     * Berapa temuan yang akan ikut terbawa pilihan sekarang.
     *
     * Rute JSON tersendiri, bukan muat ulang sebagian halaman monitor.
     * Yang ditanyakan hanya SATU ANGKA; memuat ulang halamannya berarti
     * menjalankan kembali penyaringan, penghitungan kartu, dan
     * paginasinya setiap kali satu pilihan digeser di dalam dialog —
     * pekerjaan yang seluruhnya dibuang.
     */
    public function registerJumlah(Request $r)
    {
        return response()->json(['jumlah' => $this->saringRegister($r)->count()]);
    }

    /**
     * Unduh Register Tindakan Perbaikan sebagai berkas Excel.
     *
     * Dikirim sebagai aliran (stream), bukan disimpan dulu ke berkas
     * sementara. Register berfoto tertanam berukuran beberapa megabita,
     * dan berkas sementara yang gagal terhapus — permintaan yang putus
     * di tengah, proses yang dimatikan — menumpuk di diska server tanpa
     * ada yang membersihkannya.
     */
    public function register(Request $r): StreamedResponse
    {
        $data = $this->saringRegister($r)->get();

        $penyusun = new RegisterPerbaikan(
            $data,
            $this->perusahaanKop($r),
            [
                'urutan'     => $r->get('urutan', 'bulan'),
                'perusahaan' => $r->get('perusahaan'),
                'bulan'      => $r->get('bulan'),
                'lokasi'     => $r->get('lokasi'),
            ],
        );

        $nama = $penyusun->namaBerkas();
        $buku = $penyusun->spreadsheet();

        return response()->streamDownload(function () use ($buku) {
            $tulis = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($buku);
            $tulis->save('php://output');

            /* Dilepas SESUDAH ditulis. PhpSpreadsheet menahan setiap
               gambar sebagai sumber daya GD di memori; register berisi
               empat puluh foto yang tidak dilepas meninggalkan puluhan
               megabita tergenggam sampai prosesnya berakhir — dan pada
               antrian yang melayani banyak unduhan berturut-turut, itu
               berakhir sebagai kehabisan memori pada unduhan yang
               kebetulan ketiga belas. */
            $buku->disconnectWorksheets();
        }, $nama, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Penyaring register — TERPISAH dari penyaring daftar monitor.
     *
     * Keduanya menyaring tabel yang sama tetapi menjawab pertanyaan yang
     * berbeda: monitor menjawab "apa yang sedang saya lihat", register
     * menjawab "apa yang saya serahkan ke rapat". Menyatukannya membuat
     * kotak cari yang sedang terisi di layar diam-diam ikut memotong
     * lembar yang diserahkan — dan yang menerimanya tidak punya cara
     * mengetahui bahwa ada yang hilang.
     */
    private function saringRegister(Request $r)
    {
        $q = HazardReport::with(['company', 'user'])
            ->when($r->filled('bulan'),      fn ($b) => $b->whereRaw(Db::ym('tanggal').' = ?', [$r->bulan]))
            ->when($r->filled('lokasi'),     fn ($b) => $b->where('lokasi', $r->lokasi))
            ->when($r->filled('perusahaan'), fn ($b) => $b->where('company_id', $r->perusahaan));

        /* Urutan KEDUA selalu tanggal, apa pun urutan pertamanya.
           Tanpa itu, dua temuan pada lokasi yang sama tampil dalam
           urutan yang ditentukan basis data — yang berbeda antara dua
           unduhan atas data yang sama, dan membuat dua lembar yang
           seharusnya identik tidak dapat dibandingkan. */
        return match ($r->get('urutan', 'bulan')) {
            'lokasi'     => $q->orderBy('lokasi')->orderBy('tanggal'),
            'perusahaan' => $q->orderBy('company_id')->orderBy('tanggal'),
            'risiko'     => $q->orderByRaw(Db::urutanNilai('risiko', ['Tinggi', 'Sedang', 'Rendah']))->orderBy('tanggal'),
            'status'     => $q->orderByRaw(Db::urutanNilai('status', ['Open', 'In Progress', 'Closed']))->orderBy('tanggal'),
            default      => $q->orderBy('tanggal'),
        };
    }

    /** Halaman siap cetak → simpan sebagai PDF lewat dialog cetak peramban. */
    public function hazardCetak(Request $r)
    {
        $data = $this->saringHazard($r)->get();
        return Inertia::render('Print/Hazard', [
            'dok'  => \App\Support\KopDokumen::untuk('register-hazard', $this->perusahaanKop($r)),
            'data' => $data,
            'filters' => $r->query(),
            'kembali' => route('hazard.index', $r->query()),
        ]);
    }

    /* ---------- Ekspor Inspeksi ---------- */
    public function inspeksiCsv(Request $r)
    {
        $q = Inspection::with(['company','template','inspectors','items']);
        if ($r->filled('status'))   $q->where('status', $r->status);
        if ($r->filled('template')) $q->where('template_id', $r->template);

        $baris = [];
        foreach ($q->get() as $i) {
            $inspektur = $i->inspectors->map(fn($p) => $p->nama.' ('.$p->peran.')')->implode(', ');
            foreach ($i->items as $it) {
                $baris[] = [
                    $i->kode, optional($i->tanggal)->format('Y-m-d'), $i->template?->nama, $i->judul,
                    $i->company?->name, $i->lokasi, $i->status, $inspektur,
                    $it->kelompok, $it->uraian, $it->acuan, $it->kondisi, $it->risiko,
                    $it->temuan, $it->tindakan,
                    $it->hazard_report_id ? 'Ya' : 'Tidak',
                ];
            }
        }

        return Ekspor::csv('inspeksi-'.date('Ymd-Hi'), [
            'Kode','Tanggal','Jenis','Judul','Perusahaan','Lokasi','Status','Inspektur',
            'Kelompok','Parameter','Acuan','Kondisi','Risiko','Temuan','Tindakan','Naik ke Hazard Report',
        ], $baris);
    }

    public function inspeksiCetak(Request $r)
    {
        $q = Inspection::with(['company','template','inspectors','items']);
        if ($r->filled('status'))   $q->where('status', $r->status);
        if ($r->filled('template')) $q->where('template_id', $r->template);

        return Inertia::render('Print/Inspeksi', [
            'dok'  => \App\Support\KopDokumen::untuk('register-inspeksi', $this->perusahaanKop($r)),
            'data' => $q->latest('tanggal')->get(),
            'filters' => $r->query(),
            'kembali' => route('inspeksi.index', $r->query()),
        ]);
    }

    /* ---------- Pengingat tindak lanjut ---------- */
    /**
     * Pengingat tindak lanjut per perusahaan.
     *
     * Isi pesannya disusun di sini, bukan di tampilan. Sebelumnya seluruh
     * perakitan teks — sapaan, hitungan, daftar temuan — tertulis di dalam
     * view, sehingga logika yang menentukan bunyi pesan resmi ke PIC
     * bercampur dengan penataan letaknya dan tidak dapat diuji tanpa
     * merender halaman.
     */
    public function pengingat()
    {
        $daftar = Company::orderBy('name')->get()->map(function (Company $c) {
            $terbuka = HazardReport::where('company_id', $c->id)->where('status', '<>', 'Closed')
                        ->orderBy('tanggal')->get();

            if ($terbuka->isEmpty()) return null;

            $tinggi = $terbuka->where('risiko', 'Tinggi')->count();
            $lama   = $terbuka->filter(fn ($h) => $h->tanggal && $h->tanggal->diffInDays(now()) > 14)->count();

            $judul = 'Pengingat Tindak Lanjut Temuan — '.$c->name;

            $baris = $terbuka->take(20)->map(fn ($h, $i) =>
                ($i + 1).'. ['.$h->kode.'] '.$h->risiko.' — '
                .\Illuminate\Support\Str::limit($h->deskripsi, 70)
                .' (📍'.($h->lokasi ?: '-').', '.optional($h->tanggal)->format('d/m/Y')
                .', status '.$h->status.')')->implode("\n");

            $pesan = "*{$judul}*\n\n"
                ."Kepada Yth. ".($c->pic_name ?: 'PIC '.$c->name).",\n\n"
                ."Terdapat *{$terbuka->count()} temuan* yang belum ditutup"
                .($tinggi ? ", termasuk *{$tinggi} berisiko tinggi*" : '')
                .($lama ? ", dan *{$lama} sudah lebih dari 14 hari*" : '')
                .".\n\n{$baris}\n\n"
                ."Mohon segera ditindaklanjuti dan diperbarui statusnya pada sistem EQOHSEE.\n\n"
                ."— Tim HSE EQOHSEE";

            return [
                'id'     => $c->id,
                'nama'   => $c->name,
                'pic'    => [
                    'nama'    => $c->pic_name ?: null,
                    'email'   => $c->pic_email ?: null,
                    'telepon' => $c->pic_phone ?: null,
                ],
                'jumlah' => $terbuka->count(),
                'tinggi' => $tinggi,
                'lama'   => $lama,
                'pesan'  => $pesan,

                'wa'         => Ekspor::waLink($pesan, $c->waNumber()),
                'punyaNomor' => (bool) $c->waNumber(),
                'mail'       => $c->pic_email ? Ekspor::mailLink($c->pic_email, $judul, $pesan) : null,
                'urlLihat'   => route('hazard.index', ['perusahaan' => $c->id, 'status' => 'Open']),
                'urlEdit'    => route('admin.companies.edit', $c),
            ];
        })->filter()->values()->all();

        return \Inertia\Inertia::render('Hazard/Pengingat', [
            'judul'    => 'Pengingat Tindak Lanjut',
            'subjudul' => 'Temuan yang belum ditutup, per perusahaan',

            'perusahaan'  => $daftar,
            'urlPerusahaan' => route('admin.companies.index'),
        ]);
    }

    private function saringHazard(Request $r)
    {
        return HazardReport::with('company')
            ->when($r->filled('q'), fn($b) => $b->where(fn($w) => $w
                ->where('kode','like','%'.$r->q.'%')
                ->orWhere('deskripsi','like','%'.$r->q.'%')
                ->orWhere('lokasi','like','%'.$r->q.'%')))
            ->when($r->filled('bulan'),      fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$r->bulan]))
            ->when($r->filled('kategori'),   fn($b) => $b->where('kategori', $r->kategori))
            ->when($r->filled('risiko'),     fn($b) => $b->where('risiko', $r->risiko))
            ->when($r->filled('status'),     fn($b) => $b->where('status', $r->status))
            ->when($r->filled('perusahaan'), fn($b) => $b->where('company_id', $r->perusahaan))
            ->latest('tanggal');
    }
}
