<?php

namespace App\Support;

use App\Models\Company;
use App\Models\HazardReport;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Register Tindakan Perbaikan — lembar Excel yang diserahkan ke luar.
 *
 * Ini BUKAN ekspor data. Ekspor data sudah ada dan berbentuk CSV: ia
 * menuangkan seluruh kolom apa adanya untuk diolah lagi. Yang disusun di
 * sini sebuah FORMULIR TERKENDALI — berkop, bernomor dokumen, dengan
 * kolom yang urutannya disepakati — yang dicetak, ditandatangani, dan
 * dibawa ke rapat bulanan bersama perusahaan terlapor.
 *
 * ── Fotonya DITANAM, bukan sekadar ditautkan ──
 *
 * Aplikasi asal menautkan fotonya ke Google Drive dan menyertakan empat
 * baris petunjuk cara menampilkannya sendiri. Itu masuk akal di sana:
 * berkasnya tetap ringan, dan tautannya terbuka bagi siapa pun.
 *
 * Di sini tidak. Foto laporan bahaya disajikan lewat rute berjaga yang
 * menuntut sesi login — lihat App\Support\Berkas — sehingga `=IMAGE()`
 * pada Excel akan mengambilnya TANPA kuki sesi dan mendapat halaman
 * masuk, bukan gambar. Lembar ini pula yang paling sering dikirim ke
 * pihak yang justru TIDAK punya akun: kontraktor yang ditagih
 * perbaikannya.
 *
 * Maka yang ditanam thumbnail — dikecilkan ke MAKS_THUMB piksel dan
 * dikompres — dan selnya sekaligus menjadi tautan ke foto ukuran penuh
 * bagi yang memang punya akses. Dua puluh dua temuan berfoto dua-duanya
 * berujung di bawah satu megabita, dan lembarnya terbaca utuh di layar
 * orang yang membukanya di ruang rapat tanpa jaringan.
 *
 * ── Yang tidak berfoto tidak mendapat kotak kosong ──
 *
 * Selnya berisi tanda hubung. Kotak abu-abu bertuliskan "tidak ada
 * gambar" pada lembar yang dicetak hitam-putih tidak dapat dibedakan
 * dari gambar yang gagal dimuat.
 */
final class RegisterPerbaikan
{
    /** Sisi terpanjang thumbnail yang ditanam, dalam piksel. */
    private const MAKS_THUMB = 240;

    /** Tinggi baris data, dalam titik. Menampung thumbnail beserta jaraknya. */
    private const TINGGI_BARIS = 96;

    /** Lebar kolom A–I, mengikuti formulir acuan. */
    private const LEBAR = [6, 34, 16, 40, 18, 13, 11, 26, 26];

    private const JUDUL_KOLOM = [
        'No',
        'Deviasi / Penyimpangan / Ketidaksesuaian (Deskripsi Temuan)',
        'Hirarki Pengendalian',
        'Rekomendasi Tindakan Perbaikan',
        'Penanggung Jawab (PIC)',
        'Batas Akhir',
        'Status',
        'Gambar Temuan',
        'Gambar Perbaikan',
    ];

    /**
     * Cara pengurutan yang ditawarkan, beserta kolom pengurutnya.
     *
     * Kuncinya muncul di alamat dan di berkasnya, jadi ia pendek dan
     * tidak menyebut nama kolom basis data.
     */
    public const URUTAN = [
        'bulan'      => 'Bulan / Tanggal',
        'lokasi'     => 'Lokasi / Area',
        'perusahaan' => 'Perusahaan Terlapor',
        'risiko'     => 'Tingkat Risiko',
        'status'     => 'Status',
    ];

    /**
     * @param Collection<int,HazardReport> $data
     * @param array{urutan?:string,perusahaan?:string,bulan?:string,lokasi?:string} $pilihan
     */
    public function __construct(
        private Collection $data,
        private ?Company $perusahaan,
        private array $pilihan = [],
    ) {}

