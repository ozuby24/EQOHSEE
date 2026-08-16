<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `app_settings.value` menjadi TEKS, bukan kolom JSON.
 *
 * Kolomnya lahir sebagai `json`, tetapi tidak pernah dipakai sebagai
 * JSON. Yang disimpan di dalamnya adalah nilai tunggal: nama penyedia
 * AI ("anthropic"), nama model ("claude-sonnet-5"), dan kunci API yang
 * sudah terenkripsi ("eyJpdiI6..."). Tidak satu pun dari ketiganya
 * adalah dokumen JSON yang sah.
 *
 * Di SQLite hal itu tidak pernah terlihat: `json` di sana hanyalah TEXT
 * tanpa pemeriksaan apa pun, jadi semua tersimpan dengan baik. Di
 * PostgreSQL dan MySQL kolomnya sungguhan bertipe JSON dan MENOLAK
 * nilainya:
 *
 *     SQLSTATE[22P02] invalid input syntax for type json
 *     DETAIL: Token "anthropic" is invalid.
 *
 * Akibatnya di pemasangan sungguhan: menyimpan kunci AI selalu berakhir
 * galat 500, sementara "Uji sambungan" — yang tidak menulis apa pun —
 * berhasil. Kuncinya diuji, dinyatakan terhubung, lalu hilang. Satu-
 * satunya penyedia yang tampak bekerja adalah yang kuncinya dibaca dari
 * `.env`, sebab itu satu-satunya jalan yang tidak melewati tabel ini.
 *
 * Nilai numerik milik modul KO (`ko_warn_days` dan kawan-kawan)
 * kebetulan JSON yang sah — `90` adalah angka JSON — sehingga lolos
 * selama ini dan menyamarkan kerusakannya. Nilai itu tetap dibaca
 * dengan json_decode sesudah perubahan ini; angka di dalam kolom teks
 * tetap terurai menjadi angka.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) return;

        Schema::table('app_settings', function (Blueprint $t) {
            $t->text('value')->nullable()->change();
        });
    }

    /**
     * Kembali ke `json` akan MENOLAK baris yang sudah tersimpan — kunci
     * AI yang terenkripsi bukan JSON yang sah — sehingga membatalkan
     * migrasi ini berarti kehilangan datanya, bukan sekadar mengubah
     * bentuk kolomnya. Karena itu `down()` sengaja tidak melakukan apa
     * pun: teks menampung segala yang pernah ditampung json.
     */
    public function down(): void
    {
        // sengaja kosong — lihat keterangan di atas
    }
};
