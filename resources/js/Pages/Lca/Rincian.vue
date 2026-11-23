<script setup lang="ts">
/*
  Rincian studi LCA — empat fase ISO 14040/14044 dalam empat tab:
  Ringkasan dampak (LCIA), Inventori (LCI lima tahap + pedigree DQR),
  Skenario (interpretasi: lima tuas dekarbonisasi), Verifikasi (tinjauan
  kritis, alur status, versi, jejak audit).

  Pratinjau baris inventori dan simulasi skenario dihitung SERVER —
  mesin yang sama dengan angka tersimpan — dipanggil sesudah ketikan
  atau geseran berhenti sejenak.
*/
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import AliranTahap from '../../Components/Lca/AliranTahap.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Kolom from '../../Grafik/Kolom.vue';
import { n, rp, json } from '../../energi';
import { waktu } from '../../perancah';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';

const { dialog, tanya, batal, lanjut } = useDialog();

const props = propHalaman();
const s = computed(() => props.studi ?? {});
const pil = computed(() => props.pilihan ?? {});
const TAHAP = computed(() => (pil.value.tahap ?? []) as any[]);
const W: Record<string, string> = Object.fromEntries((props.pilihan?.tahap ?? []).map((t: any) => [t.nilai, t.warna]));
const P: Record<string, string> = Object.fromEntries((props.pilihan?.tahap ?? []).map((t: any) => [t.nilai, t.pendek]));
const tab = ref(location.hash === '#skenario' ? 'skenario' : location.hash === '#inventori' ? 'inventori' : location.hash === '#verifikasi' ? 'verifikasi' : 'ringkasan');
watch(tab, t => history.replaceState(history.state, '', `#${t}`));
const desimal = (v: number) => (Math.abs(v) >= 100 ? 0 : Math.abs(v) >= 10 ? 1 : Math.abs(v) >= 0.1 ? 2 : 4);

/* ── ringkasan ── */
const arah = computed(() => {
  if (!s.value.baseline) return null;
  const p = ((s.value.gwp - s.value.baseline) / s.value.baseline) * 100;
  return { teks: `${p < 0 ? '−' : '+'}${n(Math.abs(p), 1)}% vs baseline`, baik: p <= 0 };
});
const stat = computed(() => [
  { label: 'GWP100', nilai: n(s.value.gwp, desimal(s.value.gwp)), satuan: 'kg CO₂e', ikon: 'cloud', arah: arah.value, catatan: `per ${s.value.fu}` },
  { label: 'Energi kumulatif', nilai: n(s.value.ced, desimal(s.value.ced)), satuan: 'MJ', ikon: 'zap', catatan: `per ${s.value.fu}` },
  { label: 'Emisi tahunan produk', nilai: n(s.value.ton_tahun, 0), satuan: 't CO₂e', ikon: 'factory', catatan: `${n(s.value.volume, 0)} ${s.value.fu_label}/tahun` },
  { label: 'Kualitas data', nilai: n(s.value.dqr, 2), satuan: 'DQR', ikon: 'badge-check', catatan: s.value.mutu },
]);
const hasil = computed(() => (props.hasil ?? []) as any[]);
const DAMPAK = computed(() => (pil.value.dampak ?? []) as any[]);
const perTahap = computed(() => TAHAP.value.map((t: any) => {
  const g = hasil.value.find(h => h.kategori === 'gwp' && h.tahap === t.nilai);
  return { tahap: t.nilai, pendek: t.pendek, warna: t.warna, gwp: g?.nilai ?? 0, porsi: g?.porsi ?? 0 };
}));
const matriks = computed(() => DAMPAK.value.map((d: any) => ({
  ...d, total: s.value.total?.[d.nilai] ?? 0,
  sel: TAHAP.value.map((t: any) => hasil.value.find(h => h.kategori === d.nilai && h.tahap === t.nilai) ?? { nilai: 0, porsi: 0 }),
})));
const hot = computed(() => (props.hotspot ?? []) as any[]);

/* ── inventori ── */
const tahapInv = ref<string>(TAHAP.value[0]?.nilai ?? 'ekstraksi');
const cari = ref('');
const faktor = computed(() => (props.faktor ?? []) as any[]);
const faktorTampil = computed(() => {
  const q = cari.value.trim().toLowerCase();
  return faktor.value.filter(f => !q || (f.nama + f.kode).toLowerCase().includes(q))
    .sort((a, b) => Number(b.tahap_bawaan === tahapInv.value) - Number(a.tahap_bawaan === tahapInv.value));
});
const PED = computed(() => (pil.value.pedigree ?? []) as any[]);
const item = useForm<Record<string, any>>({ faktor_id: null, tahap: tahapInv.value, nama: '', jumlah: null, satuan: '', jarak_km: null,
  daur_ulang_pct: 0, alokasi_pct: 100, p_reliabilitas: 2, p_kelengkapan: 2, p_temporal: 2, p_geografis: 2, p_teknologi: 2, sumber_data: 'primer', catatan: '' });
