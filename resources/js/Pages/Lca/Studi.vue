<script setup lang="ts">
/*
  Daftar studi LCA dan pembuatan studi baru (Goal & Scope, ISO 14044
  klausul 4.2). Kartu tiap studi memperlihatkan jejak per unit
  fungsional, mutu data, dan statusnya; satu kode dapat punya beberapa
  versi — yang terverifikasi dikunci, revisi dibuat sebagai versi baru.
*/
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n } from '../../energi';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const buka = ref(!(props.studi ?? []).length);
const tahun = new Date().getFullYear();
const form = useForm<Record<string, any>>({
  judul: '', produk: '', sku: '', fasilitas: '', wilayah: 'ID-KAL', fu_nilai: 1, fu_label: 'ton produk', aliran_ref_kg: 1000,
  batas_sistem: 'cradle_to_gate', metode: 'IPCC_2021_GWP100', alokasi: 'massa', cutoff_pct: 1,
  periode_mulai: `${tahun - 1}-01-01`, periode_selesai: `${tahun - 1}-12-31`, volume_tahunan: 1000000,
  baseline_gwp: null, target_gwp: null, tujuan: '', audiens: '',
});
const KELAS: Record<string, string> = { draf: 'pr-abu', tinjau: 'pr-kuning', terverifikasi: 'pr-hijau', terbit: 'pr-biru', arsip: 'pr-abu' };
const studi = computed(() => (props.studi ?? []).map((s: any, urut: number) => ({ ...s, urut })));
const WILAYAH = [['ID-KAL', 'Kalimantan'], ['ID-SUL', 'Sulawesi'], ['ID-SUM', 'Sumatera'], ['ID-JW', 'Jawa-Bali'], ['ID-MA', 'Maluku & Papua']];
const desimal = (v: number) => (Math.abs(v) >= 100 ? 0 : Math.abs(v) >= 10 ? 1 : 2);
</script>

