<script setup lang="ts">
/*
  Formulir riksa uji pesawat angkat / alat bantu angkat.

  Pemeriksa tidak memilih putusan. Ia mengisi yang diperiksanya — butir
  visual & fungsi, hasil NDT, dan ukuran (keausan, kawat putus,
  pemuluran, bukaan kait, deformasi) — dan putusannya tampil saat itu
  juga di panel kanan, lengkap dengan alasannya. Begitu disimpan dengan
  putusan TIDAK LAYAK, alatnya dikarantina.
*/
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import { n, json } from '../../energi';
import { kelayakan, riksa, tanggal } from '../../angkat';

const props = propHalaman();
const t = computed(() => props.target);
const pil = computed(() => props.pilihan ?? {});
const cari = ref('');
const pilihan = computed(() => {
  const q = cari.value.toLowerCase();
  const cocok = (x: any) => !q || `${x.kode} ${x.nama}`.toLowerCase().includes(q);
  return {
    alat: (props.register?.alat ?? []).filter(cocok),
    gear: (props.register?.gear ?? []).filter(cocok),
  };
});

const form = useForm<Record<string, any>>({
  objek: props.objek, id: t.value?.id ?? null, jenis: t.value?.status === 'kedaluwarsa' || !t.value?.uji_terakhir && !t.value?.suket_berlaku ? 'pertama' : 'berkala',
  pemeriksa: props.pemeriksa ?? '', skp_nomor: '', pjk3: '',
  periksa: Object.fromEntries((props.butir ?? []).map((b: any) => [b.kode, 'lulus'])),
  ukur: Object.fromEntries((props.ukur ?? []).map((u: any) => [u.kode, null])),
  ndt_metode: 'MPI', ndt_hasil: 'tanpa_indikasi', catatan: '',
});

const nilai = ref<any>(null);
let jeda: ReturnType<typeof setTimeout> | null = null;
async function pratinjau() {
  if (!props.kategori) return;
  try { nilai.value = await json('/pengangkatan/riksa-uji/pratinjau', { kategori_uji: props.kategori, periksa: form.periksa, ukur: form.ukur, ndt_metode: form.ndt_metode, ndt_hasil: form.ndt_hasil }); } catch { /* pratinjau saja */ }
}
watch(() => JSON.stringify([form.periksa, form.ukur, form.ndt_metode, form.ndt_hasil]), () => { if (jeda) clearTimeout(jeda); jeda = setTimeout(pratinjau, 250); });
onMounted(pratinjau);

function kirim() { form.post('/pengangkatan/riksa-uji'); }
const b = computed(() => props.beban);
</script>

