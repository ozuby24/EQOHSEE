<script setup lang="ts">
/*
  Dasbor Lifting & Rigging Management.

  Urutannya mengikuti pertanyaan pengawas pagi: berapa pengangkatan
  bulan ini dan berapa yang kritis (panggung), apa yang menunggu
  persetujuan dan apa yang dijadwalkan dua minggu ke depan, crane dan
  alat bantu mana yang tidak layak digunakan atau hampir habis masa
  ujinya — lalu polanya: tren, sebaran beban-radius, utilisasi, 6 pilar
  kajian, dan penyebab alat ditolak saat riksa uji.
*/
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import SebarRencana from '../../Components/Angkat/SebarRencana.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Donat from '../../Grafik/Donat.vue';
import Kolom from '../../Grafik/Kolom.vue';
import Radar from '../../Grafik/Radar.vue';
import BatangTumpuk from '../../Grafik/BatangTumpuk.vue';
import { n } from '../../energi';
import { klas, kelayakan, simulasi, status, waktu, tanggal, sisaHari, KLAS } from '../../angkat';

const props = propHalaman();
const r = computed(() => props.ringkas ?? {});

const stat = computed(() => [
  { label: 'Pengangkatan bulan ini', nilai: n(r.value.bulan_ini, 0), satuan: 'rencana', ikon: 'construction',
    catatan: `${r.value.kritis_bulan} kritis · ${r.value.selesai_bulan} selesai` },
  { label: 'Menunggu persetujuan', nilai: n(r.value.menunggu, 0), satuan: 'rencana', ikon: 'clipboard-check',
    catatan: r.value.menunggu_ktt ? `${r.value.menunggu_ktt} menunggu KTT` : 'Tidak ada yang menunggu KTT' },
  { label: 'Crane layak digunakan', nilai: n(r.value.alat_pct, 0), satuan: '%', ikon: 'shield-check',
    catatan: `${r.value.alat_layak} dari ${r.value.alat} unit ber-SUKET berlaku` },
  { label: 'Utilisasi crane rata-rata', nilai: n(r.value.util_rata, 1), satuan: '%', ikon: 'gauge',
    catatan: `Tertinggi ${n(r.value.util_maks, 1)}% · batas 85%` },
]);
const lencana = computed(() => [
  ...(r.value.karantina ? [`${r.value.karantina} dikarantina`] : ['Tidak ada alat dikarantina']),
  ...(r.value.kedaluwarsa ? [`${r.value.kedaluwarsa} lewat masa uji`] : []),
  'Permenaker 8/2020 · Kepmen ESDM 1827/2018',
]);

const tren = computed(() => props.tren ?? []);
const deretTren = computed(() => (['rutin', 'rekayasa', 'kritis'] as const).map(k => ({ nama: KLAS[k].pendek, nilai: tren.value.map((t: any) => t[k]), warna: KLAS[k].warna })));
const bagianKlas = computed(() => (props.klasifikasi ?? []).map((k: any) => ({ label: k.label, nilai: k.nilai, warna: klas(k.kode).warna })));
const totalKlas = computed(() => bagianKlas.value.reduce((a: number, b: any) => a + b.nilai, 0));

const hist = computed(() => props.histogram ?? []);
const warnaHist = ['#15803D', '#15803D', '#2A78D6', '#7C3AED', '#D97706', '#DC2626'];

const pilar = computed(() => props.pilar ?? []);
const sumbuPilar = computed(() => pilar.value.map((p: any) => ({ label: p.kode, sub: p.nama })));

const barisGear = computed(() => (props.gearJenis ?? []).map((g: any) => ({
  label: g.label, sub: `${g.layak + g.segera + g.kedaluwarsa + g.karantina} unit`, url: '/pengangkatan/peralatan?tab=gear',
  bagian: [
    { nama: 'Layak', nilai: g.layak, warna: kelayakan('layak').warna },
    { nama: 'Segera riksa', nilai: g.segera, warna: kelayakan('segera').warna },
    { nama: 'Kedaluwarsa', nilai: g.kedaluwarsa, warna: kelayakan('kedaluwarsa').warna },
    { nama: 'Karantina / afkir', nilai: g.karantina, warna: kelayakan('karantina').warna },
  ],
})));

const sebab = computed(() => props.sebab ?? []);
const statusRencana = computed(() => (props.statusRencana ?? []).filter((s: any) => s.nilai > 0).map((s: any) => ({ label: s.label, nilai: s.nilai, warna: status(s.kode).warna })));
</script>

