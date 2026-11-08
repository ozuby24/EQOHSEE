<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Miners mengikuti Safe Track — tahap dasar (skema, alur, penyetuju).
 *
 * DIKEMBANGKAN DI TEMPAT, TIDAK DIBUANG LALU DIBUAT ULANG. HRIS, FROP,
 * dan dasbor bergantung pada mnr_pekerja, mnr_blok, dan mnr_jabatan —
 * absensi, cuti, lembur, slip gaji, dan kontrak menunjuk pekerja dengan
 * cascadeOnDelete. Tabel yang dibuang lalu dibuat ulang akan menghapus
 * seluruh riwayat HR itu tanpa satu pun galat. Karena itu yang dilakukan
 * di sini hanya MENAMBAH kolom dan tabel, lalu MENGISI yang baru dari
 * data yang sudah ada; tidak ada baris lama yang dihapus selain langkah
 * alur yang BELUM PERNAH DITINDAK dan tidak lagi ada pada rantainya.
 *
 * YANG BERUBAH ATURANNYA (keputusan pemilik, 7 Nov 2026: seluruh aturan
 * yang diperdebatkan mengikuti Safe Track):
 *
 *   1. Penyetuju BERNAMA per IUP (tabel mnr_skema), bukan "siapa pun
 *      pemegang peran". Diisi awal dari pemegang peran yang sekarang,
 *      supaya tidak ada antrean yang mendadak tanpa penyetuju.
 *   2. Langkah pertama alur adalah PENGAJU, bukan PJO. Langkah itu
 *      disetujui oleh tindakan "Kirim" pengajunya sendiri — seperti
 *      seq0 Safe Track — sehingga mitra kerja dapat mengirim berkasnya
 *      tanpa akun kedua.
 *   3. Rantai per dokumen mengikuti SkemaPersetujuan::RANTAI Safe Track:
 *      MCU berhenti di dokter; induksi pengaju→HSE; Mine Permit, SIMPER,
 *      dan penambahan unit pengaju→HSE→KTT; upgrade pengaju→HSE;
 *      perpanjangan pengaju→dokter→HSE→KTT.
 *   4. Dokumen tahu IUP PENERBITNYA (`iup_id`) terpisah dari perusahaan
 *      pemiliknya (`company_id`). Sebelumnya penerima surel dicari
 *      menurut company_id — dan dokumen buatan mitra (IUJP) menyurati
 *      OHSE milik mitra itu sendiri, yang biasanya tidak ada.
 *
 * KOLOM UNTUK TAHAP BERIKUTNYA ikut ditambahkan sekarang (MCU, induksi,
 * permit, SIMPER, perpanjangan, kartu), supaya paket-paket berikutnya
 * tidak perlu menyentuh skema tabel yang sama berkali-kali.
 *
 * PORTABEL: tanpa UPDATE … JOIN dan tanpa fungsi khas MySQL. Pengisian
 * dikerjakan baris demi baris lewat query builder — volumenya ratusan
 * sampai ribuan baris, bukan jutaan.
 */