    public function spreadsheet(): Spreadsheet
    {
        $buku = new Spreadsheet();
        $l = $buku->getActiveSheet();
        $l->setTitle('Register Perbaikan');

        foreach (self::LEBAR as $i => $lebar) {
            $l->getColumnDimensionByColumn($i + 1)->setWidth($lebar);
        }

        $baris = $this->kop($l);
        $baris = $this->tabel($l, $baris);
        $baris = $this->ringkasan($l, $baris);
        $this->catatanKaki($l, $baris);

        /* Dibekukan di bawah baris judul tabel. Register dua puluh temuan
           tidak muat satu layar, dan kolom yang digulir tanpa judulnya
           menjadi sembilan kolom teks yang tidak dapat dibedakan. */
        $l->freezePane('A'.($this->barisJudul + 1));

        $l->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $l->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $l->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        /* Baris judul diulang pada tiap halaman cetak. Halaman kedua
           sebuah register tanpa judul kolom adalah halaman yang harus
           dibolak-balik untuk dibaca. */
        $l->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($this->barisJudul, $this->barisJudul);

        return $buku;
    }

    /** Nomor baris judul tabel — dipakai membekukan panel dan mengulang cetak. */
    private int $barisJudul = 7;

    /* ═════════════ kop ═════════════ */

