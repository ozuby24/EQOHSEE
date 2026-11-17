<?php

namespace App\Support\Miners\Kartu;

use App\Models\Miners\{InduksiOrang, McuOrang, Permit, Simper};
use App\Support\Miners\{Verifikasi, Zona};
use App\Support\Waktu;
use Illuminate\Support\Carbon;

/**
 * Isi halaman verifikasi publik `/v/{kode}` — RincianVerifikasi Safe Track.
 *
 * Halaman ini dibuka siapa saja yang memindai QR (petugas pos, Inspektur
 * Tambang), TANPA login. Karena itu yang tampil hanya yang perlu untuk
 * memutuskan "boleh lewat atau tidak": nama, jabatan, perusahaan, jenis
 * dan nomor kartu, keadaan SAAT INI dan sisa harinya, unit beserta kelas.
 * Nomor SIM dan NIK disamarkan; tidak ada foto, telepon, alamat, atau
 * hasil MCU — hanya masa berlakunya.
 */
final class RincianVerifikasi
{
    /** @return array<string,mixed>|null null bila kodenya tidak sah atau barisnya tidak ada */
    public static function dariKode(string $kode): ?array
    {
        $baca = Verifikasi::baca($kode);
        if (! $baca) return null;

        return match ($baca['jenis']) {
            'MP' => self::permit(Permit::withoutGlobalScopes()->find($baca['id'])),
            'SP' => self::simper(Simper::withoutGlobalScopes()->find($baca['id'])),
            'ID' => self::induksi(InduksiOrang::withoutGlobalScopes()->find($baca['id'])),
            'MC' => self::mcu(McuOrang::withoutGlobalScopes()->find($baca['id'])),
            default => null,
        };
    }

    private static function permit(?Permit $p): ?array
    {
        if (! $p) return null;

        $habis  = $p->habisEfektif();
        $simper = Simper::withoutGlobalScopes()->where('permit_id', $p->id)->where('status', 'terbit')->orderByDesc('id')->first();
        $iup    = $p->iup()->withoutGlobalScopes()->first();
        $mcu    = $p->mcuOrang()->withoutGlobalScopes()->first();

        return [
            'jenis'      => 'Mine Permit'.($p->jenis_kartu && $p->jenis_kartu !== 'Mine Permit' ? ' — '.$p->jenis_kartu : ''),
            'nomor'      => $p->no_registrasi,
            'keadaan'    => self::keadaan($p->status === 'terbit' || $p->status === 'kedaluwarsa', (bool) $p->aktif, $habis),
            'berlaku'    => $habis?->toDateString(),
            'sisa'       => Zona::sisa($habis),
            'akses'      => WarnaAkses::CAKUPAN_AREA[strtoupper((string) $p->cakupan_area)] ?? null,
            'warna'      => WarnaAkses::sah($p->kode_warna) ? WarnaAkses::label($p->kode_warna) : null,
            'mcuSampai'  => $mcu?->berlaku_sampai?->toDateString(),
            'mcuSisa'    => Zona::sisa($mcu?->berlaku_sampai),
            'simper'     => $simper ? self::ringkasSimper($simper) : null,
            'ktt'        => $iup?->ktt,
            'penerbit'   => $iup?->name,
        ] + self::orang($p->pekerja()->withoutGlobalScopes()->first(), $mcu?->jabatan, $p->kontraktor()->withoutGlobalScopes()->first()?->name);
    }

    private static function simper(?Simper $s): ?array
    {
        if (! $s) return null;

        $habis = $s->habisEfektif();
        $p     = Permit::withoutGlobalScopes()->find($s->permit_id);

        return [
            'jenis'    => 'SIMPER',
            'nomor'    => $s->no_simper,
            'keadaan'  => self::keadaan($s->status === 'terbit', (bool) $s->aktif, $habis),
            'berlaku'  => $habis?->toDateString(),
            'sisa'     => Zona::sisa($habis),
            'simper'   => self::ringkasSimper($s),
            'penerbit' => $p?->iup()->withoutGlobalScopes()->first()?->name,
        ] + self::orang($s->pekerja()->withoutGlobalScopes()->first(), $p?->mcuOrang()->withoutGlobalScopes()->first()?->jabatan, null);
    }

    private static function induksi(?InduksiOrang $o): ?array
    {
        if (! $o) return null;

        $ind = $o->induksi()->withoutGlobalScopes()->first();
        $sah = $ind?->status === 'selesai' && $o->status !== 'gagal';

        return [
            'jenis'   => 'Sertifikat Induksi',
            'nomor'   => $ind?->no_registrasi,
            'keadaan' => $sah ? 'berlaku' : 'tidak-sah',
            'tanggal' => $ind?->tanggal?->toDateString(),
        ] + self::orang($o->pekerja()->withoutGlobalScopes()->first(), null, null);
    }

    private static function mcu(?McuOrang $o): ?array
    {
        if (! $o) return null;

        $mcu   = $o->mcu()->withoutGlobalScopes()->first();
        $habis = $o->berlaku_sampai;

        return [
            'jenis'   => 'Sertifikat MCU',
            'nomor'   => $mcu?->no_registrasi,
            'keadaan' => self::keadaan(in_array($mcu?->status, ['selesai', 'kedaluwarsa'], true), true, $habis),
            'berlaku' => $habis?->toDateString(),
            'sisa'    => Zona::sisa($habis),
        ] + self::orang($o->pekerja()->withoutGlobalScopes()->first(), $o->jabatan, null, $o->nama);
    }

    /** @return array<string,mixed> */
    private static function ringkasSimper(Simper $s): array
    {
        $unit = collect(DataKartu::unit($s))->filter(fn ($u) => $u['nama'] !== '')->map(fn ($u) => ['nama' => $u['nama'], 'kelas' => $u['kelas']])->values();

        return [
            'nomor'   => $s->no_simper,
            'sim'     => self::samarkan($s->no_simpol),
            'jenisSim'=> $s->jenis_simpol,
            'berlaku' => $s->habisEfektif()?->toDateString(),
            'aktif'   => (bool) $s->aktif && $s->status === 'terbit',
            'unit'    => $unit,
        ];
    }

    /** @return array<string,mixed> */
    private static function orang($pk, ?string $jabatan, ?string $kontraktor, ?string $nama = null): array
    {
        return [
            'nama'       => $pk?->nama ?? $nama,
            'jabatan'    => $jabatan,
            'perusahaan' => $kontraktor ?? $pk?->company()->withoutGlobalScopes()->first()?->name,
            'nik'        => self::samarkan($pk?->nik),
        ];
    }

    /** 'berlaku' | 'segera-habis' | 'habis' | 'nonaktif' | 'belum-terbit' */
    private static function keadaan(bool $terbit, bool $aktif, ?Carbon $habis): string
    {
        if (! $terbit) return 'belum-terbit';
        if (! $aktif) return 'nonaktif';
        if ($habis && $habis->lt(Waktu::kini()->startOfDay())) return 'habis';
        if ($habis && Zona::sisa($habis) <= 30) return 'segera-habis';

        return 'berlaku';
    }

    /** Sisakan empat huruf terakhir: `••••••••1234`. */
    public static function samarkan(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;

        $sisa = mb_strlen($v) <= 4 ? 1 : 4;

        return str_repeat('•', max(3, mb_strlen($v) - $sisa)).mb_substr($v, -$sisa);
    }
}