return new class extends Migration
{
    /** Rantai baru per kunci alur — salinan beku Alur::LANGKAH saat migrasi ini ditulis. */
    private const RANTAI = [
        'mcu'                 => ['pengaju', 'dokter'],
        'induksi'             => ['pengaju', 'ohse'],
        'permit'              => ['pengaju', 'ohse', 'ktt'],
        'simper'              => ['pengaju', 'ohse', 'ktt'],
        'penambahan'          => ['pengaju', 'ohse', 'ktt'],
        'upgrade'             => ['pengaju', 'ohse'],
        'perpanjangan'        => ['pengaju', 'dokter', 'ohse', 'ktt'],
        'perpanjangan_permit' => ['pengaju', 'dokter', 'ohse', 'ktt'],
    ];

    /** Tabel dokumen per kunci `mnr_alur.dokumen`. */
    private const TABEL = [
        'mcu'                 => 'mnr_mcu',
        'induksi'             => 'mnr_induksi',
        'permit'              => 'mnr_permit',
        'simper'              => 'mnr_simper',
        'ajuan'               => 'mnr_simper_ajuan',
        'perpanjangan_permit' => 'mnr_permit_perpanjangan',
    ];

    public function up(): void
    {
        $this->skema();
        $this->kolom();
        $this->tabelBaru();

        $this->isiIup();
        $this->isiPermit();
        $this->isiMcu();
        $this->isiUnit();
        $this->isiSkema();
        $this->isiAlur();
    }

    /* ═══════════════════ struktur ═══════════════════ */

    private function skema(): void
    {
        /* Penyetuju bernama per IUP — padanan `skemas` Safe Track.
           `company_id` NULL adalah alur UMUM, cadangan bagi IUP yang
           belum punya barisnya sendiri; sama seperti baris tanpa iup_id
           di Safe Track. */
        Schema::create('mnr_skema', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->unique()->constrained()->nullOnDelete();

            $t->foreignId('dokter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('ohse_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('ktt_user_id')->nullable()->constrained('users')->nullOnDelete();

            /* Daftar alamat dipisah koma — "to" ikut menerima seperti
               penerima utama, "cc" hanya ditembusi. Satu peran sering
               dijalankan lebih dari satu orang, sedangkan langkah
               persetujuannya tetap satu. */
            $t->text('dokter_cc')->nullable();
            $t->text('ohse_to')->nullable();
            $t->text('ohse_cc')->nullable();
            $t->text('ktt_cc')->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    private function kolom(): void
    {
        /* Identitas perusahaan untuk label alur dan kartu — padanan
           kolom `corps` Safe Track yang belum dimiliki `companies`. */
        Schema::table('companies', function (Blueprint $t) {
            $t->string('nama_pendek', 60)->nullable();
            $t->string('logo_grup', 500)->nullable();
            $t->string('jabatan_ktt', 120)->nullable();
            $t->string('ttd_ktt', 500)->nullable();
            $t->string('no_izin', 120)->nullable();
            $t->string('channel_darurat', 120)->nullable();
        });

        Schema::table('mnr_alur', function (Blueprint $t) {
            /* SIAPA YANG DITUGASI, terpisah dari siapa yang BERTINDAK
               (`user_id`). Admin boleh bertindak atas nama yang
               ditugasi; tanpa kolom ini riwayatnya tidak dapat
               membedakan keduanya. */
            $t->foreignId('ditugaskan_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['ditugaskan_user_id', 'keadaan']);
        });

        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->date('tanggal_nonaktif')->nullable();
            $t->string('alasan_nonaktif', 200)->nullable();
        });

        Schema::table('mnr_mcu', function (Blueprint $t) {
            $t->foreignId('iup_id')->nullable()->constrained('companies')->nullOnDelete();

            /* Pilihan dokter 3 / 6 / 12 bulan saat menutup surat. */
            $t->unsignedTinyInteger('bulan_berlaku')->nullable();
        });

        Schema::table('mnr_mcu_orang', function (Blueprint $t) {
            $t->string('no_id', 40)->nullable();
            $t->string('jenis_mcu', 20)->nullable();
            $t->string('level_risiko', 20)->nullable();
            $t->unsignedTinyInteger('bulan_berlaku')->nullable();
            $t->text('catatan_perbaikan')->nullable();
            $t->string('berkas_rujukan', 500)->nullable();
        });

        Schema::table('mnr_mcu_rujukan', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('mnr_induksi', function (Blueprint $t) {
            $t->foreignId('iup_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->text('catatan')->nullable();
        });

        Schema::table('mnr_permit', function (Blueprint $t) {
            $t->foreignId('iup_id')->nullable()->constrained('companies')->nullOnDelete();

            /* Mine Permit | Magazine | Blasting — `typecard` Safe Track. */
            $t->string('jenis_kartu', 20)->nullable();

            $t->boolean('aktif')->default(true);
            $t->date('tanggal_nonaktif')->nullable();
            $t->string('alasan_nonaktif', 200)->nullable();

            $t->string('berkas_ktp', 500)->nullable();
            $t->string('berkas_permohonan', 500)->nullable();
            $t->text('catatan')->nullable();
        });

        Schema::table('mnr_simper', function (Blueprint $t) {
            $t->foreignId('iup_id')->nullable()->constrained('companies')->nullOnDelete();

            /* Dari mana tanggal SIMPOL berasal: auto (dibaca dari berkas),
               manual, atau perpanjangan — `sim_expired_source`. */
            $t->string('simpol_sumber', 12)->nullable();
            $t->text('simpol_meta')->nullable();
            $t->string('berkas_pengalaman', 500)->nullable();

            $t->boolean('aktif')->default(true);
            $t->date('tanggal_nonaktif')->nullable();
            $t->string('alasan_nonaktif', 200)->nullable();
        });

        /* "SIO sertifikat/Kompetensi" — pilihan jenis SIM Safe Track —
           panjangnya 25 huruf. */
        Schema::table('mnr_simper', function (Blueprint $t) {
            $t->string('jenis_simpol', 40)->nullable()->change();
        });

        Schema::table('mnr_simper_unit', function (Blueprint $t) {
            /* F Full · R Restricted · P Probation · I Instructor · T —
               dicetak per unit di belakang kartu. */
            $t->string('authority', 2)->nullable();
            $t->string('berkas_evaluasi', 500)->nullable();
        });

        Schema::table('mnr_simper_ajuan', function (Blueprint $t) {
            $t->foreignId('iup_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->string('no_simpol_baru', 40)->nullable();
            $t->date('tanggal_mcu')->nullable();
            $t->string('berkas_mcu', 500)->nullable();
            $t->string('berkas_form', 500)->nullable();
            $t->string('berkas_pengalaman', 500)->nullable();
        });

        Schema::table('mnr_simper_ajuan_unit', function (Blueprint $t) {
            /* Upgrade menunjuk unit yang SUDAH ada pada kartunya. */
            $t->foreignId('simper_unit_id')->nullable()->constrained('mnr_simper_unit')->nullOnDelete();
            $t->string('authority', 2)->nullable();
            $t->string('berkas_praktek', 500)->nullable();
            $t->string('berkas_evaluasi', 500)->nullable();
            $t->string('berkas_upgrade', 500)->nullable();
            $t->string('berkas_ddt', 500)->nullable();
            $t->string('berkas_jam', 500)->nullable();
        });
    }

    private function tabelBaru(): void
    {
        /* Satu baris unit SIMPER memuat BEBERAPA type unit — Safe Track
           menyimpannya sebagai daftar nama dipisah koma. */
        Schema::create('mnr_simper_unit_jenis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('simper_unit_id')->constrained('mnr_simper_unit')->cascadeOnDelete();
            $t->foreignId('jenis_unit_id')->constrained('mnr_jenis_unit')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['simper_unit_id', 'jenis_unit_id']);
        });

        Schema::create('mnr_simper_ajuan_unit_jenis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ajuan_unit_id')->constrained('mnr_simper_ajuan_unit')->cascadeOnDelete();
            $t->foreignId('jenis_unit_id')->constrained('mnr_jenis_unit')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['ajuan_unit_id', 'jenis_unit_id']);
        });

        /* Perpanjangan Mine Permit — `extendcards` Safe Track. */
        Schema::create('mnr_permit_perpanjangan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('iup_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->foreignId('permit_id')->constrained('mnr_permit')->cascadeOnDelete();

            $t->string('no_registrasi', 40)->nullable()->index();
            $t->date('tanggal')->nullable();
            $t->date('tanggal_mcu')->nullable();

            /* Tanggal habis BARU yang diminta — `datesimpol` Safe Track. */
            $t->date('berlaku_baru')->nullable();

            $t->string('berkas_form', 500)->nullable();
            $t->string('berkas_mcu', 500)->nullable();

            $t->string('status', 20)->default('draf')->index();
            $t->text('catatan')->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        /* Riwayat setiap perpanjangan — `riwayat_perpanjangans`. */
        Schema::create('mnr_riwayat_perpanjangan', function (Blueprint $t) {
            $t->id();
            $t->string('jenis', 10);
            $t->unsignedBigInteger('dokumen_id');
            $t->unsignedBigInteger('perpanjangan_id')->nullable();
            $t->unsignedSmallInteger('urutan')->default(1);
            $t->date('berlaku_lama')->nullable();
            $t->date('berlaku_baru')->nullable();
            $t->text('data_lama')->nullable();
            $t->text('data_baru')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['jenis', 'dokumen_id']);
        });

        /* Pola lembar cetak kartu yang disimpan — `SkemaCetak`. */
        Schema::create('mnr_tata_cetak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama', 80);
            $t->text('pengaturan')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'nama']);
        });
    }

    /* ═══════════════════ pengisian ═══════════════════ */

    /**
     * IUP penerbit tiap dokumen: induk perusahaannya bila ia mitra
     * (punya parent_id), atau perusahaannya sendiri bila ia IUP.
     */
    private function isiIup(): void
    {
        $induk = DB::table('companies')->pluck('parent_id', 'id')->all();

        $iupDari = fn ($c) => $c === null ? null : (($induk[$c] ?? null) ?: (int) $c);

        foreach (['mnr_mcu', 'mnr_induksi', 'mnr_permit', 'mnr_simper'] as $tabel) {
            foreach (DB::table($tabel)->whereNull('iup_id')->whereNotNull('company_id')
                         ->get(['id', 'company_id']) as $b) {
                DB::table($tabel)->where('id', $b->id)->update(['iup_id' => $iupDari($b->company_id)]);
            }
        }

        $simper = DB::table('mnr_simper')->pluck('iup_id', 'id')->all();

        foreach (DB::table('mnr_simper_ajuan')->whereNull('iup_id')->get(['id', 'simper_id']) as $a) {
            if ($iup = $simper[$a->simper_id] ?? null) {
                DB::table('mnr_simper_ajuan')->where('id', $a->id)->update(['iup_id' => $iup]);
            }
        }
    }

    /**
     * Mine Permit: kontraktor bagi kartu buatan mitra, status aktif dari
     * pencabutan, dan jenis kartu dari kategorinya.
     */
    private function isiPermit(): void
    {
        $induk    = DB::table('companies')->pluck('parent_id', 'id')->all();
        $kategori = DB::table('mnr_kategori_permit')->pluck('nama', 'id')->all();

        foreach (DB::table('mnr_permit')->get() as $p) {
            $ubah = [];

            /* Pemilik baris adalah mitra (IUJP) → ia kontraktornya. */
            if ($p->kontraktor_id === null && $p->company_id && ($induk[$p->company_id] ?? null)) {
                $ubah['kontraktor_id'] = $p->company_id;
            }

            if ($p->status === 'dicabut') {
                $ubah['aktif']            = false;
                $ubah['tanggal_nonaktif'] = $p->tanggal_cabut;
                $ubah['alasan_nonaktif']  = $p->alasan_cabut;
            }

            if ($p->jenis_kartu === null) {
                $nama = mb_strtolower((string) ($kategori[$p->kategori_permit_id] ?? ''));

                $ubah['jenis_kartu'] = match (true) {
                    str_contains($nama, 'blasting') => 'Blasting',
                    str_contains($nama, 'magazine') => 'Magazine',
                    default                         => 'Mine Permit',
                };
            }

            if ($ubah) DB::table('mnr_permit')->where('id', $p->id)->update($ubah);
        }
    }

    /** Masa berlaku MCU yang sudah tercatat dihitung 12 bulan. */
    private function isiMcu(): void
    {
        DB::table('mnr_mcu_orang')
            ->whereNull('bulan_berlaku')
            ->whereNotNull('berlaku_sampai')
            ->update(['bulan_berlaku' => 12]);
    }

    /**
     * Unit SIMPER: huruf kewenangan Safe Track dari kewenangan lama, dan
     * type unit ke tabel pasangannya. Kolom `kewenangan` lama TIDAK
     * dibuang — ia tetap menyimpan bunyi aslinya.
     */
    private function isiUnit(): void
    {
        $peta = ['operator' => 'F', 'pengawas' => 'R', 'trainer' => 'I'];
        $kini = now();

        foreach (DB::table('mnr_simper_unit')->get(['id', 'kewenangan', 'jenis_unit_id', 'authority']) as $u) {
            if ($u->authority === null && isset($peta[$u->kewenangan])) {
                DB::table('mnr_simper_unit')->where('id', $u->id)->update(['authority' => $peta[$u->kewenangan]]);
            }

            if ($u->jenis_unit_id) {
                DB::table('mnr_simper_unit_jenis')->insertOrIgnore([
                    'simper_unit_id' => $u->id, 'jenis_unit_id' => $u->jenis_unit_id,
                    'created_at' => $kini, 'updated_at' => $kini,
                ]);
            }
        }

        foreach (DB::table('mnr_simper_ajuan_unit')->get(['id', 'kewenangan', 'jenis_unit_id', 'authority']) as $u) {
            if ($u->authority === null && isset($peta[$u->kewenangan])) {
                DB::table('mnr_simper_ajuan_unit')->where('id', $u->id)->update(['authority' => $peta[$u->kewenangan]]);
            }

            if ($u->jenis_unit_id) {
                DB::table('mnr_simper_ajuan_unit_jenis')->insertOrIgnore([
                    'ajuan_unit_id' => $u->id, 'jenis_unit_id' => $u->jenis_unit_id,
                    'created_at' => $kini, 'updated_at' => $kini,
                ]);
            }
        }
    }

    /**
     * Penyetuju awal per IUP, dari pemegang peran yang sekarang.
     *
     * Sebelum ini yang disurati dan yang boleh menindak adalah SIAPA PUN
     * pemegang peran di perusahaan dokumennya. Mengisi skema dari orang
     * yang sama menjaga antrean yang sedang berjalan tetap punya
     * penerimanya; yang kosong tetap kosong dan layar Alur Persetujuan
     * menyebutnya, bukan diisi dengan tebakan.
     */
    private function isiSkema(): void
    {
        $iup = collect();

        foreach (self::TABEL as $tabel) {
            if (Schema::hasColumn($tabel, 'iup_id')) {
                $iup = $iup->merge(DB::table($tabel)->whereNotNull('iup_id')->distinct()->pluck('iup_id'));
            }
        }

        $kini = now();

        foreach ($iup->unique()->filter()->values() as $c) {
            if (DB::table('mnr_skema')->where('company_id', $c)->exists()) continue;

            $pertama = fn (string $kolom, string $nilai) => DB::table('users')
                ->where('company_id', $c)->where($kolom, $nilai)->orderBy('id')->value('id');

            $baris = [
                'dokter_user_id' => $pertama('ohse_role', 'paramedis'),
                'ohse_user_id'   => $pertama('ohse_role', 'ohse'),
                'ktt_user_id'    => $pertama('lms_role', 'ktt'),
            ];

            if (array_filter($baris) === []) continue;

            DB::table('mnr_skema')->insert($baris + [
                'company_id' => $c, 'created_at' => $kini, 'updated_at' => $kini,
            ]);
        }
    }

    /**
     * Langkah alur yang sudah ada dipetakan ke rantai Safe Track.
     *
     *   · 'pjo' menjadi 'pengaju' — langkah yang disetujui oleh "Kirim".
     *   · Dokumen yang BELUM PERNAH DIKIRIM (langkah pertamanya masih
     *     menunggu) disusun ulang penuh menurut rantai barunya.
     *   · Dokumen yang sudah berjalan mempertahankan langkah yang sudah
     *     ditindak; yang hilang hanya langkah MENUNGGU yang tidak lagi
     *     ada pada rantainya (mis. langkah OHSE pada MCU — Safe Track
     *     menutup MCU di dokter). Statusnya lalu dihitung ulang.
     *   · Yang ditugasi diisi: pengaju = pembuat dokumen; langkah yang
     *     sudah ditindak = yang menindaknya; langkah menunggu = penyetuju
     *     IUP-nya pada mnr_skema.
     */
    private function isiAlur(): void
    {
        DB::table('mnr_alur')->where('peran', 'pjo')->update(['peran' => 'pengaju']);

        $skema = DB::table('mnr_skema')->get()->keyBy(fn ($s) => (string) $s->company_id);
        $kini  = now();

        $pasangan = DB::table('mnr_alur')->select('dokumen', 'dokumen_id')->distinct()->get();

        foreach ($pasangan as $p) {
            $tabel = self::TABEL[$p->dokumen] ?? null;
            if (! $tabel) continue;

            $dok = DB::table($tabel)->where('id', $p->dokumen_id)->first();

            /* Langkah yatim — dokumennya sudah tidak ada. Ditinggalkan
               apa adanya; ia tidak terbaca oleh layar mana pun. */
            if (! $dok) continue;

            $kunci  = $p->dokumen === 'ajuan' ? ($dok->jenis ?? 'penambahan') : $p->dokumen;
            $rantai = self::RANTAI[$kunci] ?? self::RANTAI['permit'];

            $langkah = DB::table('mnr_alur')->where('dokumen', $p->dokumen)
                ->where('dokumen_id', $p->dokumen_id)->orderBy('urutan')->get();

            $pertama   = $langkah->first();
            $belumKirim = $pertama && $pertama->peran === 'pengaju' && $pertama->keadaan === 'menunggu';

            if ($belumKirim) {
                /* Belum ada keputusan siapa pun di dalamnya: disusun ulang. */
                DB::table('mnr_alur')->whereIn('id', $langkah->pluck('id'))->delete();

                foreach ($rantai as $i => $peran) {
                    DB::table('mnr_alur')->insert([
                        'dokumen' => $p->dokumen, 'dokumen_id' => $p->dokumen_id,
                        'urutan' => $i + 1, 'peran' => $peran, 'keadaan' => 'menunggu',
                        'created_at' => $kini, 'updated_at' => $kini,
                    ]);
                }
            } else {
                foreach ($langkah as $l) {
                    if ($l->keadaan === 'menunggu' && ! in_array($l->peran, $rantai, true)) {
                        DB::table('mnr_alur')->where('id', $l->id)->delete();
                    }
                }
            }

            $langkah = DB::table('mnr_alur')->where('dokumen', $p->dokumen)
                ->where('dokumen_id', $p->dokumen_id)->orderBy('urutan')->get();

            $s = $skema[(string) ($dok->iup_id ?? '')] ?? $skema[''] ?? null;

            foreach ($langkah as $l) {
                $tugas = match (true) {
                    $l->peran === 'pengaju'   => $dok->user_id ?? $l->user_id,
                    $l->keadaan !== 'menunggu' => $l->user_id,
                    default                    => $s?->{$l->peran.'_user_id'} ?? null,
                };

                DB::table('mnr_alur')->where('id', $l->id)->update(['ditugaskan_user_id' => $tugas]);
            }

            /* Status dihitung ulang dari alurnya, kecuali keadaan yang
               tidak ditentukan alur: dicabut, dan status akhir yang sudah
               ditetapkan. */
            if (in_array($dok->status, ['dicabut'], true)) continue;

            $status = $this->status($p->dokumen, $langkah);

            if ($status !== null && $status !== $dok->status) {
                DB::table($tabel)->where('id', $p->dokumen_id)->update(['status' => $status]);
            }
        }
    }

    /** Status dokumen menurut langkah-langkahnya — sama dengan Jalur::status(). */
    private function status(string $dokumen, $langkah): ?string
    {
        if ($langkah->isEmpty()) return null;

        if ($langkah->contains(fn ($l) => $l->keadaan === 'tolak')) return 'ditolak';

        /* Dikembalikan kepada pengaju: kembali menjadi draf sampai ia
           mengirim ulang. */
        if ($langkah->contains(fn ($l) => $l->keadaan === 'dikembalikan')) return 'draf';

        if ($langkah->first()->keadaan !== 'setuju') return 'draf';

        if ($langkah->every(fn ($l) => $l->keadaan === 'setuju')) {
            return in_array($dokumen, ['mcu', 'induksi', 'ajuan', 'perpanjangan_permit'], true) ? 'selesai' : 'terbit';
        }

        foreach ($langkah as $i => $l) {
            if ($l->keadaan !== 'setuju') {
                return match ($l->peran) {
                    'dokter' => 'diperiksa',
                    'ohse'   => 'ohse',
                    'ktt'    => 'ktt',
                    default  => 'diajukan',
                };
            }
        }

        return null;
    }

    public function down(): void
    {
        /* PERINGATAN: membuang tabel dan kolom baru beserta isinya.
           Langkah 'pengaju' dikembalikan menjadi 'pjo'; langkah yang
           dibuang saat up() TIDAK dapat dipulihkan. */
        DB::table('mnr_alur')->where('peran', 'pengaju')->update(['peran' => 'pjo']);

        foreach (['mnr_tata_cetak', 'mnr_riwayat_perpanjangan', 'mnr_permit_perpanjangan',
                  'mnr_simper_ajuan_unit_jenis', 'mnr_simper_unit_jenis', 'mnr_skema'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::table('mnr_simper_ajuan_unit', function (Blueprint $t) {
            $t->dropConstrainedForeignId('simper_unit_id');
            $t->dropColumn(['authority', 'berkas_praktek', 'berkas_evaluasi', 'berkas_upgrade', 'berkas_ddt', 'berkas_jam']);
        });

        Schema::table('mnr_simper_ajuan', function (Blueprint $t) {
            $t->dropConstrainedForeignId('iup_id');
            $t->dropColumn(['no_simpol_baru', 'tanggal_mcu', 'berkas_mcu', 'berkas_form', 'berkas_pengalaman']);
        });

        Schema::table('mnr_simper_unit', fn (Blueprint $t) => $t->dropColumn(['authority', 'berkas_evaluasi']));

        Schema::table('mnr_simper', function (Blueprint $t) {
            $t->dropConstrainedForeignId('iup_id');
            $t->dropColumn(['simpol_sumber', 'simpol_meta', 'berkas_pengalaman', 'aktif', 'tanggal_nonaktif', 'alasan_nonaktif']);
        });

        Schema::table('mnr_permit', function (Blueprint $t) {
            $t->dropConstrainedForeignId('iup_id');
            $t->dropColumn(['jenis_kartu', 'aktif', 'tanggal_nonaktif', 'alasan_nonaktif',
                            'berkas_ktp', 'berkas_permohonan', 'catatan']);
        });

        Schema::table('mnr_induksi', function (Blueprint $t) {
            $t->dropConstrainedForeignId('iup_id');
            $t->dropColumn('catatan');
        });

        Schema::table('mnr_mcu_rujukan', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));

        Schema::table('mnr_mcu_orang', fn (Blueprint $t) => $t->dropColumn([
            'no_id', 'jenis_mcu', 'level_risiko', 'bulan_berlaku', 'catatan_perbaikan', 'berkas_rujukan',
        ]));

        Schema::table('mnr_mcu', function (Blueprint $t) {
            $t->dropConstrainedForeignId('iup_id');
            $t->dropColumn('bulan_berlaku');
        });

        Schema::table('mnr_pekerja', fn (Blueprint $t) => $t->dropColumn(['tanggal_nonaktif', 'alasan_nonaktif']));

        Schema::table('mnr_alur', function (Blueprint $t) {
            $t->dropIndex(['ditugaskan_user_id', 'keadaan']);
            $t->dropConstrainedForeignId('ditugaskan_user_id');
        });

        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn([
            'nama_pendek', 'logo_grup', 'jabatan_ktt', 'ttd_ktt', 'no_izin', 'channel_darurat',
        ]));
    }
};
