<script setup lang="ts">
/*
  Kewajiban per PPKH: corong luas (kewajiban → ditetapkan → ditanam →
  BAST), batas waktu ½N, status reklamasi hutan, dan sanksi. Pendaftaran
  PPKH baru menghitung sendiri kewajiban (luas PAK × rasio) dan batas
  penyelesaiannya — yang mengisi cukup menyalin SK.
*/
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n } from '../../energi';
import { FASE, WAKTU, sisa, tanggal } from '../../rdas';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const tambah = ref(false);
const form = useForm({ nomor_sk: '', tanggal_sk: '', jangka_tahun: 10, tujuan: 'komersial', kegiatan: 'Operasi produksi pertambangan batubara',
  provinsi: 'Kalimantan Timur', kabupaten: '', bpdas: 'BPDAS Mahakam Berau', luas_ppkh_ha: null as number | null, nomor_pak: '', luas_pak_ha: null as number | null, rasio: 1, catatan: '' });
const batas = computed(() => {
  if (!form.tanggal_sk || !form.jangka_tahun) return null;
  const d = new Date(form.tanggal_sk + 'T00:00:00');
  const bulan = Math.round(form.jangka_tahun * 6) + (form.jangka_tahun > 5 ? 0 : 12);
  d.setMonth(d.getMonth() + bulan);
  return d.toISOString().slice(0, 10);
});
function simpan() { form.post('/rehab-das/kewajiban'); }
const rurh: Record<string, string> = { belum: 'pr-abu', telaah: 'pr-kuning', sidang_1: 'pr-kuning', sidang_2: 'pr-biru', disetujui: 'pr-hijau' };
const predikat: Record<string, string> = { baik: 'pr-hijau', sedang: 'pr-kuning', buruk: 'pr-merah' };
</script>

<template>
  <Head title="Kewajiban PPKH" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <button class="eq-btn-utama inline-flex items-center gap-2" @click="tambah = !tambah"><Ikon nama="file-text" :ukuran="16" />{{ tambah ? 'Tutup isian' : 'Daftarkan PPKH' }}</button>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Kartu v-if="tambah" judul="Daftarkan PPKH" ket="Kewajiban = luas PAK × rasio (minimal 1 : 1). Batas penyelesaian ½N untuk PPKH > 5 tahun, ½N + 1 tahun untuk ≤ 5 tahun." ikon="file-text" warna="hijau">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="simpan">
        <label class="kw-l md:col-span-2">Nomor SK PPKH<input v-model="form.nomor_sk" required maxlength="150" placeholder="SK.123/MENLHK/SETJEN/PLA.0/1/2022" class="kw-i"></label>
        <label class="kw-l">Tanggal SK<input v-model="form.tanggal_sk" type="date" required class="kw-i"></label>
        <label class="kw-l">Jangka waktu (tahun)<input v-model.number="form.jangka_tahun" type="number" min="1" max="100" required class="kw-i num"></label>
        <label class="kw-l md:col-span-2">Kegiatan<input v-model="form.kegiatan" required maxlength="150" class="kw-i"></label>
        <label class="kw-l">Tujuan<select v-model="form.tujuan" class="kw-i"><option value="komersial">Komersial</option><option value="non_komersial">Non-komersial</option></select></label>
        <label class="kw-l">BPDAS<input v-model="form.bpdas" list="kw-bpdas" required maxlength="100" class="kw-i"><datalist id="kw-bpdas"><option v-for="b in pil.bpdas" :key="b" :value="b" /></datalist></label>
        <label class="kw-l">Provinsi<input v-model="form.provinsi" required maxlength="60" class="kw-i"></label>
        <label class="kw-l">Kabupaten<input v-model="form.kabupaten" maxlength="80" class="kw-i"></label>
        <label class="kw-l">Luas PPKH (ha)<input v-model.number="form.luas_ppkh_ha" type="number" step="0.01" min="0.01" required class="kw-i num"></label>
        <label class="kw-l">Luas PAK (ha)<input v-model.number="form.luas_pak_ha" type="number" step="0.01" min="0.01" required class="kw-i num"></label>
        <label class="kw-l md:col-span-2">Nomor Penetapan Areal Kerja<input v-model="form.nomor_pak" maxlength="150" class="kw-i"></label>
        <label class="kw-l">Rasio kewajiban<input v-model.number="form.rasio" type="number" step="0.1" min="1" max="10" class="kw-i num"></label>
        <div class="kw-hasil md:col-span-1"><span>Kewajiban</span><b class="num">{{ n((form.luas_pak_ha ?? 0) * (form.rasio || 1), 2) }} ha</b><small>batas {{ tanggal(batas) }}</small></div>
        <p v-if="Object.keys(form.errors).length" class="md:col-span-4 text-[12px] kw-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="md:col-span-4"><button class="eq-btn-utama" :disabled="form.processing">Simpan PPKH</button></div>
      </form>
    </Kartu>

    <section class="grid gap-4 xl:grid-cols-2">
      <Link v-for="p in props.ppkh" :key="p.id" :href="`/rehab-das/kewajiban/${p.id}`" class="kw-kartu">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] font-bold text-stone-500">{{ p.kode }} · {{ p.bpdas }}</p>
            <p class="text-[14px] font-extrabold text-cam-ink leading-snug">{{ p.nomor_sk }}</p>
            <p class="text-[11.5px] text-stone-500 mt-0.5">{{ p.kegiatan }} · {{ p.kabupaten }}, {{ p.provinsi }}</p>
          </div>
          <span class="pr-pil shrink-0" :class="WAKTU[p.waktu]?.kelas">{{ WAKTU[p.waktu]?.pendek }}</span>
        </div>
        <div class="grid grid-cols-4 gap-2 mt-3">
          <div class="kw-angka"><span>Kewajiban</span><b class="num">{{ n(p.kewajiban, 0) }}</b><small>ha</small></div>
          <div class="kw-angka"><span>Ditetapkan</span><b class="num">{{ n(p.ditetapkan_pct, 0) }}%</b><small>{{ n(p.ditetapkan, 0) }} ha</small></div>
          <div class="kw-angka"><span>Ditanam</span><b class="num">{{ n(p.ditanam_pct, 0) }}%</b><small>{{ n(p.ditanam, 0) }} ha</small></div>
          <div class="kw-angka kw-bast"><span>BAST</span><b class="num">{{ n(p.bast_pct, 0) }}%</b><small>{{ n(p.bast, 0) }} ha</small></div>
        </div>
        <div class="kw-fase" :title="'Luas menurut fase'">
          <i v-for="(l, k) in p.fase" v-show="l > 0" :key="k" :style="{ width: (l / Math.max(1, p.kewajiban) * 100) + '%', background: FASE[k]?.warna }" :title="`${FASE[k]?.pendek}: ${n(l, 1)} ha`" />
        </div>
        <div class="flex flex-wrap gap-1.5 mt-3 text-[11px]">
          <span class="pr-pil pr-abu">Batas {{ tanggal(p.batas_selesai) }} · {{ sisa(p.sisa_hari) }}</span>
          <span class="pr-pil" :class="rurh[p.rurh_status]">Reklamasi: {{ pil.rurh?.find((x: any) => x.nilai === p.rurh_status)?.label }}</span>
          <span v-if="p.predikat" class="pr-pil" :class="predikat[p.predikat]">Nilai reklamasi {{ n(p.nilai_reklamasi, 0) }}</span>
          <span v-if="p.sanksi_tahap" class="pr-pil pr-merah">Sanksi tahap {{ p.sanksi_tahap }}</span>
          <span class="pr-pil pr-abu">{{ p.blok }} lokasi</span>
        </div>
      </Link>
      <p v-if="!(props.ppkh ?? []).length" class="text-[12.5px] text-stone-400">Belum ada PPKH terdaftar.</p>
    </section>
  </div>
