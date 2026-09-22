<?php

namespace App\Support;

use App\Models\Company;
use App\Models\ComplianceSubject;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Register Pemenuhan — lembar Excel yang diserahkan ke luar.
 *
 * Lembar cetak yang sudah ada memuat SATU peraturan beserta pasalnya:
 * ia ditandatangani dan diarsipkan sebagai bukti evaluasi naskah itu.
 * Yang dibutuhkan di rapat dan pada audit eksternal adalah kebalikannya
 * — seluruh kewajiban dalam satu tahun, berdampingan, dapat disaring
 * dan diurutkan sendiri oleh yang membacanya.
 *
 * ── Satu baris per BUTIR, bukan per peraturan ──
 *
 * Yang dinilai comply atau tidak adalah pasalnya, bukan undang-undangnya.
 * Diringkas menjadi satu baris per peraturan, kolom statusnya harus
 * memuat "4 comply, 1 not comply, 1 N/A" sebagai teks — dan teks itu
 * tidak dapat disaring, tidak dapat dihitung, dan tidak dapat diurutkan
 * oleh siapa pun yang menerimanya. Pasal yang tidak dipenuhi, yang
 * justru menjadi alasan lembar ini dikirim, tenggelam di dalamnya.
 *
 * ── Peraturan yang belum dirinci tetap mendapat barisnya ──
 *
 * Dilewati, ia hilang tanpa jejak dari lembar yang seharusnya
 * memperlihatkan seluruh kewajiban — dan yang membacanya menyimpulkan
 * peraturan itu memang belum diidentifikasi. Ia muncul dengan penunjuk
 * "(belum dirinci butirnya)" dan status kosong.
 *
 * ── "Belum dinilai" tidak pernah ditulis sebagai nol ──
 *
 * Persentase dihitung App\Support\Kepatuhan, yang memulangkan null bila
 * belum ada satu pun yang dinilai. Null ditulis sebagai tanda hubung.
 * Nol pada kolom persentase berarti "seluruhnya tidak comply" — sebuah
 * pernyataan yang jauh lebih keras, dan salah.
 */
final class RegisterKepatuhan
{
    /** Lebar kolom A–N, dalam satuan Excel. */
    private const LEBAR = [5, 13, 16, 28, 30, 18, 15, 40, 40, 14, 26, 28, 18, 13];

    private const JUDUL_KOLOM = [
        'No',
        'Aspek',
        'Jenis',
        'Nomor Peraturan / Standar',
        'Judul',
        'Instansi Penerbit',
        'Pasal / Klausul',
        'Rangkuman Kewajiban',
        'Penerapan di Perusahaan',
        'Status Pemenuhan',
        'Keterangan / Bukti',
        'Rencana Tindak Lanjut',
        'Penanggung Jawab (PIC)',
        'Target',
    ];

    /** Kolom terakhir yang dipakai — dihitung sekali, dipakai di mana-mana. */
    private const KOLOM_AKHIR = 'N';

    /** Warna latar dan huruf tiap status pemenuhan. */
    private const WARNA = [
        'Comply'     => ['DCFCE7', '15803D'],
        'Not Comply' => ['FEE2E2', 'B91C1C'],
        'N/A'        => ['F1F0EF', '78716C'],
        'belum'      => ['FEF3C7', '92400E'],
    ];

    /**
     * @param Collection<int,ComplianceSubject> $data  sudah memuat `points`
     * @param array{tahun?:int,sumber?:string|null,aspek?:string|null} $pilihan
     */
    public function __construct(
        private Collection $data,
        private ?Company $perusahaan,
        private array $pilihan = [],
    ) {}

    public function namaBerkas(): string
    {
        $bagian = ['Register-Pemenuhan', (string) ($this->pilihan['tahun'] ?? date('Y'))];

        if ($this->pilihan['sumber'] ?? null) $bagian[] = $this->pilihan['sumber'];

        return implode('-', $bagian).'-'.date('Ymd-Hi').'.xlsx';
    }

    public function spreadsheet(): Spreadsheet
    {
        $buku = new Spreadsheet();

        $l = $buku->getActiveSheet();
        $l->setTitle('Register Pemenuhan');

        foreach (self::LEBAR as $i => $lebar) {
            $l->getColumnDimensionByColumn($i + 1)->setWidth($lebar);
        }

        $baris = $this->kop($l);
        $this->tabel($l, $baris);

        /* Dibekukan di bawah baris judul tabel. Register lima puluh butir
           tidak muat satu layar, dan empat belas kolom teks yang digulir
           tanpa judulnya tidak dapat dibedakan satu sama lain. */
        $l->freezePane('A'.($this->barisJudul + 1));

        $l->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $l->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);

