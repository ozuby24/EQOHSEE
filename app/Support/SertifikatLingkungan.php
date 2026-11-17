<?php

namespace App\Support;

use App\Models\{EnvAudit, EnvAuditSertifikat, Signatory};
use Illuminate\Support\Carbon;

/**
 * Sertifikat Penghargaan Kinerja Lingkungan: syarat terbit, potret isi,
 * dan bentuk data lembarnya.
 *
 * Satu tempat untuk ketiganya, dipakai halaman ikhtisar (pratinjau),
 * lembar cetak, dan halaman verifikasi publik. Ditulis tiga kali,
 * ketiganya cepat berselisih — dan yang paling mahal berselisih adalah
 * lembar yang dicetak dengan halaman yang memverifikasinya.
 */
final class SertifikatLingkungan
{
    /**
     * Boleh terbit atau tidak, beserta alasannya.
     *
     * Dua syarat, keduanya dari instrumennya sendiri: seluruh kriteria
     * sudah diverifikasi — kriteria yang belum diisi dihitung nol,
     * jadi skor audit yang belum lengkap adalah skor sementara — dan
     * predikatnya memang terbit, yang sudah memuat syarat nilai penuh
     * pada bagian Administrasi dan Implementasi.
     *
     * @return array{layak:bool, alasan:?string}
     */
    public static function kelayakan(EnvAudit $a, array $skor, ?EnvAuditSertifikat $aktif = null): array
    {
        if ($aktif) {
            return ['layak' => false, 'alasan' => 'Sertifikat '.$aktif->nomor.' masih berlaku. '
                .'Cabut lebih dulu bila perlu diterbitkan ulang.'];
        }

        if ($skor['belum'] > 0) {
            return ['layak' => false, 'alasan' => 'Masih ada '.$skor['belum'].' dari '.$skor['kriteria']
                .' kriteria yang belum diverifikasi. Sertifikat hanya terbit dari audit yang lengkap — '
                .'kriteria yang belum diisi dihitung nol.'];
        }

        if (!$skor['predikat']['nama']) {
            return ['layak' => false, 'alasan' => $skor['predikat']['alasan']];
        }

        return ['layak' => true, 'alasan' => null];
    }

    /** Pemberi penghargaan: pemilik izin perusahaan yang diaudit, bila ada. */
    public static function penerbit(EnvAudit $a): ?string
    {
        $c = $a->company;
        if (!$c) return null;

        $pemilik = $c->owner?->name ?: trim((string) $c->parent);

        return $pemilik !== '' && $pemilik !== $c->name ? $pemilik : null;
    }

    /** Isi sertifikat yang dibekukan saat terbit. */
    public static function potret(EnvAudit $a, array $skor, ?Signatory $ttd): array
    {
        return [
            'perusahaan'   => $a->company?->name ?: $a->judul,
            'penerbit'     => self::penerbit($a),
            'judul'        => $a->judul,
            'kodeAudit'    => $a->kode,
            'lokasi'       => $a->lokasi,
            'tahun'        => $a->tahun,
            'tanggalAudit' => $a->tanggal?->toDateString(),

            'pemenuhan' => $skor['pemenuhan'],
            'pengurang' => $skor['pengurang'],
            'akhir'     => $skor['akhir'],
            'kriteria'  => $skor['kriteria'],

            'peringkatKriteria' => $skor['peringkat']['kriteria'],
            'warna'             => $skor['peringkat']['warna'],

            'bagian' => array_values(array_map(fn ($b) => [
                'kunci'  => $b['kunci'],
                'judul'  => $b['judul'],
                'bobot'  => $b['bobot'],
                'persen' => $b['persen'],
            ], $skor['bagian'])),

            'ttdNama'    => $ttd?->name,
            'ttdJabatan' => $ttd?->title,
        ];
    }

    /**
     * Sertifikat yang berlaku berbeda dari hasil hitung audit sekarang.
     *
     * Nilai audit tetap boleh disunting sesudah sertifikat terbit —
     * temuan lapangan yang terlambat masuk memang terjadi. Yang tidak
     * boleh terjadi adalah perubahan itu tidak terlihat.
     */
    public static function berubah(EnvAuditSertifikat $s, array $skor): bool
    {
        return $s->predikat !== $skor['predikat']['nama']
            || $s->peringkat !== $skor['peringkat']['nama']
            || abs($s->skor - (float) $skor['akhir']) >= 0.005;
    }

    /** Data lembar sertifikat untuk digambar. */
    public static function lembar(EnvAuditSertifikat $s): array
    {
        $d = (array) $s->data;
        $c = $s->company;

        $logoPenerima = $c?->logo ? Berkas::terbuka($c->logo) : null;
        $logoPenerbit = $c?->owner?->logo ? Berkas::terbuka($c->owner->logo) : null;

        return [
            'id'      => $s->id,
            'nomor'   => $s->nomor,
            'kode'    => $s->kodeTampil(),
            'terbit'  => $s->terbit?->translatedFormat('j F Y'),
            'berlaku' => $s->berlaku?->translatedFormat('j F Y'),
            'tempat'  => $s->tempat,

            'predikat'          => $s->predikat,
            'bintang'           => EnvAuditSertifikat::BINTANG[$s->predikat] ?? 1,
            'peringkat'         => $s->peringkat,
            'peringkatKriteria' => $d['peringkatKriteria'] ?? '',
            'warna'             => $d['warna'] ?? '#16A34A',
            'tema'              => $s->tema(),

            'skor'      => (float) $s->skor,
            'pemenuhan' => (float) ($d['pemenuhan'] ?? $s->skor),
            'pengurang' => (float) ($d['pengurang'] ?? 0),
            'kriteria'  => (int) ($d['kriteria'] ?? 0),

            'perusahaan' => $d['perusahaan'] ?? '—',
            'penerbit'   => $d['penerbit'] ?? null,
            'judul'      => $d['judul'] ?? '',
            'kodeAudit'  => $d['kodeAudit'] ?? null,
            'lokasi'     => $d['lokasi'] ?? null,
            'tahun'      => (int) ($d['tahun'] ?? $s->terbit?->year),
            'tanggalAudit' => ! empty($d['tanggalAudit'])
                ? Carbon::parse($d['tanggalAudit'])->translatedFormat('j F Y') : null,
            'bagian'     => $d['bagian'] ?? [],

            'ttdNama'    => $d['ttdNama'] ?? null,
            'ttdJabatan' => $d['ttdJabatan'] ?? null,
            'ttdGambar'  => Berkas::url($s->signatory, 'ttd'),

            /* Logo pemberi dan penerima dipisah: perusahaan jasa yang
               tidak berlogo sendiri tidak boleh tampil memakai logo
               pemilik izinnya seolah-olah itu logonya. */
            'logoPenerbit' => $logoPenerbit,
            'logoPenerima' => $logoPenerima !== $logoPenerbit ? $logoPenerima : null,

            'status'      => $s->status(),
            'dicabutPada' => $s->dicabut_at?->translatedFormat('j F Y'),
            'alasanCabut' => $s->alasan_cabut,

            'urlVerifikasi' => $s->urlVerifikasi(),
        ];
    }
}