</template>

<style scoped>
.kw-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.kw-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.kw-hasil { border-radius: 12px; background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; display: grid; }
.kw-hasil span { font-size: 10.5px; font-weight: 700; color: #166534; } .kw-hasil b { font-size: 17px; font-weight: 900; color: #14532D; } .kw-hasil small { font-size: 10.5px; color: #4D7C0F; }
.kw-galat { color: #B91C1C; }
.kw-kartu { display: block; border-radius: 18px; background: #fff; border: 1px solid #E7E5E4; padding: 16px 18px; box-shadow: 0 8px 22px -18px rgba(15, 23, 32, .5); transition: border-color .15s; }
.kw-kartu:hover { border-color: #86EFAC; }
.kw-angka { border-radius: 12px; background: #F8FAF5; padding: 7px 10px; display: grid; }
.kw-angka span { font-size: 10px; font-weight: 700; color: #78716C; } .kw-angka b { font-size: 17px; font-weight: 900; color: #1C1917; } .kw-angka small { font-size: 10px; color: #A8A29E; }
.kw-bast { background: #DCFCE7; } .kw-bast b { color: #14532D; }
.kw-fase { display: flex; height: 9px; border-radius: 999px; background: #F0EFED; overflow: hidden; margin-top: 12px; }
.kw-fase i { display: block; height: 100%; }
:root[data-tema="gelap"] .kw-l { color: #93A1A8; }
:root[data-tema="gelap"] .kw-hasil { background: #12271D; border-color: #1F4D33; }
:root[data-tema="gelap"] .kw-hasil b, :root[data-tema="gelap"] .kw-hasil span, :root[data-tema="gelap"] .kw-hasil small { color: #8FE3BE; }
:root[data-tema="gelap"] .kw-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .kw-kartu { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .kw-angka { background: #20272B; }
:root[data-tema="gelap"] .kw-angka b { color: #E8EDEF; }
:root[data-tema="gelap"] .kw-bast { background: #12271D; } :root[data-tema="gelap"] .kw-bast b { color: #8FE3BE; }
:root[data-tema="gelap"] .kw-fase { background: #2E373C; }
</style>