<template>
  <Head title="Studi LCA" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex gap-2">
        <button type="button" class="eq-btn-utama inline-flex items-center gap-2" @click="buka = !buka"><Ikon nama="flask-conical" :ukuran="16" />{{ buka ? 'Tutup formulir' : 'Studi baru' }}</button>
        <Link href="/lca/faktor" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="database" :ukuran="16" />Faktor dampak</Link>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Kartu v-if="buka" judul="Goal & scope studi baru" :ket="`Kode otomatis ${props.kodeBerikut} · ISO 14044 klausul 4.2`" ikon="target" warna="hijau">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="form.post('/lca/studi')">
        <label class="st-l md:col-span-2">Judul studi<input v-model="form.judul" required maxlength="180" placeholder="mis. Jejak karbon batubara GAR 4.200 FOB tongkang" class="st-i"></label>
        <label class="st-l">Produk<input v-model="form.produk" required maxlength="150" placeholder="Batubara GAR 4.200" class="st-i"></label>
        <label class="st-l">SKU / grade<input v-model="form.sku" maxlength="80" class="st-i"></label>
        <label class="st-l md:col-span-2">Fasilitas / site<input v-model="form.fasilitas" required maxlength="150" placeholder="Pit Utara + jetty Sungai Barito" class="st-i"></label>
        <label class="st-l">Wilayah (faktor grid)
          <select v-model="form.wilayah" class="st-i"><option v-for="w in WILAYAH" :key="w[0]" :value="w[0]">{{ w[1] }}</option></select>
        </label>
        <label class="st-l">Volume tahunan (unit fungsional)<input v-model.number="form.volume_tahunan" type="number" min="0" step="any" required class="st-i num"></label>
        <label class="st-l">Unit fungsional — nilai<input v-model.number="form.fu_nilai" type="number" step="any" min="0.000001" required class="st-i num"></label>
        <label class="st-l">Unit fungsional — satuan<input v-model="form.fu_label" required maxlength="100" placeholder="ton batubara" class="st-i"></label>
        <label class="st-l">Aliran referensi (kg)<input v-model.number="form.aliran_ref_kg" type="number" step="any" min="0.000001" required class="st-i num"></label>
        <label class="st-l">Batas sistem
          <select v-model="form.batas_sistem" class="st-i"><option v-for="b in pil.batas" :key="b.nilai" :value="b.nilai">{{ b.label }}</option></select>
        </label>
        <label class="st-l">Metode LCIA
          <select v-model="form.metode" class="st-i"><option v-for="b in pil.metode" :key="b.nilai" :value="b.nilai">{{ b.label }}</option></select>
        </label>
        <label class="st-l">Alokasi
          <select v-model="form.alokasi" class="st-i"><option v-for="b in pil.alokasi" :key="b.nilai" :value="b.nilai">{{ b.label }}</option></select>
        </label>
        <label class="st-l">Cut-off (%)<input v-model.number="form.cutoff_pct" type="number" min="0" max="10" step="0.1" class="st-i num"></label>
        <label class="st-l">Periode mulai<input v-model="form.periode_mulai" type="date" required class="st-i"></label>
        <label class="st-l">Periode selesai<input v-model="form.periode_selesai" type="date" required class="st-i"></label>
        <label class="st-l">Baseline (kg CO₂e/FU)<input v-model.number="form.baseline_gwp" type="number" step="any" class="st-i num"></label>
        <label class="st-l">Target (kg CO₂e/FU)<input v-model.number="form.target_gwp" type="number" step="any" class="st-i num"></label>
        <label class="st-l md:col-span-3">Tujuan studi<textarea v-model="form.tujuan" rows="2" maxlength="3000" placeholder="mis. Menyiapkan deklarasi jejak karbon produk untuk pembeli dan persiapan EU CBAM; mengidentifikasi hotspot untuk program dekarbonisasi." class="st-i" /></label>
        <label class="st-l">Audiens<input v-model="form.audiens" maxlength="150" placeholder="Pembeli, direksi, PROPER" class="st-i"></label>
        <p v-if="Object.keys(form.errors).length" class="md:col-span-4 text-[12px] st-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="md:col-span-4"><button class="eq-btn-utama" :disabled="form.processing">Buat studi</button></div>
      </form>
    </Kartu>

    <section class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <Link v-for="s in studi" :key="s.id" :href="`/lca/studi/${s.id}`" class="st-kartu">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] font-bold text-stone-400 num">{{ s.kode }} · v{{ s.versi }}</p>
            <p class="text-[14px] font-extrabold text-cam-ink leading-snug">{{ s.produk }}</p>
            <p class="text-[11.5px] text-stone-500 truncate">{{ s.judul }}</p>
          </div>
          <span class="pr-pil" :class="KELAS[s.status]">{{ s.status_label }}</span>
        </div>
        <div class="st-angka">
          <div><p class="num">{{ n(s.gwp, desimal(s.gwp)) }}</p><span>kg CO₂e / {{ s.fu_label }}</span></div>
          <div><p class="num">{{ n(s.dqr, 2) }}</p><span>DQR · {{ s.mutu }}</span></div>
          <div><p class="num">{{ n(s.ton_tahun, 0) }}</p><span>t CO₂e / tahun</span></div>
        </div>
        <p class="text-[11px] text-stone-400 mt-2">{{ s.batas_label }} · {{ s.periode }} · {{ s.baris }} baris inventori</p>
      </Link>
      <p v-if="!studi.length" class="md:col-span-2 text-center text-[12.5px] text-stone-400 py-10">Belum ada studi. Buat yang pertama di atas.</p>
    </section>
  </div>
</template>

<style scoped>
.st-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.st-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.st-galat { color: #B91C1C; }
.st-kartu { display: block; padding: 16px 18px; border-radius: 20px; background: linear-gradient(180deg, #F0FDF4 0%, #FFFFFF 46%); border: 1px solid #D1FAE5;
  box-shadow: 0 14px 30px -24px rgba(21, 128, 61, .6); transition: transform .12s, box-shadow .12s; }
.st-kartu:hover { transform: translateY(-2px); box-shadow: 0 20px 34px -22px rgba(21, 128, 61, .65); }
.st-angka { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
.st-angka div { padding: 9px 10px; border-radius: 12px; background: #fff; border: 1px solid #E7E5E4; }
.st-angka p { font-size: 18px; font-weight: 900; color: #14532D; letter-spacing: -.02em; line-height: 1.1; }
.st-angka span { font-size: 10px; color: #78716C; font-weight: 600; }
:root[data-tema="gelap"] .st-kartu { background: linear-gradient(180deg, #13261E 0%, #1B2124 46%); border-color: #1F3F31; }
:root[data-tema="gelap"] .st-angka div { background: #20272B; border-color: #2E373C; }
:root[data-tema="gelap"] .st-angka p { color: #8FE3BE; }
:root[data-tema="gelap"] .st-galat { color: #F5A9A9; }
</style>