<template>
  <Head title="Catat riksa uji" />

  <!-- belum ada sasaran: pilih dari register -->
  <Kartu v-if="!t" judul="Pilih yang akan diriksa uji" ket="Crane dan alat bantu dari register, termasuk yang dikarantina" ikon="scan-eye" warna="biru">
    <input v-model="cari" placeholder="Cari nomor seri atau nama" aria-label="Cari alat" class="fr-i mb-3">
    <p class="fr-sub">Pesawat angkat</p>
    <div class="grid gap-2 md:grid-cols-2 mb-4">
      <button v-for="a in pilihan.alat" :key="'a' + a.id" class="fr-pilih" @click="router.visit(`/pengangkatan/riksa-uji/baru?objek=alat&id=${a.id}`)">
        <span class="min-w-0 text-left"><b>{{ a.kode }}</b> · {{ a.nama }}</span><span class="pr-pil" :class="kelayakan(a.status).kelas">{{ kelayakan(a.status).pendek }}</span>
      </button>
    </div>
    <p class="fr-sub">Alat bantu angkat</p>
    <div class="grid gap-2 md:grid-cols-2">
      <button v-for="g in pilihan.gear" :key="'g' + g.id" class="fr-pilih" @click="router.visit(`/pengangkatan/riksa-uji/baru?objek=gear&id=${g.id}`)">
        <span class="min-w-0 text-left"><b>{{ g.kode }}</b> · {{ g.nama }}</span><span class="pr-pil" :class="kelayakan(g.status).kelas">{{ kelayakan(g.status).pendek }}</span>
      </button>
    </div>
  </Kartu>

  <form v-else class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] items-start" @submit.prevent="kirim">
    <div class="space-y-4">
      <Kartu :judul="`${t.kode} · ${t.nama}`" :ket="`${props.objek === 'alat' ? 'Pesawat angkat ' + n(t.kapasitas_t, 0) + ' t' : 'Alat bantu · WLL ' + n(t.wll_t, 2) + ' t'} · status sekarang ${kelayakan(t.status).pendek.toLowerCase()}`" ikon="scan-eye" warna="biru">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fr-l">Jenis pemeriksaan<select v-model="form.jenis" class="fr-i"><option v-for="j in pil.jenisRiksa" :key="j.nilai" :value="j.nilai">{{ j.label }}</option></select></label>
          <label class="fr-l">Pemeriksa (Ahli K3 PAA)<input v-model="form.pemeriksa" required maxlength="120" class="fr-i"></label>
          <label class="fr-l">No. SKP / lisensi Kemnaker<input v-model="form.skp_nomor" required maxlength="80" placeholder="mis. 5/2831/AS.01.02/V/2025" class="fr-i"></label>
          <label class="fr-l">PJK3<input v-model="form.pjk3" maxlength="150" class="fr-i"></label>
        </div>
      </Kartu>

      <Kartu judul="Butir pemeriksaan visual & fungsi" ket="Butir bertanda KRITIS yang gagal langsung membuat alat tidak layak digunakan" ikon="list-checks" warna="amber">
        <ul class="space-y-2">
          <li v-for="x in props.butir" :key="x.kode" class="fr-butir">
            <div class="min-w-0 flex-1"><p class="text-[12.5px] font-semibold text-cam-ink">{{ x.label }}</p>
              <p class="text-[11px] text-stone-500">{{ x.kode }}<b v-if="x.kritis" class="fr-kritis"> · KRITIS</b></p></div>
            <div class="fr-segmen">
              <button v-for="s in [['lulus', 'Lulus'], ['gagal', 'Gagal'], ['na', 'N/A']]" :key="s[0]" type="button" :class="[{ aktif: form.periksa[x.kode] === s[0] }, 'fr-' + s[0]]" @click="form.periksa[x.kode] = s[0]">{{ s[1] }}</button>
            </div>
          </li>
        </ul>
      </Kartu>

      <Kartu judul="Ukuran, NDT & uji beban" :ket="b ? `Beban uji statis ${b.statis_pct}% = ${n(b.beban_statis_t, 2)} t, tahan ${b.tahan_menit} menit${b.dinamis_pct ? ` · dinamis ${b.dinamis_pct}% = ${n(b.beban_dinamis_t, 2)} t` : ''}` : ''" ikon="ruler" warna="ungu">
        <div class="grid gap-3 md:grid-cols-3">
          <label v-for="u in props.ukur" :key="u.kode" class="fr-l">{{ u.label }} ({{ u.satuan }})
            <input v-model.number="form.ukur[u.kode]" type="number" step="0.1" min="0" :placeholder="`batas ${u.batas}`" class="fr-i num" :class="{ 'fr-lewat': form.ukur[u.kode] !== null && form.ukur[u.kode] !== '' && Number(form.ukur[u.kode]) > u.batas }">
          </label>
          <label class="fr-l">Metode NDT<select v-model="form.ndt_metode" class="fr-i"><option v-for="x in pil.ndt" :key="x.nilai" :value="x.nilai">{{ x.label }}</option></select></label>
          <label class="fr-l">Hasil NDT<select v-model="form.ndt_hasil" class="fr-i"><option value="tanpa_indikasi">Tanpa indikasi</option><option value="ada_cacat">Ditemukan retak/cacat</option></select></label>
          <label class="fr-l md:col-span-3">Catatan pemeriksa<textarea v-model="form.catatan" rows="2" maxlength="2000" class="fr-i" /></label>
        </div>
        <p v-if="Object.keys(form.errors).length" class="mt-3 text-[12px] fr-merah">{{ Object.values(form.errors)[0] }}</p>
      </Kartu>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-4">
      <section v-if="nilai" class="fr-putusan" :class="nilai.putusan === 'layak' ? 'fr-putusan-hijau' : 'fr-putusan-merah'">
        <Ikon :nama="nilai.putusan === 'layak' ? 'shield-check' : 'shield-x'" :ukuran="34" />
        <p class="fr-putusan-judul">{{ riksa(nilai.putusan).label }}</p>
        <p class="text-[12px] opacity-90">{{ nilai.putusan === 'layak' ? (b ? `Berlaku ${form.jenis === 'triwulan' ? 3 : b.bulan} bulan sejak hari ini` : '') : 'Disimpan → alat dikarantina, label MERAH, tidak dapat dipilih di rencana.' }}</p>
        <ul v-if="nilai.alasan.length" class="mt-3 space-y-1 text-[12px] text-left">
          <li v-for="(a, i) in nilai.alasan" :key="i" class="flex gap-2"><Ikon nama="circle-x" :ukuran="14" class="shrink-0 mt-px" />{{ a }}</li>
        </ul>
        <p v-if="nilai.minor" class="mt-2 text-[12px]">{{ nilai.minor }} butir minor gagal — catat perbaikannya.</p>
      </section>
      <Kartu judul="Acuan uji" ikon="book-check" warna="biru">
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[12px]">
          <dt class="text-stone-500">Kategori</dt><dd class="font-bold">{{ props.kategoriLabel }}</dd>
          <dt class="text-stone-500">SWL / WLL</dt><dd class="font-bold num">{{ n(props.objek === 'alat' ? t.kapasitas_t : t.wll_t, 2) }} t</dd>
          <template v-if="b"><dt class="text-stone-500">Uji statis</dt><dd class="font-bold num">{{ b.statis_pct }}% · {{ n(b.beban_statis_t, 2) }} t · {{ b.tahan_menit }} menit</dd></template>
          <template v-if="b?.dinamis_pct"><dt class="text-stone-500">Uji dinamis</dt><dd class="font-bold num">{{ b.dinamis_pct }}% · {{ n(b.beban_dinamis_t, 2) }} t</dd></template>
          <dt class="text-stone-500">Uji terakhir</dt><dd class="font-bold">{{ tanggal(t.uji_terakhir ?? t.suket_berlaku) }}</dd>
        </dl>
        <p class="text-[11px] text-stone-500 mt-3">Permenaker 8/2020 · ASME B30.5 / B30.9 / B30.26 · BS EN 13414</p>
      </Kartu>
      <div class="flex flex-wrap gap-2">
        <button class="eq-btn-utama" :disabled="form.processing">Simpan laporan riksa uji</button>
        <Link href="/pengangkatan/peralatan" class="eq-btn-lain">Batal</Link>
      </div>
    </aside>
  </form>
