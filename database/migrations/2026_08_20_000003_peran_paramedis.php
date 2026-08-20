<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Peran paramedis pada kolom `ohse_role` yang sudah ada.
 *
 * Tidak menambah kolom. `ohse_role` sejak awal berarti "peran orang ini
 * di dalam tim keselamatan & kesehatan kerja", dan paramedis persis itu
 * — hanya saja satu-satunya nilai yang pernah dipakai adalah 'ohse'.
 *
 * SATU KOLOM BERARTI KEDUANYA SALING MENIADAKAN, dan itu memang yang
 * dikehendaki. Tahap paramedis ada supaya yang MEMBACA hasil
 * pemeriksaan bukan orang yang MEMUTUSKAN kelayakannya; satu orang yang
 * memegang kedua peran dapat memaraf tahap paramedis lalu menyetujui
 * pengajuannya sendiri sebagai OHSE, dan pemisahan itu lenyap tanpa
 * satu pun tanda di layar.
 *
 * Tidak ada kebuntuan yang timbul karenanya: paraf tidak menahan
 * keputusan (lihat App\Support\Tahap), jadi tambang yang belum menunjuk
 * paramedis tetap dapat menjalankan seluruh pengajuannya — rantainya
 * hanya menyebutkan bahwa tahap itu belum diparaf, yang memang keadaan
 * sebenarnya.
 *
 * Migrasi ini sengaja tidak mengubah data. Ia ada sebagai catatan
 * bahwa nilai yang sah untuk `ohse_role` bertambah, sebab kolomnya
 * bertipe string tanpa constraint dan perubahannya tidak akan terlihat
 * pada skema mana pun.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