watch(tahapInv, t => (item.tahap = t));
const fPilih = computed(() => faktor.value.find(f => f.id === item.faktor_id) ?? null);
const satuanPilihan = computed(() => {
  const su = fPilih.value?.satuan; if (!su) return [];
  const grup = Object.values(pil.value.satuan ?? {}).find((g: any) => g.includes(su)) as string[] | undefined;
  return su === 'tkm' ? ['tkm', 'ton', 'kg'] : (grup ?? [su]);
});
watch(fPilih, f => { if (f) { item.satuan = f.satuan === 'tkm' ? 'ton' : f.satuan; if (!item.nama) item.nama = ''; } });
const pratinjau = ref<any>(null);
let jedaP: ReturnType<typeof setTimeout> | null = null;
watch(() => [item.faktor_id, item.jumlah, item.satuan, item.jarak_km, item.daur_ulang_pct, item.alokasi_pct, item.p_reliabilitas, item.p_kelengkapan, item.p_temporal, item.p_geografis, item.p_teknologi], () => {
  if (jedaP) clearTimeout(jedaP);
  if (!item.faktor_id || !item.jumlah) { pratinjau.value = null; return; }
  jedaP = setTimeout(async () => { try { pratinjau.value = await json(`/lca/studi/${s.value.id}/pratinjau`, { ...item.data() }); } catch { pratinjau.value = null; } }, 280);
});
function tambah() { item.post(`/lca/studi/${s.value.id}/inventori`, { preserveScroll: true, onSuccess: () => { item.reset('jumlah', 'nama', 'jarak_km', 'catatan'); pratinjau.value = null; } }); }
const inv = computed(() => ((props.inventori ?? []) as any[]).map((x, urut) => ({ ...x, urut })));
const invTahap = computed(() => inv.value.filter(x => x.tahap === tahapInv.value));
const totalTahap = (t: string) => inv.value.filter(x => x.tahap === t).reduce((a, x) => a + x.gwp, 0);
async function hapus(x: any) {
  if (await tanya({ judul: `Hapus baris "${x.nama}"?`, pesan: 'Dampak studi dihitung ulang dan penghapusan tercatat di jejak audit.', labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/lca/studi/${s.value.id}/inventori/${x.id}`, { preserveScroll: true });
  }
}
async function hapusSkenario(x: any) {
  if (await tanya({ judul: `Hapus skenario "${x.nama}"?`, labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/lca/studi/${s.value.id}/skenario/${x.id}`, { preserveScroll: true });
  }
}
function impor() { router.post(`/lca/studi/${s.value.id}/impor-energi`, {}, { preserveScroll: true }); }

/* ── skenario ── */
const tuas = reactive({ ebt: 30, bbm: 10, logistik: 10, daur_ulang: 10, limbah: 30 });
const TUAS = [
  { k: 'ebt', label: 'Listrik diganti PLTS', maks: 100, ikon: 'sun', ket: 'Porsi kWh grid/captive yang dipasok PLTS' },
  { k: 'bbm', label: 'Efisiensi BBM alat & kendaraan', maks: 30, ikon: 'fuel', ket: 'Idle, payload, rute, B-blend lebih tinggi' },
  { k: 'logistik', label: 'Efisiensi angkutan & pengapalan', maks: 40, ikon: 'ship', ket: 'Muatan penuh tongkang, kapal lebih besar' },
  { k: 'daur_ulang', label: 'Substitusi material & peledak', maks: 60, ikon: 'recycle', ket: 'Emulsi rendah karbon, ban vulkanisir, baja daur ulang' },
  { k: 'limbah', label: 'Limbah dialihkan dari TPA', maks: 90, ikon: 'leaf', ket: 'Pemilahan, kompos, pemanfaatan limbah B3' },
] as const;
const sim = ref<any>(null);
let jedaS: ReturnType<typeof setTimeout> | null = null;
async function simulasi() { try { sim.value = await json(`/lca/studi/${s.value.id}/simulasi`, { ...tuas }); } catch { /* biarkan */ } }
watch(tuas, () => { if (jedaS) clearTimeout(jedaS); jedaS = setTimeout(simulasi, 220); });
onMounted(simulasi);
const skForm = useForm({ nama: '', deskripsi: '' });
function simpanSk() { skForm.transform(d => ({ ...d, ...tuas })).post(`/lca/studi/${s.value.id}/skenario`, { preserveScroll: true, onSuccess: () => skForm.reset() }); }
const skenario = computed(() => ((props.skenario ?? []) as any[]).map((x, urut) => ({ ...x, urut })));
const maksBanding = computed(() => Math.max(1e-9, ...((sim.value?.tahap ?? []) as any[]).map((t: any) => Math.abs(t.baseline))));

/* ── verifikasi ── */
const iso = computed(() => ((props.iso ?? []) as any[]).map((x, urut) => ({ ...x, urut })));
const catatan = ref('');
function status(aksi: string) { router.post(`/lca/studi/${s.value.id}/status`, { aksi, catatan: catatan.value }, { preserveScroll: true, onSuccess: () => (catatan.value = '') }); }
function versiBaru() { router.post(`/lca/studi/${s.value.id}/versi`); }
const scope = useForm<Record<string, any>>({
  judul: s.value.judul, produk: s.value.produk, sku: s.value.sku, fasilitas: s.value.fasilitas, wilayah: s.value.wilayah,
  fu_nilai: s.value.fu_nilai, fu_label: s.value.fu_label, aliran_ref_kg: s.value.aliran_ref_kg, batas_sistem: s.value.batas,
  metode: s.value.metode, alokasi: s.value.alokasi, cutoff_pct: s.value.cutoff_pct, periode_mulai: s.value.periode_mulai,
  periode_selesai: s.value.periode_selesai, volume_tahunan: s.value.volume, baseline_gwp: s.value.baseline, target_gwp: s.value.target,
  tujuan: s.value.tujuan, audiens: s.value.audiens,
});
const KELAS: Record<string, string> = { draf: 'pr-abu', tinjau: 'pr-kuning', terverifikasi: 'pr-hijau', terbit: 'pr-biru', arsip: 'pr-abu' };
const isoKelas = (st: string) => (st === 'lulus' ? 'pr-hijau' : st === 'peringatan' ? 'pr-kuning' : 'pr-merah');
const jejak = computed(() => ((props.jejak ?? []) as any[]).map((x, urut) => ({ ...x, urut })));
</script>

<template>
  <Head :title="`${s.kode} v${s.versi} · LCA`" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-2">
      <Link href="/lca/studi" class="text-[12.5px] font-semibold text-stone-500 hover:underline">← Studi LCA</Link>
      <div class="flex flex-wrap gap-2">
        <a :href="`/lca/studi/${s.id}/ekspor/csv`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="file-spreadsheet" :ukuran="16" />Ekspor CSV</a>
        <button v-if="s.terkunci" type="button" class="eq-btn-utama inline-flex items-center gap-2" @click="versiBaru"><Ikon nama="git-compare-arrows" :ukuran="16" />Buat versi baru</button>
      </div>
    </section>

    <Panggung tema="lca" :judul="`${s.produk}`" :sub="`${s.kode} v${s.versi} · ${s.judul} · per ${s.fu} · ${s.batas_label}`"
              :stat="stat" :lencana="[s.status_label, s.periode, ...(s.terkunci ? ['Dikunci'] : [])]" />

    <nav class="flex flex-wrap gap-1.5 rc-tab" role="tablist">
      <button v-for="t in [['ringkasan', 'Ringkasan dampak', 'chart-pie'], ['inventori', 'Inventori 5 tahap', 'list-checks'], ['skenario', 'Skenario', 'sliders-horizontal'], ['verifikasi', 'Verifikasi ISO', 'shield-check']]"
              :key="t[0]" type="button" role="tab" :aria-selected="tab === t[0]" :class="tab === t[0] && 'rc-aktif'" @click="tab = t[0]">
        <Ikon :nama="t[2]" :ukuran="15" />{{ t[1] }}
      </button>
    </nav>

    <!-- ═════════ RINGKASAN ═════════ -->
    <template v-if="tab === 'ringkasan'">
      <Kartu judul="Aliran emisi per tahap" :ket="`kg CO₂e per ${s.fu}`" ikon="route" warna="hijau">
        <AliranTahap :tahap="perTahap" />
      </Kartu>
      <Kartu judul="Matriks dampak — kategori × tahap" ket="Nilai per unit fungsional; warna sel menunjukkan porsi tahap pada kategorinya" ikon="layers" warna="toska" rapat>
        <div class="overflow-x-auto">
          <table class="w-full text-[12px]">
            <thead class="text-left text-[11px] text-stone-500 bg-stone-50"><tr><th class="px-4 py-2.5">Kategori</th>
              <th v-for="t in TAHAP" :key="t.nilai" class="px-3 py-2.5 text-right whitespace-nowrap"><span class="inline-block w-2 h-2 rounded-full mr-1" :style="{ background: t.warna }" />{{ t.pendek }}</th>
              <th class="px-4 py-2.5 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
              <tr v-for="d in matriks" :key="d.nilai">
                <td class="px-4 py-2.5"><b class="text-cam-ink">{{ d.kode }}</b> <span class="text-stone-500">{{ d.nama }}</span><br><span class="text-[10.5px] text-stone-400">{{ d.satuan }} · {{ d.metode }}</span></td>
                <td v-for="(c, ci) in d.sel" :key="ci" class="px-3 py-2.5 text-right num"
                    :style="{ background: c.porsi > 0 ? `color-mix(in srgb, ${TAHAP[Number(ci)].warna} ${Math.min(55, Math.round(c.porsi * 0.55))}%, transparent)` : undefined }">
                  {{ n(c.nilai, d.nilai === 'odp' ? 8 : desimal(c.nilai)) }}<br><span class="text-[10px] text-stone-500">{{ n(c.porsi, 0) }}%</span></td>
                <td class="px-4 py-2.5 text-right font-extrabold num">{{ n(d.total, d.nilai === 'odp' ? 8 : desimal(d.total)) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </Kartu>
      <KartuGrafik judul="Hotspot GWP (Pareto)" catatan="Kolom = kg CO₂e per FU; garis = porsi kumulatif" :angka="`${hot.length} kontributor teratas`">
        <Kolom :label="hot.map((h: any) => h.nama.length > 22 ? h.nama.slice(0, 21) + '…' : h.nama)" :deret="[{ nama: 'kg CO₂e', nilai: hot.map((h: any) => h.gwp), warna: '#15803D' }]"
               :garis="{ nama: 'Kumulatif', nilai: hot.map((h: any) => h.kumulatif), warna: '#D97706' }" :tinggi="240" miring />
      </KartuGrafik>
    </template>

    <!-- ═════════ INVENTORI ═════════ -->
    <template v-if="tab === 'inventori'">
      <div class="rc-stepper">
        <button v-for="(t, i) in TAHAP" :key="t.nilai" type="button" :class="tahapInv === t.nilai && 'rc-step-aktif'" :style="{ '--w': t.warna }" @click="tahapInv = t.nilai">
          <span class="rc-nomor">{{ i + 1 }}</span>
          <span class="text-left"><b>{{ t.pendek }}</b><small class="num">{{ n(totalTahap(t.nilai), desimal(totalTahap(t.nilai))) }} kg CO₂e · {{ inv.filter(x => x.tahap === t.nilai).length }} baris</small></span>
        </button>
      </div>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] items-start">
        <Kartu :judul="`Tambah baris — ${P[tahapInv]}`" :ket="s.terkunci ? 'Studi dikunci; buat versi baru untuk mengubah inventori' : 'Pilih faktor, isi jumlah per unit fungsional, nilai mutu datanya'" ikon="flask-conical" :warna="'hijau'">
          <div v-if="!s.terkunci" class="space-y-3">
            <div class="grid gap-3 md:grid-cols-2">
              <label class="rc-l md:col-span-2">Cari faktor<input v-model="cari" type="search" placeholder="solar, listrik, ANFO, tongkang…" class="rc-i"></label>
              <label class="rc-l md:col-span-2">Faktor dampak
                <select v-model="item.faktor_id" class="rc-i" required>
                  <option :value="null" disabled>— pilih faktor —</option>
                  <option v-for="f in faktorTampil" :key="f.id" :value="f.id">{{ f.nama }} · {{ n(f.gwp, 3) }} kg CO₂e/{{ f.satuan }}{{ f.perlu_cocok ? ' · belum dicocokkan' : '' }}</option>
                </select>
              </label>
              <label class="rc-l md:col-span-2">Nama baris (opsional)<input v-model="item.nama" maxlength="180" :placeholder="fPilih?.nama ?? ''" class="rc-i"></label>
              <label class="rc-l">Jumlah per {{ s.fu }}<input v-model.number="item.jumlah" type="number" step="any" min="0" class="rc-i num" required></label>
              <label class="rc-l">Satuan
                <select v-model="item.satuan" class="rc-i"><option v-for="u in satuanPilihan" :key="u" :value="u">{{ u }}</option></select>
              </label>
              <label v-if="fPilih?.satuan === 'tkm' && item.satuan !== 'tkm'" class="rc-l">Jarak tempuh (km)<input v-model.number="item.jarak_km" type="number" step="any" min="0" class="rc-i num"></label>
              <label class="rc-l">Konten daur ulang (%)<input v-model.number="item.daur_ulang_pct" type="number" min="0" max="100" class="rc-i num"></label>
              <label class="rc-l">Alokasi ke produk (%)<input v-model.number="item.alokasi_pct" type="number" min="0" max="100" class="rc-i num"></label>
            </div>
            <div>
              <p class="rc-l mb-1.5">Pedigree mutu data (1 terbaik – 5 terlemah)</p>
              <div class="space-y-1.5">
                <div v-for="p in PED" :key="p.kunci" class="flex flex-wrap items-center gap-2">
                  <span class="w-36 text-[11.5px] font-semibold text-stone-600">{{ p.label }}</span>
                  <div class="rc-ped">
                    <button v-for="v in 5" :key="v" type="button" :title="p.tingkat[v - 1]" :class="item[p.kunci] === v && 'rc-ped-aktif'" @click="item[p.kunci] = v">{{ v }}</button>
                  </div>
                  <span class="text-[10.5px] text-stone-400">{{ p.tingkat[item[p.kunci] - 1] }}</span>
                </div>
              </div>
            </div>
            <p v-if="Object.keys(item.errors).length" class="text-[12px] rc-galat">{{ Object.values(item.errors)[0] }}</p>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="eq-btn-utama" :disabled="item.processing || !item.faktor_id || !item.jumlah" @click="tambah">Tambah ke inventori</button>
              <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="impor"><Ikon nama="zap" :ukuran="15" />Tarik dari Energy Performance</button>
            </div>
          </div>
          <p v-else class="text-[12.5px] text-stone-500">Studi berstatus {{ s.status_label }}. Gunakan “Buat versi baru” untuk merevisi.</p>
        </Kartu>

        <aside class="rc-pratinjau xl:sticky xl:top-4">
          <p class="text-[12.5px] font-extrabold">Pratinjau dampak baris</p>
          <p class="text-[11px] opacity-80">{{ fPilih ? fPilih.nama : 'Pilih faktor dan isi jumlah' }}</p>
          <template v-if="pratinjau">
            <p class="rc-besar num">{{ n(pratinjau.gwp, desimal(pratinjau.gwp)) }}<small> kg CO₂e</small></p>
            <div class="grid grid-cols-2 gap-2 mt-2">
              <div class="rc-mini"><span>Energi</span><b class="num">{{ n(pratinjau.ced, desimal(pratinjau.ced)) }} MJ</b></div>
              <div class="rc-mini"><span>Air</span><b class="num">{{ n(pratinjau.wsf, 4) }} m³</b></div>
              <div class="rc-mini"><span>Asidifikasi</span><b class="num">{{ n(pratinjau.ap, 5) }} kg SO₂e</b></div>
              <div class="rc-mini"><span>Eutrofikasi</span><b class="num">{{ n(pratinjau.ep, 6) }} kg PO₄e</b></div>
            </div>
            <p class="text-[11.5px] mt-3">Kuantitas ternormalisasi <b class="num">{{ n(pratinjau.jumlah_normal, 4) }} {{ pratinjau.satuan_faktor }}</b></p>
            <p class="text-[11.5px]">DQR <b class="num">{{ n(pratinjau.dqr, 2) }}</b> · {{ pratinjau.mutu }} · ketidakpastian ±{{ n(pratinjau.ketidakpastian, 1) }}%</p>
            <p class="text-[11.5px]">Porsi bila ditambah: <b class="num">{{ n(pratinjau.gwp / Math.max(1e-9, s.gwp + pratinjau.gwp) * 100, 1) }}%</b> dari total</p>
          </template>
          <p v-else class="text-[12px] opacity-80 mt-6">Angka muncul begitu faktor dan jumlah terisi.</p>
        </aside>
      </section>

      <Kartu :judul="`Inventori tahap ${P[tahapInv]}`" :ket="`${invTahap.length} baris · ${n(totalTahap(tahapInv), desimal(totalTahap(tahapInv)))} kg CO₂e per ${s.fu}`" ikon="list-checks" warna="biru" rapat>
        <div class="overflow-x-auto">
          <table class="w-full text-[12px]">
            <thead class="text-left text-[11px] text-stone-500 bg-stone-50"><tr><th class="px-4 py-2.5">Baris</th><th class="px-3 text-right">Jumlah</th><th class="px-3 text-right">kg CO₂e</th><th class="px-3 text-right">MJ</th><th class="px-3 text-right">DQR</th><th class="px-3">Sumber</th><th class="px-3"></th></tr></thead>
            <tbody class="divide-y divide-stone-100">
              <tr v-for="x in invTahap" :key="x.id">
                <td class="px-4 py-2.5"><b class="text-cam-ink">{{ x.nama }}</b><br><span class="text-[10.5px] text-stone-400">{{ x.faktor?.kode }} · {{ n(x.faktor?.gwp, 3) }}/{{ x.faktor?.satuan }}<template v-if="x.faktor?.perlu_cocok"> · <span class="rc-warn">faktor belum dicocokkan</span></template></span>
                  <p v-if="x.catatan" class="text-[10.5px] text-stone-400">{{ x.catatan }}</p></td>
                <td class="px-3 py-2.5 text-right num whitespace-nowrap">{{ n(x.jumlah, desimal(x.jumlah)) }} {{ x.satuan }}<template v-if="x.jarak_km"><br><span class="text-[10.5px] text-stone-400">{{ n(x.jarak_km, 0) }} km</span></template></td>
                <td class="px-3 py-2.5 text-right num font-extrabold" :class="x.gwp < 0 && 'rc-kredit'">{{ n(x.gwp, desimal(x.gwp)) }}</td>
                <td class="px-3 py-2.5 text-right num">{{ n(x.ced, desimal(x.ced)) }}</td>
                <td class="px-3 py-2.5 text-right num">{{ n(x.dqr, 1) }}</td>
                <td class="px-3 py-2.5"><span class="pr-pil" :class="x.sumber_data === 'energi' ? 'pr-biru' : x.sumber_data === 'primer' ? 'pr-hijau' : 'pr-abu'">{{ x.sumber_data === 'energi' ? 'Energy Perf.' : x.sumber_data }}</span></td>
                <td class="px-3 py-2.5 text-right"><button v-if="!s.terkunci" type="button" class="eq-btn-mini" @click="hapus(x)">Hapus</button></td>
              </tr>
              <tr v-if="!invTahap.length"><td colspan="7" class="px-4 py-8 text-center text-stone-400">Belum ada baris pada tahap ini.</td></tr>
            </tbody>
          </table>
        </div>
      </Kartu>
    </template>

    <!-- ═════════ SKENARIO ═════════ -->
    <template v-if="tab === 'skenario'">
      <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] items-start">
        <Kartu judul="Tuas dekarbonisasi" ket="Geser — hasil dihitung ulang per baris inventori" ikon="sliders-horizontal" warna="hijau">
          <div class="space-y-4">
            <div v-for="t in TUAS" :key="t.k">
              <div class="flex items-center justify-between gap-3">
                <p class="text-[12.5px] font-bold text-cam-ink inline-flex items-center gap-2"><Ikon :nama="t.ikon" :ukuran="15" />{{ t.label }}</p>
                <p class="text-[13px] font-black num rc-hijau">{{ tuas[t.k] }}%</p>
              </div>
              <input v-model.number="tuas[t.k]" type="range" min="0" :max="t.maks" step="1" class="w-full accent-green-700">
              <p class="text-[10.5px] text-stone-400">{{ t.ket }} · maks {{ t.maks }}%<template v-if="sim"> · −{{ n(sim.tuas?.[t.k] ?? 0, desimal(sim.tuas?.[t.k] ?? 0)) }} kg CO₂e/FU</template></p>
            </div>
          </div>
        </Kartu>

        <div class="space-y-4">
          <section v-if="sim" class="rc-sim">
            <div class="flex flex-wrap items-end gap-4">
              <div><p class="text-[11px] font-bold opacity-80">Penurunan per unit fungsional</p><p class="text-[44px] font-black num leading-none">−{{ n(sim.reduksi_pct, 1) }}%</p></div>
              <p class="text-[12.5px] pb-1 opacity-90">{{ n(sim.baseline, desimal(sim.baseline)) }} → <b>{{ n(sim.proyeksi, desimal(sim.proyeksi)) }}</b> kg CO₂e per {{ s.fu }}</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-4">
              <div class="rc-kaca"><span>Dihindari / tahun</span><b class="num">{{ n(sim.hemat_t_tahun, 0) }} t CO₂e</b></div>
              <div class="rc-kaca"><span>Nilai pajak/pasar karbon</span><b class="num">{{ rp(sim.hemat_idr) }}</b></div>
              <div class="rc-kaca"><span>Potensi EU CBAM</span><b class="num">€{{ n(sim.hemat_eur, 0) }}</b></div>
            </div>
          </section>
          <Kartu v-if="sim" judul="Baseline vs skenario per tahap" ket="kg CO₂e per unit fungsional" ikon="chart-column" warna="toska">
            <ul class="space-y-3">
              <li v-for="t in sim.tahap" :key="t.tahap">
                <div class="flex justify-between text-[12px]"><b class="text-cam-ink">{{ P[t.tahap] }}</b>
                  <span class="num text-stone-500">{{ n(t.baseline, desimal(t.baseline)) }} → <b class="text-cam-ink">{{ n(t.proyeksi, desimal(t.proyeksi)) }}</b></span></div>
                <div class="mt-1 space-y-1">
                  <div class="h-2.5 rounded-full bg-stone-100 overflow-hidden"><i class="block h-full rounded-full opacity-40" :style="{ width: Math.abs(t.baseline) / maksBanding * 100 + '%', background: W[t.tahap] }" /></div>
                  <div class="h-2.5 rounded-full bg-stone-100 overflow-hidden"><i class="block h-full rounded-full" :style="{ width: Math.abs(t.proyeksi) / maksBanding * 100 + '%', background: W[t.tahap] }" /></div>
                </div>
              </li>
            </ul>
            <p class="text-[10.5px] text-stone-400 mt-3">Pita pudar = baseline · pita pekat = skenario</p>
          </Kartu>
          <Kartu judul="Simpan sebagai skenario" ikon="file-check-2" warna="ungu">
            <form class="grid gap-2 md:grid-cols-[1fr_auto]" @submit.prevent="simpanSk">
              <input v-model="skForm.nama" required maxlength="150" placeholder="mis. PLTS 30% + efisiensi BBM 10%" class="rc-i">
              <button class="eq-btn-utama" :disabled="skForm.processing">Simpan</button>
              <input v-model="skForm.deskripsi" maxlength="1000" placeholder="Keterangan (opsional)" class="rc-i md:col-span-2">
            </form>
          </Kartu>
        </div>
      </section>
      <Kartu judul="Skenario tersimpan" :ket="`${skenario.length} skenario`" ikon="history" warna="hijau" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="x in skenario" :key="x.id" class="px-5 py-3 flex flex-wrap items-center gap-3">
            <p class="text-[20px] font-black num rc-hijau w-20">−{{ n(x.reduksi_pct, 1) }}%</p>
            <div class="min-w-0 flex-1"><p class="text-[13px] font-bold text-cam-ink">{{ x.nama }}</p>
              <p class="text-[11px] text-stone-500">PLTS {{ n(x.tuas_ebt, 0) }}% · BBM {{ n(x.tuas_bbm, 0) }}% · logistik {{ n(x.tuas_logistik, 0) }}% · material {{ n(x.tuas_daur_ulang, 0) }}% · limbah {{ n(x.tuas_limbah, 0) }}%</p></div>
            <p class="text-[12px] text-stone-600 num">{{ n(x.hemat_t_tahun, 0) }} t/thn · {{ rp(x.hemat_idr) }}</p>
            <button type="button" class="eq-btn-mini" @click="hapusSkenario(x)">Hapus</button>
          </li>
          <li v-if="!skenario.length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Belum ada skenario tersimpan.</li>
        </ul>
      </Kartu>
    </template>

    <!-- ═════════ VERIFIKASI ═════════ -->
    <template v-if="tab === 'verifikasi'">
      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] items-start">
        <Kartu judul="Tinjauan kritis ISO 14044" :ket="props.siap ? 'Butir wajib lulus — studi dapat diajukan/diverifikasi' : 'Lengkapi butir wajib sebelum diajukan'" ikon="shield-check" :warna="props.siap ? 'hijau' : 'amber'">
          <ul class="space-y-2.5">
            <li v-for="b in iso" :key="b.kode" class="flex items-start gap-3 text-[12.5px]">
              <span class="pr-pil shrink-0" :class="isoKelas(b.status)">{{ b.status === 'lulus' ? 'Lulus' : b.status === 'peringatan' ? 'Perhatian' : 'Belum' }}</span>
              <div class="min-w-0"><p class="font-bold text-cam-ink">{{ b.butir }} <span v-if="b.wajib" class="text-[10px] text-stone-400 font-bold">WAJIB</span></p><p class="text-stone-500">{{ b.ket }}</p></div>
            </li>
          </ul>
        </Kartu>
        <Kartu judul="Alur status" :ket="s.pemverifikasi ? `Diverifikasi ${s.pemverifikasi} · ${waktu(s.diverifikasi_at)}` : 'Draf → tinjauan → terverifikasi → terbit'" ikon="workflow" warna="biru">
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span v-for="st in ['draf', 'tinjau', 'terverifikasi', 'terbit']" :key="st" class="pr-pil" :class="s.status === st ? KELAS[st] : 'pr-abu'" :style="{ opacity: s.status === st ? 1 : 0.5 }">{{ st === 'tinjau' ? 'Tinjauan' : st[0].toUpperCase() + st.slice(1) }}</span>
          </div>
          <p v-if="s.catatan_tinjau" class="text-[12px] rc-warn mb-3">Catatan tinjauan: {{ s.catatan_tinjau }}</p>
          <textarea v-model="catatan" rows="2" maxlength="2000" placeholder="Catatan (wajib saat mengembalikan)" class="rc-i mb-3" />
          <div class="flex flex-wrap gap-2">
            <button v-if="s.status === 'draf'" type="button" class="eq-btn-utama" :disabled="!props.siap" @click="status('ajukan')">Ajukan tinjauan</button>
            <template v-if="s.status === 'tinjau'">
              <button type="button" class="eq-btn-utama" :disabled="!props.siap || !props.boleh?.verifikasi" @click="status('verifikasi')">Verifikasi & kunci</button>
              <button type="button" class="eq-btn-lain" @click="status('kembalikan')">Kembalikan ke draf</button>
            </template>
            <button v-if="s.status === 'terverifikasi'" type="button" class="eq-btn-utama" @click="status('terbitkan')">Terbitkan</button>
            <button v-if="['draf', 'terverifikasi', 'terbit'].includes(s.status)" type="button" class="eq-btn-lain" @click="status('arsipkan')">Arsipkan</button>
          </div>
          <div class="mt-4">
            <p class="rc-l mb-1">Versi</p>
            <div class="flex flex-wrap gap-1.5">
              <Link v-for="v in props.versi" :key="v.id" :href="`/lca/studi/${v.id}`" class="pr-pil" :class="v.id === s.id ? 'pr-hijau' : 'pr-abu'">v{{ v.versi }} · {{ n(v.total_gwp, 1) }}</Link>
            </div>
          </div>
        </Kartu>
      </section>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] items-start">
        <Kartu judul="Goal & scope" :ket="s.terkunci ? 'Dikunci' : 'Perubahan tercatat di jejak audit'" ikon="target" warna="hijau">
          <form class="grid gap-3 md:grid-cols-3" @submit.prevent="scope.put(`/lca/studi/${s.id}`, { preserveScroll: true })">
            <label class="rc-l md:col-span-2">Judul<input v-model="scope.judul" :disabled="s.terkunci" class="rc-i"></label>
            <label class="rc-l">Produk<input v-model="scope.produk" :disabled="s.terkunci" class="rc-i"></label>
            <label class="rc-l">Unit fungsional<input v-model.number="scope.fu_nilai" type="number" step="any" :disabled="s.terkunci" class="rc-i num"></label>
            <label class="rc-l">Satuan FU<input v-model="scope.fu_label" :disabled="s.terkunci" class="rc-i"></label>
            <label class="rc-l">Volume tahunan<input v-model.number="scope.volume_tahunan" type="number" step="any" :disabled="s.terkunci" class="rc-i num"></label>
            <label class="rc-l">Batas sistem<select v-model="scope.batas_sistem" :disabled="s.terkunci" class="rc-i"><option v-for="b in pil.batas" :key="b.nilai" :value="b.nilai">{{ b.label }}</option></select></label>
            <label class="rc-l">Baseline kg CO₂e/FU<input v-model.number="scope.baseline_gwp" type="number" step="any" :disabled="s.terkunci" class="rc-i num"></label>
            <label class="rc-l">Target kg CO₂e/FU<input v-model.number="scope.target_gwp" type="number" step="any" :disabled="s.terkunci" class="rc-i num"></label>
            <label class="rc-l md:col-span-3">Tujuan<textarea v-model="scope.tujuan" rows="2" :disabled="s.terkunci" class="rc-i" /></label>
            <p v-if="Object.keys(scope.errors).length" class="md:col-span-3 text-[12px] rc-galat">{{ Object.values(scope.errors)[0] }}</p>
            <div v-if="!s.terkunci" class="md:col-span-3"><button class="eq-btn-utama" :disabled="scope.processing">Simpan goal & scope</button></div>
          </form>
        </Kartu>
        <Kartu judul="Jejak audit" :ket="`${jejak.length} kejadian terakhir`" ikon="history" warna="ungu" rapat>
          <ol class="divide-y divide-stone-100 max-h-[420px] overflow-y-auto">
            <li v-for="j in jejak" :key="j.urut" class="px-5 py-2.5">
              <p class="text-[12px] font-semibold text-cam-ink">{{ j.ringkas }}</p>
              <p class="text-[10.5px] text-stone-400">{{ waktu(j.pada) }} · {{ j.oleh }} · {{ j.aksi }}</p>
            </li>
          </ol>
        </Kartu>
      </section>
    </template>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.rc-tab button { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: 12px; font-size: 12.5px; font-weight: 700;
  background: #fff; border: 1px solid #E7E5E4; color: #57534E; }
.rc-tab .rc-aktif { background: #15803D; border-color: #15803D; color: #fff; box-shadow: 0 10px 20px -14px #15803D; }
.rc-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.rc-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.rc-galat { color: #B91C1C; }
.rc-warn { color: #B45309; }
.rc-kredit, .rc-hijau { color: #15803D; }
.rc-stepper { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px; }
.rc-stepper button { --w: #0F766E; display: flex; gap: 10px; align-items: center; padding: 10px 12px; border-radius: 14px; background: #fff; border: 1.5px solid #E7E5E4; }
.rc-stepper b { display: block; font-size: 12.5px; color: #1C1917; }
.rc-stepper small { display: block; font-size: 10.5px; color: #78716C; }
.rc-nomor { width: 28px; height: 28px; flex: none; border-radius: 999px; display: grid; place-items: center; font-size: 12px; font-weight: 900; color: #fff; background: var(--w); }
.rc-step-aktif { border-color: var(--w) !important; box-shadow: 0 0 0 3px color-mix(in srgb, var(--w) 22%, transparent); }
.rc-ped { display: inline-flex; gap: 4px; }
.rc-ped button { width: 30px; height: 30px; border-radius: 9px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 800; color: #57534E; }
.rc-ped .rc-ped-aktif { background: #15803D; border-color: #15803D; color: #fff; }
.rc-pratinjau { border-radius: 20px; padding: 18px; color: #fff; background: linear-gradient(140deg, #052E1C 0%, #0E4D33 45%, #15803D 100%); box-shadow: 0 20px 40px -26px #052E1C; }
.rc-besar { font-size: 34px; font-weight: 900; letter-spacing: -.02em; margin-top: 10px; }
.rc-besar small { font-size: 13px; font-weight: 700; opacity: .8; }
.rc-mini { padding: 8px 10px; border-radius: 12px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); display: grid; }
.rc-mini span { font-size: 10px; opacity: .8; font-weight: 700; }
.rc-mini b { font-size: 12.5px; }
.rc-sim { border-radius: 22px; padding: 20px 22px; color: #fff; background: linear-gradient(118deg, #052E1C 0%, #0E4D33 30%, #15803D 64%, #6BAF3A 100%); box-shadow: 0 20px 40px -26px #052E1C; }
.rc-kaca { padding: 10px 12px; border-radius: 14px; background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.22); display: grid; }
.rc-kaca span { font-size: 10.5px; font-weight: 700; opacity: .85; }
.rc-kaca b { font-size: 15px; font-weight: 900; }
:root[data-tema="gelap"] .rc-tab button, :root[data-tema="gelap"] .rc-stepper button, :root[data-tema="gelap"] .rc-ped button { background: #20272B; border-color: #2E373C; color: #C9D2D6; }
:root[data-tema="gelap"] .rc-stepper b { color: #E8EDEF; }
:root[data-tema="gelap"] .rc-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .rc-warn { color: #F6CF7A; }
:root[data-tema="gelap"] .rc-kredit, :root[data-tema="gelap"] .rc-hijau { color: #8FE3BE; }
</style>