        /* TIDAK dipaksa muat satu halaman lebar.

           Keempat belas kolomnya berjumlah tiga ratus karakter;
           selembar A4 mendatar memuat sekitar seratus sepuluh. Dipaksa
           muat, Excel menyusutkannya ke seperempat ukuran — sebuah
           halaman bertuliskan huruf tiga titik yang tidak dapat dibaca
           siapa pun, dan itu diperiksa dengan mencetaknya, bukan dengan
           melihat layarnya. Dibiarkan mengalir, ia menjadi tiga halaman
           mendatar yang terbaca.

           Tingginya tetap dibiarkan mengalir (setFitToHeight(0)):
           register empat ratus baris memang berhalaman banyak. */
        $l->getPageSetup()->setFitToWidth(0)->setFitToHeight(0);
        $l->getPageSetup()->setScale(85);

        $l->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($this->barisJudul, $this->barisJudul);

        /* Kolom identitas diulang di tepi kiri tiap halaman. Halaman
           kedua sebuah register — yang memuat kolom Status sampai
           Target tanpa satu pun nomor peraturannya — adalah halaman
           yang harus ditempelkan ke halaman pertama untuk dibaca. */
        $l->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A', 'D');

        $this->lembarRekap($buku);

        /* Lembar pertama yang aktif saat dibuka, bukan lembar rekap yang
           baru saja disusun. PhpSpreadsheet meninggalkan penunjuknya di
           lembar terakhir yang disentuh. */
        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    private int $barisJudul = 10;

    /* ═════════════ kop ═════════════ */