    private function kop(Worksheet $l): int
    {
        $dok = KopDokumen::untuk('register-perbaikan', $this->perusahaan);

        $l->mergeCells('A1:B3');
        $l->mergeCells('C1:G1');
        $l->mergeCells('C2:G3');

        $l->setCellValue('C1', $dok['jenis']);
        $l->setCellValue('C2', $dok['judul']);

        $l->setCellValue('H1', 'No. Dokumen');
        $l->setCellValue('I1', $dok['nomor'] ?: '—');
        $l->setCellValue('H2', 'Tgl Penerbitan');
        $l->setCellValue('I2', $this->tanggalPanjang($dok['terbit']));
        $l->setCellValue('H3', 'Tgl Perubahan');
        $l->setCellValue('I3', $this->tanggalPanjang($dok['setuju']));

        $l->getStyle('C1:G1')->getFont()->setBold(true)->setSize(11);
        $l->getStyle('C2:G3')->getFont()->setBold(true)->setSize(14);
        $l->getStyle('C1:G3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $l->getStyle('H1:H3')->getFont()->setBold(true)->setSize(9);
        $l->getStyle('I1:I3')->getFont()->setSize(9);
        $l->getStyle('A1:I3')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach ([1, 2, 3] as $r) $l->getRowDimension($r)->setRowHeight(18);

        $this->logo($l);

        /* Baris identitas: apa yang disaring, dan berapa hasilnya.
           Register tanpa keduanya tidak dapat dipertanggungjawabkan —
           yang membacanya tidak tahu apakah ia melihat seluruh temuan
           atau satu areanya saja. */
        $l->mergeCells('A4:B4'); $l->mergeCells('C4:E4');
        $l->mergeCells('F4:G4'); $l->mergeCells('H4:I4');
        $l->mergeCells('A5:B5'); $l->mergeCells('C5:E5');
        $l->mergeCells('F5:G5'); $l->mergeCells('H5:I5');

        $l->setCellValue('A4', 'Perusahaan / Sumber');
        $l->setCellValue('C4', ': '.$this->labelSumber());
        $l->setCellValue('F4', 'Tanggal');
        $l->setCellValue('H4', ': '.Waktu::kini()->translatedFormat('d M Y'));

        $l->setCellValue('A5', 'Sort Berdasarkan');
        $l->setCellValue('C5', ': '.(self::URUTAN[$this->pilihan['urutan'] ?? 'bulan'] ?? self::URUTAN['bulan']));
        $l->setCellValue('F5', 'Total');
        $l->setCellValue('H5', ': '.$this->data->count().' temuan');

        $l->getStyle('A4:I5')->getFont()->setSize(10);
        $l->getStyle('A4:B5')->getFont()->setBold(true);
        $l->getStyle('F4:G5')->getFont()->setBold(true);
        $l->getStyle('A4:I5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $l->getRowDimension(4)->setRowHeight(18);
        $l->getRowDimension(5)->setRowHeight(18);

        return 7;
    }

    /**
     * Logo perusahaan di kotak kiri atas.
     *
     * Dilewati diam-diam bila perusahaannya belum memasang logo — dan
     * itu bukan kelalaian melainkan keadaan yang wajar: pemasangan baru
     * belum sempat mengunggahnya, dan berkas yang GAGAL terbit karena
     * logo tidak ada jauh lebih merugikan daripada kop tanpa logo.
     */
    private function logo(Worksheet $l): void
    {
        $jalur = $this->perusahaan?->effectiveLogo();

        if (!$jalur) return;

        $berkas = \Illuminate\Support\Facades\Storage::disk(Berkas::TERBUKA)->path($jalur);

        if (!is_file($berkas)) return;

        try {
            $gambar = new Drawing();
            $gambar->setPath($berkas);
            $gambar->setOffsetX(8);
            $gambar->setOffsetY(4);
            $gambar->setCoordinates('A1');

            /* Dibatasi kedua sisinya, bukan tingginya saja. Logo
               memanjang — lambang bersanding tulisan, nisbah 4:1 — yang
               hanya dipatok tingginya akan melebar menembus kotaknya dan
               menutupi judul di sebelahnya. Yang terlihat saat mencoba
               dengan satu logo bujur sangkar adalah kop yang rapi. */
            $this->muatkan($l, $gambar, self::LEBAR[0] + self::LEBAR[1], 3 * 18, 52);

            $gambar->setWorksheet($l);
        } catch (\Throwable) {
            /* Logo yang tidak dapat dibaca — format tak dikenal, berkas
               rusak — tidak boleh menggagalkan seluruh registernya. */
        }
    }

    /**
     * Patok satu gambar supaya muat di dalam kotak selnya.
     *
     * Memakai NISBAH gambar itu sendiri, bukan tinggi dan lebar yang
     * ditetapkan dua kali. PhpSpreadsheet tidak memangkas apa pun: tinggi
     * yang dipatok tanpa lebarnya membuat gambar 16:9 setinggi 110 piksel
     * selebar 196 piksel, dan kolom selebar 26 karakter hanya 187 — jadi
     * ia menjorok sembilan piksel ke kolom sebelahnya, tepat menimpa
     * gambar perbaikan di kolom berikutnya.
     *
     * @param float $lebarKolom lebar kolom dalam satuan Excel (karakter)
     * @param float $tinggiBaris tinggi baris dalam poin
     * @param float $maksTinggi  tinggi yang diinginkan, bila muat
     */
    private function muatkan(
        Worksheet $l,
        Drawing|MemoryDrawing $gambar,
        float $lebarKolom,
        float $tinggiBaris,
        float $maksTinggi,
    ): void {
        /* Fontnya diambil dari lembarnya, bukan dari gambarnya. Lebar
           kolom Excel dihitung dalam satuan karakter font bawaan, jadi
           menghitungnya dengan font yang berbeda memulangkan lebar yang
           berbeda pula — dan `$gambar->getWorksheet()` di sini masih
           null: gambarnya memang belum ditempelkan. */
        $font = $l->getParent()?->getDefaultStyle()->getFont()
            ?? (new Spreadsheet())->getDefaultStyle()->getFont();

        /* Sisa ruang dikurangi tepiannya. Gambar yang persis selebar
           selnya menempel pada garis kolom di kedua sisi, dan garis yang
           tertutup gambar membuat tabelnya terlihat berantakan justru
           pada baris yang paling diperhatikan orang. */
        $kotakLebar  = max(1, SharedDrawing::cellDimensionToPixels($lebarKolom, $font) - 14);
        $kotakTinggi = max(1, SharedDrawing::pointsToPixels($tinggiBaris) - 12);

        $asliLebar  = max(1, $gambar->getWidth());
        $asliTinggi = max(1, $gambar->getHeight());

        $skala = min(
            $maksTinggi / $asliTinggi,
            $kotakTinggi / $asliTinggi,
            $kotakLebar / $asliLebar,
        );

        $gambar->setResizeProportional(true);
        $gambar->setHeight((int) max(1, round($asliTinggi * $skala)));
    }

    /* ═════════════ tabel ═════════════ */

    private function tabel(Worksheet $l, int $baris): int
    {
        $this->barisJudul = $baris;

        foreach (self::JUDUL_KOLOM as $i => $judul) {
            $l->setCellValue([$i + 1, $baris], $judul);
        }

        $l->getStyle("A{$baris}:I{$baris}")->getFont()->setBold(true)->setSize(10);
        $l->getStyle("A{$baris}:I{$baris}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $l->getStyle("A{$baris}:I{$baris}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $l->getStyle("A{$baris}:I{$baris}")->getFont()->getColor()->setRGB('FFFFFF');
        $l->getStyle("A{$baris}:I{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $l->getRowDimension($baris)->setRowHeight(40);

        $baris++;
        $no = 1;

        foreach ($this->data as $h) {
            $l->setCellValue("A{$baris}", $no);
            $l->setCellValue("B{$baris}", (string) $h->deskripsi);
            $l->setCellValue("C{$baris}", (string) ($h->hirarki ?: '—'));
            $l->setCellValue("D{$baris}", (string) ($h->rekomendasi ?: '—'));
            $l->setCellValue("E{$baris}", $h->company?->name ?: ($h->terlapor ?: '—'));

            /* Tenggat kosong ditulis "Belum ditetapkan", BUKAN dibiarkan
               kosong. Sel kosong pada kolom tenggat terbaca sebagai
               kolom yang lupa diisi saat mencetak, dan yang membacanya
               akan mencari tenggatnya di tempat lain. */
            $l->setCellValue("F{$baris}", $h->batas_akhir
                ? $h->batas_akhir->translatedFormat('d-M-y')
                : 'Belum ditetapkan');

            $l->setCellValue("G{$baris}", mb_strtoupper((string) $h->status));

            $this->sel($l, "H{$baris}", $h, 'hzd');
            $this->sel($l, "I{$baris}", $h, 'hzt');

            $l->getRowDimension($baris)->setRowHeight(self::TINGGI_BARIS);

            /* Tenggat terlewat disorot. Register yang tidak membedakannya
               menyerahkan pekerjaan itu kepada yang membacanya — dan yang
               membacanya adalah orang yang sama yang seharusnya ditagih. */
            if ($h->lewatTenggat()) {
                $l->getStyle("F{$baris}:G{$baris}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE2E1');
                $l->getStyle("F{$baris}:G{$baris}")->getFont()->getColor()->setRGB('B91C1C');
                $l->getStyle("F{$baris}:G{$baris}")->getFont()->setBold(true);
            }

            $baris++;
            $no++;
        }

        $akhir = $baris - 1;

        if ($akhir >= $this->barisJudul + 1) {
            $julat = 'A'.($this->barisJudul + 1).":I{$akhir}";
            $l->getStyle($julat)->getFont()->setSize(9);
            /* Rata TENGAH tegak, bukan rata atas.

               Barisnya setinggi 96 poin karena harus memuat foto, jauh
               lebih tinggi daripada yang dibutuhkan satu-dua baris teks.
               Teks yang menempel di tepi atas baris setinggi itu
               berjarak jauh dari fotonya sendiri yang tergambar di
               tengah, sehingga satu baris register terbaca sebagai dua
               hal yang tidak berhubungan. */
            $l->getStyle($julat)->getAlignment()->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $l->getStyle($julat)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            foreach (['A', 'C', 'F', 'G', 'H', 'I'] as $kolom) {
                $l->getStyle($kolom.($this->barisJudul + 1).":{$kolom}{$akhir}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $l->setAutoFilter('A'.$this->barisJudul.":I{$akhir}");
        }

        return $baris + 1;
    }

    /**
     * Isi satu sel gambar: thumbnail yang ditanam, dan tautan ke aslinya.
     *
     * Teks selnya tetap diisi meski gambarnya berhasil ditanam. Gambar
     * pada Excel MENGAMBANG di atas lembar, bukan berada di dalam selnya
     * — menyalin barisnya, menyaring tabelnya, atau menyembunyikan
     * kolomnya meninggalkan gambar itu di tempatnya semula. Teks di
     * baliknya memastikan barisnya tetap terbaca ketika gambarnya
     * tertinggal.
     *
     */
    private function sel(Worksheet $l, string $sel, HazardReport $h, string $jenis): void
    {
        $alamat = Berkas::daftarUrl($h, $jenis);

        if (!$alamat) {
            $l->setCellValue($sel, '—');
            $l->getStyle($sel)->getFont()->getColor()->setRGB('9CA3AF');

            return;
        }

        $l->setCellValueExplicit($sel, 'Lihat foto', DataType::TYPE_STRING);
        $l->getCell($sel)->getHyperlink()->setUrl($alamat[0]);
        $l->getStyle($sel)->getFont()->getColor()->setRGB('1D4ED8');
        $l->getStyle($sel)->getFont()->setUnderline(true)->setSize(8);
        $l->getStyle($sel)->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);

        $this->thumbnail($l, $sel, $this->jalurFoto($h, $jenis));
    }

    /**
     * Jalur berkas foto pertama pada diska.
     *
     * Dibaca dari barisnya langsung, bukan dengan menguraikan kembali
     * alamat guarded yang baru saja disusun sendiri. Cara yang kedua —
     * susun alamat, cocokkan dengan regex, ambil ulang barisnya dari
     * basis data tanpa scope — bekerja, tetapi memasang jalur yang
     * MENERIMA ALAMAT lalu memuat baris apa pun yang ditunjuknya. Selama
     * alamatnya berasal dari dalam kelas ini, itu aman; yang membuatnya
     * berbahaya adalah pemanggil berikutnya, yang akan meneruskan alamat
     * dari tempat lain karena bentuk fungsinya mengundang itu.
     *
     * Atributnya diambil dari peta Berkas, bukan ditulis harfiah, supaya
     * penggantian nama kolom tidak menyisakan satu tempat yang lupa.
     */
    private function jalurFoto(HazardReport $h, string $jenis): ?string
    {
        [, $atribut, $daftar] = Berkas::tersaji()[$jenis];

        $nilai = $h->{$atribut};

        return $daftar ? (((array) $nilai)[0] ?? null) : ($nilai ?: null);
    }

    /**
     * Tanam thumbnail satu foto ke dalam selnya.
     *
     * Dibaca dari DISKA, bukan diambil lewat alamat guarded-nya.
     * Mengambilnya lewat HTTP berarti aplikasi ini memanggil dirinya
     * sendiri sekali per foto — pada register dua puluh temuan itu empat
     * puluh permintaan yang menunggu proses yang sama yang sedang
     * menyusun berkasnya, dan pada server berproses tunggal keduanya
     * saling menunggu sampai habis waktu.
     */
    private function thumbnail(Worksheet $l, string $sel, ?string $jalur): void
    {
        if (!$jalur) return;

        $disk = \Illuminate\Support\Facades\Storage::disk(Berkas::TERTUTUP);

        if (!$disk->exists($jalur)) {
            $disk = \Illuminate\Support\Facades\Storage::disk(Berkas::TERBUKA);
            if (!$disk->exists($jalur)) return;
        }

        try {
            $sumber = @imagecreatefromstring($disk->get($jalur));
            if (!$sumber) return;

            $lebar  = imagesx($sumber);
            $tinggi = imagesy($sumber);
            $skala  = min(1, self::MAKS_THUMB / max($lebar, $tinggi));

            $kecil = imagescale($sumber, max(1, (int) round($lebar * $skala)));
            imagedestroy($sumber);

            if (!$kecil) return;

            /* Keduanya HARUS sepasang. Yang pertama menentukan bita apa
               yang ditulis, yang kedua menentukan nama dan jenis yang
               didaftarkan paketnya — dan MIMETYPE_DEFAULT adalah PNG.
               Memasangkannya dengan RENDERING_JPEG menghasilkan berkas
               berisi JPEG yang dinamai .png dan dideklarasikan image/png
               di [Content_Types].xml: paket yang tidak sah, yang boleh
               saja ditolak atau dikosongkan pembacanya. Ukurannya tetap
               masuk akal dan berkasnya tetap terbuka, sehingga
               kekeliruannya hanya terlihat dari dalam zip-nya. */
            $gambar = new MemoryDrawing();
            $gambar->setImageResource($kecil);
            $gambar->setRenderingFunction(MemoryDrawing::RENDERING_JPEG);
            $gambar->setMimeType(MemoryDrawing::MIMETYPE_JPEG);

            /* Dipatok tinggi DAN lebarnya. Yang pertama menyamakan
               tinggi tiap baris apa pun bentuk fotonya — foto tegak yang
               dibiarkan setinggi aslinya meregangkan barisnya tiga kali
               lipat baris lain. Yang kedua menahannya tetap di dalam
               kolomnya; tanpa itu, foto membujur menjorok ke kolom
               sebelahnya dan menimpa foto perbaikan yang ada di sana. */
            $gambar->setOffsetX(6);
            $gambar->setOffsetY(4);
            $gambar->setCoordinates($sel);

            $kolom = Coordinate::columnIndexFromString(
                Coordinate::coordinateFromString($sel)[0],
            );

            $this->muatkan($l, $gambar, self::LEBAR[$kolom - 1], self::TINGGI_BARIS, 110);

            $gambar->setWorksheet($l);
        } catch (\Throwable) {
            /* Foto yang tidak dapat dibaca meninggalkan tautannya saja.
               Register yang gagal terbit karena satu foto rusak jauh
               lebih merugikan daripada satu sel tanpa gambar. */
        }
    }

    /* ═════════════ ringkasan ═════════════ */

    private function ringkasan(Worksheet $l, int $baris): int
    {
        $total  = $this->data->count();
        $tutup  = $this->data->where('status', 'Closed')->count();
        $telat  = $this->data->filter(fn ($h) => $h->lewatTenggat())->count();
        $persen = $total ? (int) round($tutup / $total * 100) : 0;

        $l->mergeCells("A{$baris}:C{$baris}");
        $l->mergeCells("D{$baris}:I{$baris}");
        $l->setCellValue("A{$baris}", 'RINGKASAN PENYELESAIAN');

        /* Jumlah yang lewat tenggat ikut disebut. "Closed 68%" sendirian
           terbaca sebagai kabar baik; yang menentukan apakah ia kabar
           baik adalah berapa dari sisanya yang sudah lewat waktu. */
        $l->setCellValue("D{$baris}", sprintf(
            'Closed: %d dari %d temuan     Persentase Closed: %d%%%s',
            $tutup, $total, $persen,
            $telat ? sprintf('     Lewat tenggat: %d temuan', $telat) : '',
        ));

        $l->getStyle("A{$baris}:I{$baris}")->getFont()->setBold(true)->setSize(10);
        $l->getStyle("A{$baris}:I{$baris}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
        $l->getStyle("A{$baris}:I{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $l->getRowDimension($baris)->setRowHeight(22);
        $baris++;

        /* Rekap pelapor menjawab "siapa yang perlu ditanya", bukan
           "apa nomor laporannya".

           Sebelumnya kolom terakhirnya memuat deretan nomor temuan —
           FRM/ABG/OHSE/106, 105, 104, dan seterusnya. Deretan itu
           menghabiskan lima kolom terlebar pada lembarnya dan tidak
           menjawab satu pun pertanyaan yang benar-benar diajukan di
           rapat: nomornya sudah ada di tabel utama beberapa baris di
           atas, satu per satu, lengkap dengan uraiannya. Yang TIDAK ada
           di mana pun adalah rincian per orangnya — berapa yang masih
           menganggur, berapa yang berisiko tinggi, berapa yang sudah
           lewat tenggat. Itulah yang menentukan siapa yang namanya
           disebut lebih dulu. */
        foreach (self::REKAP_KOLOM as $i => $judul) {
            $l->setCellValue([$i + 1, $baris], $judul);
        }
        $l->mergeCells("A{$baris}:B{$baris}");

        $l->getStyle("A{$baris}:I{$baris}")->getFont()->setBold(true)->setSize(9);
        $l->getStyle("A{$baris}:I{$baris}")->getAlignment()
            ->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $l->getStyle("C{$baris}:I{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $l->getStyle("A{$baris}:I{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $l->getRowDimension($baris)->setRowHeight(24);
        $baris++;

        foreach ($this->rekapPelapor() as $o) {
            $l->mergeCells("A{$baris}:B{$baris}");

            $l->setCellValue("A{$baris}", $o['nama'] ?: '—');
            $l->setCellValue("C{$baris}", $o['jumlah']);
            $l->setCellValue("D{$baris}", $o['rincian']);
            $l->setCellValue("E{$baris}", $o['tinggi'] ?: '—');
            $l->setCellValue("F{$baris}", $o['telat'] ?: '—');
            $l->setCellValue("G{$baris}", $o['persen'].'%');
            $l->setCellValue("H{$baris}", $o['area']);
            $l->setCellValue("I{$baris}", $o['terakhir']);

            $l->getStyle("A{$baris}:I{$baris}")->getFont()->setSize(9);
            $l->getStyle("C{$baris}:I{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $l->getStyle("D{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $l->getStyle("H{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            /* Yang punya temuan lewat tenggat ditandai — pada kolomnya
               saja, bukan seluruh barisnya. Baris merah utuh membuat
               orangnya terbaca sebagai bermasalah, padahal yang
               bermasalah adalah temuannya. */
            if ($o['telat']) {
                $l->getStyle("F{$baris}")->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
            }

            $l->getStyle("A{$baris}:I{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $l->getRowDimension($baris)->setRowHeight(18);
            $baris++;
        }

        return $baris + 1;
    }

    /** Judul kolom rekap pelapor, satu per kolom A..I (A dan B menyatu). */
    private const REKAP_KOLOM = [
        'Pelapor', '', 'Jumlah Lapor', 'Rincian Status', 'Risiko Tinggi',
        'Lewat Tenggat', 'Closed', 'Area Terbanyak', 'Lapor Terakhir',
    ];

    /**
     * Rekap per pelapor, terurut dari yang paling banyak melapor.
     *
     * Dikelompokkan lewat Identitas, bukan langsung dari namanya. Satu
     * orang yang mengetik namanya sedikit berbeda pada tiga laporan akan
     * muncul tiga kali di rekap yang dibawa ke rapat — dan yang
     * membacanya menyimpulkan tiga orang melapor satu kali, bukan satu
     * orang melapor tiga kali.
     *
     * @return list<array<string,mixed>>
     */
    private function rekapPelapor(): array
    {
        $rekap = [];

        foreach ($this->data as $h) {
            $kunci = Identitas::kunci($h->user_id, $h->pelapor_nrp, $h->pelapor_nama);

            $rekap[$kunci] ??= [
                'nama'     => $h->user?->name ?: $h->pelapor_nama,
                'jumlah'   => 0,
                'status'   => ['Open' => 0, 'In Progress' => 0, 'Closed' => 0],
                'tinggi'   => 0,
                'telat'    => 0,
                'area'     => [],
                'terakhir' => null,
            ];

            $o = &$rekap[$kunci];
            $o['jumlah']++;

            /* Status di luar ketiganya tetap terhitung pada jumlahnya,
               hanya tidak pada rinciannya. Membuang barisnya sama sekali
               akan membuat jumlah lapor tidak sama dengan jumlah temuan
               orang itu pada tabel di atasnya, dan selisih tanpa sebab
               di satu lembar yang sama menghabiskan waktu rapat. */
            if (isset($o['status'][$h->status])) $o['status'][$h->status]++;

            if ($h->risiko === 'Tinggi')  $o['tinggi']++;
            if ($h->lewatTenggat())       $o['telat']++;
            if ($h->lokasi)               $o['area'][$h->lokasi] = ($o['area'][$h->lokasi] ?? 0) + 1;

            if ($h->tanggal && (!$o['terakhir'] || $h->tanggal->greaterThan($o['terakhir']))) {
                $o['terakhir'] = $h->tanggal;
            }

            unset($o);
        }

        uasort($rekap, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return array_values(array_map(function (array $o) {
            arsort($o['area']);

            return [
                'nama'     => $o['nama'],
                'jumlah'   => $o['jumlah'],
                'rincian'  => sprintf(
                    'Open %d · Proses %d · Closed %d',
                    $o['status']['Open'], $o['status']['In Progress'], $o['status']['Closed'],
                ),
                'tinggi'   => $o['tinggi'],
                'telat'    => $o['telat'],
                'persen'   => $o['jumlah'] ? (int) round($o['status']['Closed'] / $o['jumlah'] * 100) : 0,
                'area'     => (string) (array_key_first($o['area']) ?? '—'),
                'terakhir' => $o['terakhir']?->translatedFormat('d M Y') ?? '—',
            ];
        }, $rekap));
    }

    /* ═════════════ catatan kaki ═════════════ */

    private function catatanKaki(Worksheet $l, int $baris): void
    {
        $l->mergeCells("A{$baris}:I{$baris}");
        $l->setCellValue("A{$baris}", 'CATATAN');
        $l->getStyle("A{$baris}")->getFont()->setBold(true)->setSize(10);
        $l->getRowDimension($baris)->setRowHeight(22);
        $baris++;

        /* Petunjuknya menyebut apa yang BERLAKU DI SINI.
         *
         * Lembar acuan menyertakan empat baris cara menampilkan foto
         * dengan =IMAGE(), sebab fotonya ada di Google Drive yang
         * terbuka. Menyalin petunjuk itu apa adanya akan menyuruh orang
         * mengerjakan sesuatu yang PASTI gagal: foto di sini dijaga rute
         * bersesi, dan Excel mengambilnya tanpa kuki sesi.
         */
        $catatan = [
            'Foto temuan dan foto perbaikan sudah TERTANAM di dalam berkas ini, '
                .'sehingga tetap terlihat tanpa jaringan maupun akun EQOHSEE.',
            'Gambar yang tertanam sengaja diperkecil agar berkasnya ringan. '
                .'Untuk ukuran penuh, klik tautan "Lihat foto" pada sel yang sama — '
                .'tautan itu menuntut akun EQOHSEE yang berhak atas laporannya.',
            'Gambar pada Excel mengambang di atas lembar, bukan berada di dalam selnya. '
                .'Menyaring atau mengurutkan ulang tabel ini akan meninggalkan gambarnya '
                .'di tempat semula, sedangkan teks pada selnya tetap ikut barisnya.',
            'Tenggat yang sudah terlewat ditandai merah pada kolom Batas Akhir dan Status.',
        ];

        foreach ($catatan as $i => $teks) {
            $l->mergeCells("A{$baris}:I{$baris}");
            $l->setCellValue("A{$baris}", ($i + 1).'. '.$teks);
            $l->getStyle("A{$baris}")->getFont()->setSize(9);
            $l->getStyle("A{$baris}")->getAlignment()->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
            $l->getRowDimension($baris)->setRowHeight(26);
            $baris++;
        }
    }

    /* ═════════════ bantu ═════════════ */

    private function labelSumber(): string
    {
        $id = $this->pilihan['perusahaan'] ?? null;

        if ($id) {
            return Company::withoutGlobalScopes()->find($id)?->name ?? 'Semua perusahaan';
        }

        return 'Semua perusahaan terlapor';
    }

    private function tanggalPanjang(?string $nilai): string
    {
        if (!$nilai) return '—';

        try {
            return \Carbon\Carbon::parse($nilai)->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $nilai;
        }
    }

    /** Nama berkas unduhan, bertanggal supaya dua unduhan tidak saling timpa. */
    public function namaBerkas(): string
    {
        $imbuhan = match ($this->pilihan['urutan'] ?? 'bulan') {
            'lokasi'     => 'perArea',
            'perusahaan' => 'perPerusahaan',
            'risiko'     => 'perRisiko',
            'status'     => 'perStatus',
            default      => 'perBulan',
        };

        return 'Register_Tindakan_Perbaikan_'.$imbuhan.'_'.Waktu::kini()->format('Ymd_His').'.xlsx';
    }
}