</template>

<style scoped>
.fr-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.fr-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.fr-lewat { border-color: #DC2626; background: #FEF2F2; }
.fr-sub { font-size: 11px; font-weight: 800; color: #78716C; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
.fr-pilih { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 12px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; }
.fr-pilih:hover { border-color: #1D4ED8; }
.fr-butir { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid #F0EFED; }
.fr-butir:first-child { border-top: 0; }
.fr-kritis { color: #B91C1C; }
.fr-segmen { display: inline-flex; border-radius: 10px; border: 1px solid #E7E5E4; overflow: hidden; }
.fr-segmen button { padding: 6px 12px; font-size: 11.5px; font-weight: 700; color: #78716C; background: #fff; }
.fr-segmen button + button { border-left: 1px solid #E7E5E4; }
.fr-segmen .fr-lulus.aktif { background: #15803D; color: #fff; }
.fr-segmen .fr-gagal.aktif { background: #DC2626; color: #fff; }
.fr-segmen .fr-na.aktif { background: #78716C; color: #fff; }
.fr-merah { color: #B91C1C; }
.fr-putusan { border-radius: 20px; padding: 20px; color: #fff; text-align: center; display: grid; justify-items: center; gap: 4px; }
.fr-putusan-hijau { background: linear-gradient(135deg, #14532D, #16A34A); }
.fr-putusan-merah { background: linear-gradient(135deg, #7F1D1D, #DC2626); }
.fr-putusan-judul { font-size: 20px; font-weight: 900; letter-spacing: .01em; }
:root[data-tema="gelap"] .fr-l, :root[data-tema="gelap"] .fr-sub { color: #93A1A8; }
:root[data-tema="gelap"] .fr-pilih, :root[data-tema="gelap"] .fr-segmen button { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .fr-butir { border-color: #2E373C; }
:root[data-tema="gelap"] .fr-segmen .fr-lulus.aktif { background: #15803D; color: #fff; }
:root[data-tema="gelap"] .fr-segmen .fr-gagal.aktif { background: #DC2626; color: #fff; }
:root[data-tema="gelap"] .fr-segmen .fr-na.aktif { background: #5B676D; color: #fff; }
:root[data-tema="gelap"] .fr-kritis, :root[data-tema="gelap"] .fr-merah { color: #F5A9A9; }
:root[data-tema="gelap"] .fr-lewat { background: #2A1616; }
</style>