<template>
  <Head title="Lifting & Rigging Management" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <Link href="/pengangkatan/rencana/baru" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="calculator" :ukuran="16" />Buat rencana &amp; simulasi</Link>
        <Link href="/pengangkatan/rencana" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="clipboard-list" :ukuran="16" />Rencana</Link>
        <Link href="/pengangkatan/peralatan" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="link-2" :ukuran="16" />Register PAA</Link>
        <Link href="/pengangkatan/riksa-uji" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="scan-eye" :ukuran="16" />Riksa uji</Link>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Panggung tema="angkat" judul="Pengangkatan & kelayakan pesawat angkat"
              :sub="`${n(r.alat, 0)} crane · ${n(r.gear, 0)} alat bantu terdaftar · ${n(r.riksa_12, 0)} riksa uji dalam 12 bulan · penolakan ${r.tolak_12 === null ? '—' : n(r.tolak_12, 1) + '%'}`"
              :stat="stat" :lencana="lencana" />

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
      <Kartu judul="Menunggu persetujuan" :ket="(props.menunggu ?? []).length ? 'Pengangkatan kritis disetujui KTT' : 'Tidak ada rencana yang menunggu'" ikon="clipboard-check" warna="amber" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="p in props.menunggu" :key="p.id" class="px-5 py-3 flex items-center gap-3">
            <span class="w-2 self-stretch rounded-full shrink-0" :style="{ background: klas(p.klasifikasi).warna }" />
            <div class="min-w-0 flex-1">
              <Link :href="`/pengangkatan/rencana/${p.id}`" class="text-[13px] font-bold text-cam-ink hover:underline">{{ p.kode }} · {{ p.judul }}</Link>
              <p class="text-[11.5px] text-stone-500 truncate">{{ p.lokasi }} · {{ waktu(p.jadwal_at) }} · {{ n(p.berat_kotor_t, 1) }} t @ {{ n(p.radius_m, 0) }} m · {{ n(p.utilisasi_pct, 1) }}%</p>
            </div>
            <span class="pr-pil" :class="klas(p.klasifikasi).kelas">{{ klas(p.klasifikasi).pendek }}</span>
            <span class="pr-pil" :class="simulasi(p.putusan).kelas">{{ simulasi(p.putusan).pendek }}</span>
          </li>
          <li v-if="!(props.menunggu ?? []).length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Semua rencana yang diajukan sudah diputuskan.</li>
        </ul>
      </Kartu>
      <div class="ka-kisi content-start">
        <KartuAngka label="Alat bantu layak digunakan" :nilai="r.gear_pct" :desimal="0" satuan="%" ikon="link-2" warna="hijau"
                    :catatan="`${r.gear_layak} dari ${r.gear} sling, rantai, shackle, spreader`" />
        <KartuAngka label="Karantina & kedaluwarsa" :nilai="(r.karantina ?? 0) + (r.kedaluwarsa ?? 0)" :desimal="0" satuan="unit" ikon="shield-x" warna="merah"
                    catatan="Terkunci dari rencana mana pun" />
        <KartuAngka label="Riksa uji jatuh tempo ≤ 30 hari" :nilai="r.segera" :desimal="0" satuan="unit" ikon="calendar-clock" warna="amber"
                    catatan="Jadwalkan PJK3 sebelum masa uji habis" />
        <KartuAngka label="Skor kajian 6 pilar" :nilai="r.skor_kajian" :desimal="1" satuan="/100" ikon="clipboard-check" warna="biru"
                    :catatan="r.nogo ? `${r.nogo} rencana NO-GO perlu dirancang ulang` : 'Tidak ada rencana NO-GO'" />
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Pengangkatan per bulan" catatan="12 bulan menurut klasifikasi; garis = utilisasi crane rata-rata" :angka="`${tren.reduce((a: number, t: any) => a + t.rutin + t.rekayasa + t.kritis, 0)} rencana`">
        <Kolom :label="tren.map((t: any) => t.label)" :deret="deretTren" :garis="{ nama: 'Utilisasi rata-rata (%)', nilai: tren.map((t: any) => t.util), warna: '#0F172A' }" satuan="rencana" :tinggi="220" />
      </KartuGrafik>
      <KartuGrafik judul="Klasifikasi pengangkatan" catatan="Kritis wajib kajian lengkap dan persetujuan KTT" :angka="`${totalKlas} rencana`">
        <Donat :bagian="bagianKlas" :tengah="n(totalKlas, 0)" tengah-label="rencana" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <Kartu judul="Beban terhadap radius" ket="Setiap titik satu rencana · arahkan kursor, klik untuk membuka" ikon="crosshair" warna="biru">
        <SebarRencana :titik="props.sebar ?? []" @pilih="(id: number) => router.visit(`/pengangkatan/rencana/${id}`)" />
      </Kartu>
      <KartuGrafik judul="Sebaran utilisasi crane" catatan="Di atas 75% = kritis; di atas 85% = NO-GO" :angka="`rata-rata ${n(r.util_rata, 1)}%`">
        <Kolom :label="hist.map((h: any) => h.label)" :deret="hist.map((h: any, i: number) => ({ nama: h.label, nilai: hist.map((_: any, j: number) => (j === i ? h.jumlah : null)), warna: warnaHist[i] }))" satuan="rencana" :tinggi="230" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
      <KartuGrafik judul="Kajian teknis 6 pilar" catatan="Rata-rata skor rencana aktif (0–100)" :angka="`${n(r.skor_kajian, 1)}`">
        <Radar :sumbu="sumbuPilar" :deret="[{ nama: 'Skor rata-rata', nilai: pilar.map((p: any) => p.skor), teks: pilar.map((p: any) => n(p.skor, 0)), warna: '#1D4ED8' }]" :ukuran="300" />
        <template #tabel>
          <table><thead><tr><th>Pilar</th><th>Nama</th><th>Skor</th></tr></thead>
            <tbody><tr v-for="p in pilar" :key="p.kode"><td>{{ p.kode }}</td><td>{{ p.nama }}</td><td class="num">{{ n(p.skor, 1) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
      <Kartu judul="Alat bantu angkat per jenis" ket="Klik untuk membuka register" ikon="link-2" warna="hijau">
        <BatangTumpuk :baris="barisGear" satuan="unit" />
      </Kartu>
      <KartuGrafik judul="Status rencana" catatan="Seluruh rencana 12 bulan" :angka="`${statusRencana.reduce((a: number, s: any) => a + s.nilai, 0)}`">
        <Donat :bagian="statusRencana" tengah-label="rencana" />
      </KartuGrafik>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
      <Kartu judul="Jadwal 14 hari ke depan" :ket="(props.jadwal ?? []).length ? 'Diajukan & disetujui' : 'Belum ada jadwal'" ikon="calendar-check" warna="biru" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="p in props.jadwal" :key="p.id" class="px-5 py-3">
            <div class="flex items-center justify-between gap-2">
              <Link :href="`/pengangkatan/rencana/${p.id}`" class="text-[12.5px] font-bold text-cam-ink hover:underline truncate">{{ p.kode }} · {{ p.judul }}</Link>
              <span class="pr-pil shrink-0" :class="status(p.status).kelas">{{ status(p.status).pendek }}</span>
            </div>
            <p class="text-[11.5px] text-stone-500">{{ waktu(p.jadwal_at) }} · {{ p.alat_label }} · {{ n(p.berat_kotor_t, 1) }} t</p>
          </li>
          <li v-if="!(props.jadwal ?? []).length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Tidak ada pengangkatan terjadwal.</li>
        </ul>
      </Kartu>
      <Kartu judul="Riksa uji jatuh tempo" :ket="(props.tempo ?? []).length ? 'Kedaluwarsa & ≤ 30 hari' : 'Semua masa uji aman'" ikon="calendar-clock" warna="amber" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="t in props.tempo" :key="t.objek + t.id" class="px-5 py-3 flex items-center gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-[12.5px] font-bold text-cam-ink truncate">{{ t.kode }} · {{ t.nama }}</p>
              <p class="text-[11.5px] text-stone-500">{{ tanggal(t.tanggal) }} · {{ sisaHari(t.tanggal) }}</p>
            </div>
            <span class="pr-pil" :class="kelayakan(t.status).kelas">{{ kelayakan(t.status).pendek }}</span>
            <Link :href="`/pengangkatan/riksa-uji/baru?objek=${t.objek}&id=${t.id}`" class="eq-btn-mini">Riksa</Link>
          </li>
          <li v-if="!(props.tempo ?? []).length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Tidak ada yang jatuh tempo.</li>
        </ul>
      </Kartu>
      <KartuGrafik judul="Penyebab tidak layak saat riksa uji" catatan="12 bulan — perbaiki perawatan dari yang teratas" :angka="`${sebab.reduce((a: number, s: any) => a + s.jumlah, 0)} temuan`">
        <Kolom :label="sebab.map((s: any) => s.label.length > 18 ? s.label.slice(0, 17) + '…' : s.label)" :deret="[{ nama: 'Temuan', nilai: sebab.map((s: any) => s.jumlah), warna: '#DC2626' }]" satuan="temuan" :tinggi="230" miring />
        <template #tabel>
          <table><thead><tr><th>#</th><th>Penyebab</th><th>Jumlah</th></tr></thead>
            <tbody><tr v-for="(s, i) in sebab" :key="s.label"><td>{{ Number(i) + 1 }}</td><td>{{ s.label }}</td><td class="num">{{ s.jumlah }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
    </section>
  </div>
</template>