    /**
     * Kop terkendali — seluruhnya di dalam kolom A–D.
     *
     * Empat belas kolom register berjumlah tiga ratus karakter, jadi ia
     * tercetak menjadi tiga halaman MENDATAR, dan kolom A–D diulang di
     * tepi kiri masing-masing supaya tiap baris tetap dapat dikenali.
     * Kop yang lebih lebar daripada kolom yang diulang itu akan
     * TERPOTONG di batasnya: halaman kedua berjudul "IDENTIFIKASI DAN
     * EVALUASI PEM" dan berhenti di situ.
     *
     * Dipendekkan sampai kolom D, ia justru ikut terulang utuh di tiap
     * halaman — yang memang seharusnya terjadi pada formulir
     * terkendali: halaman yang lepas dari berkasnya masih menyebut
     * nomor dokumen dan perusahaannya sendiri.
     */
    private function kop(Worksheet $l): int
    {
        $dok = KopDokumen::untuk('evaluasi-pemenuhan', $this->perusahaan);
        $r = $this->ringkas();

        $l->mergeCells('A1:B3');
        $l->mergeCells('C1:D1');
        $l->mergeCells('C2:D3');

        $l->setCellValue('C1', $dok['jenis']);
        $l->setCellValue('C2', $dok['judul']);

        $l->getStyle('C1')->getFont()->setBold(true)->setSize(10);
        $l->getStyle('C2')->getFont()->setBold(true)->setSize(12);
        $l->getStyle('C1:D3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $l->getRowDimension(1)->setRowHeight(16);
        $l->getRowDimension(2)->setRowHeight(22);
        $l->getRowDimension(3)->setRowHeight(22);

        $this->logo($l);

        /* Baris identitas. Tanpa lingkup dan angka pemenuhannya,
           register tidak dapat dipertanggungjawabkan: yang membacanya
           tidak tahu apakah ia melihat seluruh kewajiban tahun itu atau
           satu aspeknya saja. */
        $isi = [
            ['No. Dokumen',  $dok['nomor'] ?: '—'],
            ['Terbit / Ubah', $this->tanggalPanjang($dok['terbit']).'  /  '.$this->tanggalPanjang($dok['setuju'])],
            ['Perusahaan',   $this->perusahaan?->name ?: 'Seluruh perusahaan'],
            ['Lingkup',      'Tahun '.($this->pilihan['tahun'] ?? date('Y'))
                             .'  ·  '.($this->pilihan['sumber'] ?: 'seluruh sumber')
                             .'  ·  '.($this->pilihan['aspek']
                                 ? Kepatuhan::namaAspek($this->pilihan['aspek']) : 'seluruh aspek')
                             .'  ·  dicetak '.Waktu::kini()->translatedFormat('d M Y H:i')],
            ['Pemenuhan',    $this->persenTeks($r['persen'])
                             .'  ·  '.$r['comply'].' C / '.$r['notComply'].' NC / '.$r['na'].' N/A / '
                             .$r['belum'].' belum, dari '.$this->data->count().' kewajiban'],
        ];

        $b = 4;

        foreach ($isi as [$label, $nilai]) {
            $l->mergeCells("A{$b}:B{$b}");
            $l->mergeCells("C{$b}:D{$b}");
            $l->setCellValue("A{$b}", $label);
            $l->setCellValue("C{$b}", ': '.$nilai);
            $l->getRowDimension($b)->setRowHeight(15);
            $b++;
        }

        /* Dua baris terakhir memuat kalimat terpanjang dan dibiarkan
           membungkus ke baris kedua; sisanya cukup satu baris. */
        foreach (['C7', 'C8'] as $sel) {
            $l->getStyle($sel)->getAlignment()->setWrapText(true);
            $l->getRowDimension((int) substr($sel, 1))->setRowHeight(24);
        }

        $l->getStyle('A4:D8')->getFont()->setSize(9.5);
        $l->getStyle('A4:B8')->getFont()->setBold(true);
        $l->getStyle('A4:D8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $l->getStyle('A1:D8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return 10;
    }

    /**
     * Logo perusahaan di kotak kiri atas.
     *
     * Dilewati diam-diam bila belum ada, atau bila berkasnya tidak dapat
     * dibaca. Berkas yang GAGAL TERBIT karena logo tidak ada jauh lebih
     * merugikan daripada kop tanpa logo.
     */
    private function logo(Worksheet $l): void
    {
        $jalur = $this->perusahaan?->effectiveLogo();

        if (!$jalur) return;

        $berkas = \Illuminate\Support\Facades\Storage::disk(Berkas::TERBUKA)->path($jalur);

        if (!is_file($berkas)) return;

        try {
            $gambar = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $gambar->setPath($berkas);
            $gambar->setOffsetX(8);
            $gambar->setOffsetY(4);
            $gambar->setCoordinates('A1');
            $gambar->setResizeProportional(true);
            $gambar->setHeight(46);
            $gambar->setWorksheet($l);
        } catch (\Throwable) {
            // Logo rusak atau berformat tak dikenal — registernya tetap terbit.
        }
    }

    /* ═════════════ tabel ═════════════ */

    private function tabel(Worksheet $l, int $baris): void
    {
        $this->barisJudul = $baris;
        $akhirKol = self::KOLOM_AKHIR;

        foreach (self::JUDUL_KOLOM as $i => $judul) {
            $l->setCellValue([$i + 1, $baris], $judul);
        }

        $l->getStyle("A{$baris}:{$akhirKol}{$baris}")->getFont()->setBold(true)->setSize(10)
            ->getColor()->setRGB('FFFFFF');
        $l->getStyle("A{$baris}:{$akhirKol}{$baris}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $l->getStyle("A{$baris}:{$akhirKol}{$baris}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $l->getStyle("A{$baris}:{$akhirKol}{$baris}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $l->getRowDimension($baris)->setRowHeight(34);

        $baris++;
        $no = 1;

        foreach ($this->data as $s) {
            $butir = $s->points->sortBy('order_index')->values();

            if ($butir->isEmpty()) {
                $this->barisSubjek($l, $baris, $no, $s);
                $l->setCellValue("G{$baris}", '(belum dirinci butirnya)');
                $l->getStyle("G{$baris}")->getFont()->setItalic(true)->getColor()->setRGB('A8A29E');
                $this->warnaStatus($l, $baris, null, kosong: true);

                $baris++;
                $no++;
                continue;
            }

            foreach ($butir as $p) {
                $this->barisSubjek($l, $baris, $no, $s);

                $l->setCellValue("G{$baris}", (string) $p->penunjuk);
                $l->setCellValue("H{$baris}", (string) ($p->rangkuman ?: '—'));
                $l->setCellValue("I{$baris}", (string) ($p->penerapan ?: '—'));
                $l->setCellValue("J{$baris}", $p->status ?: 'Belum dinilai');
                $l->setCellValue("K{$baris}", (string) ($p->keterangan ?: '—'));
                $l->setCellValue("L{$baris}", (string) ($p->tindak_lanjut ?: '—'));
                $l->setCellValue("M{$baris}", (string) ($p->pic ?: '—'));

                /* Target kosong ditulis tegas, bukan dibiarkan kosong.
                   Sel kosong pada kolom tenggat terbaca sebagai kolom
                   yang lupa diisi saat mencetak, dan yang membacanya
                   akan mencari tenggatnya di tempat lain. */
                $l->setCellValue("N{$baris}", $p->target
                    ? $p->target->translatedFormat('d-M-y')
                    : ($p->status === 'Not Comply' ? 'Belum ditetapkan' : '—'));

                $this->warnaStatus($l, $baris, $p->status);

                /* Target yang sudah lewat sementara butirnya masih Not
                   Comply DISOROT. Register yang tidak membedakannya
                   menyerahkan pekerjaan itu kepada yang membacanya — dan
                   yang membacanya adalah orang yang harus ditagih. */
                if ($p->status === 'Not Comply' && $p->target && $p->target->isPast()) {
                    $l->getStyle("N{$baris}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
                    $l->getStyle("N{$baris}")->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
                }

                $baris++;
                $no++;
            }
        }

        $akhir = $baris - 1;

        if ($akhir < $this->barisJudul + 1) return;

        $julat = 'A'.($this->barisJudul + 1).":{$akhirKol}{$akhir}";

        $l->getStyle($julat)->getFont()->setSize(9);
        $l->getStyle($julat)->getAlignment()->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
        $l->getStyle($julat)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (['A', 'B', 'J', 'N'] as $kolom) {
            $l->getStyle($kolom.($this->barisJudul + 1).":{$kolom}{$akhir}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_TOP);
        }

        $l->setAutoFilter('A'.$this->barisJudul.":{$akhirKol}{$akhir}");
    }

    /** Kolom identitas peraturannya — diulang pada tiap barisnya. */
    private function barisSubjek(Worksheet $l, int $baris, int $no, ComplianceSubject $s): void
    {
        $l->setCellValue("A{$baris}", $no);
        $l->setCellValue("B{$baris}", Kepatuhan::namaAspek($s->aspek));
        $l->setCellValue("C{$baris}", (string) ($s->jenis ?: Kepatuhan::SUMBER[$s->sumber] ?? $s->sumber));
        $l->setCellValue("D{$baris}", (string) $s->nomor);
        $l->setCellValue("E{$baris}", (string) $s->judul);
        $l->setCellValue("F{$baris}", (string) ($s->instansi ?: '—'));

        /* Naskah berstatus Draf ditandai di kolom nomornya. Ia hasil
           rangkuman yang belum diperiksa orang, tidak ikut menentukan
           angka mana pun di aplikasi — dan lembar yang tidak
           menyebutkannya menyerahkannya ke rapat sebagai kewajiban yang
           sudah disepakati. */
        if ($s->status === 'Draf') {
            $l->setCellValue("D{$baris}", $s->nomor.'  [DRAF — belum diperiksa]');
            $l->getStyle("D{$baris}")->getFont()->setItalic(true)->getColor()->setRGB('92400E');
        }
    }

    private function warnaStatus(Worksheet $l, int $baris, ?string $status, bool $kosong = false): void
    {
        if ($kosong) {
            $l->setCellValue("J{$baris}", '—');
            $l->getStyle("J{$baris}")->getFont()->getColor()->setRGB('A8A29E');

            return;
        }

        [$latar, $huruf] = self::WARNA[$status] ?? self::WARNA['belum'];

        $l->getStyle("J{$baris}")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($latar);
        $l->getStyle("J{$baris}")->getFont()->setBold(true)->getColor()->setRGB($huruf);
    }

    /* ═════════════ lembar rekap ═════════════ */

    /**
     * Lembar kedua: capaian per aspek.
     *
     * Bukan hiasan. Yang menerima register empat ratus baris tidak akan
     * menjumlahkannya sendiri, dan angka yang dihitungnya sendiri di
     * samping lembar ini akan berbeda dari angka yang ditampilkan
     * aplikasi — sebab ia tidak tahu bahwa N/A tidak ikut menjadi
     * pembagi.
     */
    private function lembarRekap(Spreadsheet $buku): void
    {
        $l = $buku->createSheet();
        $l->setTitle('Rekap per Aspek');

        foreach ([28, 12, 14, 13, 10, 16, 16] as $i => $lebar) {
            $l->getColumnDimensionByColumn($i + 1)->setWidth($lebar);
        }

        $l->mergeCells('A1:G1');
        $l->setCellValue('A1', 'CAPAIAN PEMENUHAN PER ASPEK — TAHUN '.($this->pilihan['tahun'] ?? date('Y')));
        $l->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $l->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $l->getRowDimension(1)->setRowHeight(26);

        $judul = ['Aspek', 'Kewajiban', 'Total Butir', 'Comply', 'Not Comply', 'N/A', 'Belum Dinilai'];

        foreach ($judul as $i => $t) $l->setCellValue([$i + 1, 3], $t);
        $l->setCellValue('H3', 'Pemenuhan');
        $l->getColumnDimension('H')->setWidth(14);

        $l->getStyle('A3:H3')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
        $l->getStyle('A3:H3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $l->getStyle('A3:H3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $l->getRowDimension(3)->setRowHeight(30);

        $baris = 4;

        foreach ($this->perAspek() as $a) {
            $l->setCellValue("A{$baris}", $a['nama']);
            $l->setCellValue("B{$baris}", $a['kewajiban']);
            $l->setCellValue("C{$baris}", $a['total']);
            $l->setCellValue("D{$baris}", $a['comply']);
            $l->setCellValue("E{$baris}", $a['notComply']);
            $l->setCellValue("F{$baris}", $a['na']);
            $l->setCellValue("G{$baris}", $a['belum']);
            $l->setCellValue("H{$baris}", $this->persenTeks($a['persen']));

            if ($a['belum'] > 0) {
                $l->getStyle("G{$baris}")->getFont()->setBold(true)->getColor()->setRGB('92400E');
            }

            $baris++;
        }

        $r = $this->ringkas();

        $l->setCellValue("A{$baris}", 'SELURUHNYA');
        $l->setCellValue("B{$baris}", $this->data->count());
        $l->setCellValue("C{$baris}", $r['total']);
        $l->setCellValue("D{$baris}", $r['comply']);
        $l->setCellValue("E{$baris}", $r['notComply']);
        $l->setCellValue("F{$baris}", $r['na']);
        $l->setCellValue("G{$baris}", $r['belum']);
        $l->setCellValue("H{$baris}", $this->persenTeks($r['persen']));

        $l->getStyle("A{$baris}:H{$baris}")->getFont()->setBold(true);
        $l->getStyle("A{$baris}:H{$baris}")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F1F0EF');

        $l->getStyle("A3:H{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $l->getStyle("B4:H{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $baris += 2;
        $l->mergeCells("A{$baris}:H".($baris + 2));
        $l->setCellValue("A{$baris}",
            'Persentase pemenuhan dihitung dari butir yang BENAR-BENAR DINILAI: comply dibagi '
            .'(comply + not comply). Butir berstatus N/A tidak ikut menjadi pembagi, sebab kewajiban '
            .'yang tidak mengikat kegiatan perusahaan bukan kewajiban yang gagal dipenuhi. Butir yang '
            .'belum dinilai juga tidak ikut — dan karena itu jumlahnya disebut terpisah pada kolom '
            .'terakhir: register yang baru sepertiga dinilai dapat menunjukkan seratus persen '
            .'pemenuhan, dan angka itu benar sekaligus menyesatkan.');
        $l->getStyle("A{$baris}")->getAlignment()->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
        $l->getStyle("A{$baris}")->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('78716C');
    }

    /* ═════════════ hitungan ═════════════ */

    /** Rekap seluruh butir pada data yang terbawa. */
    private function ringkas(): array
    {
        return Kepatuhan::rekap($this->statusDari($this->data));
    }

    /** Rekap per aspek — hanya aspek yang memang punya kewajiban. */
    private function perAspek(): array
    {
        $out = [];

        foreach ($this->data->groupBy(fn (ComplianceSubject $s) => (string) $s->aspek) as $aspek => $grup) {
            $out[] = ['nama' => Kepatuhan::namaAspek($aspek ?: null), 'kewajiban' => $grup->count()]
                + Kepatuhan::rekap($this->statusDari($grup));
        }

        usort($out, fn ($a, $b) => strcmp($a['nama'], $b['nama']));

        return $out;
    }

    /**
     * Status seluruh butir pada sederet kewajiban.
     *
     * Naskah berstatus Draf DILEWATI, persis seperti di dasbor dan di
     * rekap bulanan. Ia hasil rangkuman yang belum diperiksa orang, dan
     * angka yang ikut menghitungnya berbeda dari angka yang sama di
     * layar — tanpa ada cara mengetahui mana yang benar.
     *
     * @param  Collection<int,ComplianceSubject>  $data
     * @return array<int,string|null>
     */
    private function statusDari(Collection $data): array
    {
        return $data->reject(fn (ComplianceSubject $s) => $s->status === 'Draf')
            ->flatMap(fn (ComplianceSubject $s) => $s->points->pluck('status'))
            ->all();
    }

    private function persenTeks(?float $persen): string
    {
        return $persen === null ? '— belum dinilai' : number_format($persen, 1).'%';
    }

    private function tanggalPanjang(?string $tanggal): string
    {
        if (!$tanggal) return '—';

        try {
            return \Illuminate\Support\Carbon::parse($tanggal)->translatedFormat('d M Y');
        } catch (\Throwable) {
            return $tanggal;
        }
    }
}
