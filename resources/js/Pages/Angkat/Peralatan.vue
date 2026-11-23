<script setup lang="ts">
/*
  Register pesawat angkat (crane, hoist) dan alat bantu angkat (sling,
  rantai, webbing, shackle, spreader).

  Status di sini adalah status efektif — dihitung dari tanggal riksa uji
  saat halaman dibuka — bukan kolom yang menunggu diperbarui orang.
  Kode warna triwulan ditampilkan sebagai warna sungguhan, karena itulah
  yang dicocokkan rigger dengan cat pada sling di gudang.
*/
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n } from '../../energi';
import { kelayakan, tanggal, sisaHari, WARNA_KODE, KELAYAKAN } from '../../angkat';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();
const pil = computed(() => props.pilihan ?? {});
const tab = ref<'alat' | 'gear'>(props.tab ?? 'alat');
const saring = ref('');
const tambah = ref(false);

const alat = computed(() => (props.alat ?? []).filter((a: any) => !saring.value || a.status === saring.value));
const gear = computed(() => (props.gear ?? []).filter((g: any) => !saring.value || g.status === saring.value));
const hitung = computed(() => {
  const d = tab.value === 'alat' ? props.alat ?? [] : props.gear ?? [];
  const o: Record<string, number> = {};
  d.forEach((x: any) => (o[x.status] = (o[x.status] ?? 0) + 1));
  return o;
});

const fAlat = useForm({ nama: '', jenis: 'mobile_100t', pabrikan: '', model: '', tahun: null as number | null, nomor_seri: '', kapasitas_t: 100, boom_maks_m: null as number | null, lokasi: '', operator_nama: '', operator_sio: '', sio_berlaku: '' });
const fGear = useForm({ kode: '', nama: '', jenis: 'wire_rope', wll_t: 10, diameter_mm: null as number | null, panjang_m: null as number | null, jumlah_kaki: 2, pabrikan: '', lokasi_simpan: '' });
function simpanAlat() { fAlat.post('/pengangkatan/peralatan/alat'); }
function simpanGear() { fGear.post('/pengangkatan/peralatan/gear'); }
async function afkir(g: any) {
  if (await tanya({ judul: `Afkirkan ${g.kode}?`, pesan: 'Alat bantu ditarik permanen dari peredaran dan tidak dapat dipilih lagi. Riwayat riksa ujinya tetap tersimpan.', labelAksi: 'Afkirkan', nada: 'bahaya' })) {
    router.post(`/pengangkatan/peralatan/gear/${g.id}/afkir`, {}, { preserveScroll: true });
  }
}
</script>

<template>
  <Head title="Register PAA & alat bantu angkat" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <nav class="pa-tab">
        <button :class="{ aktif: tab === 'alat' }" @click="tab = 'alat'; saring = ''"><Ikon nama="construction" :ukuran="15" />Pesawat angkat <b class="num">{{ (props.alat ?? []).length }}</b></button>
        <button :class="{ aktif: tab === 'gear' }" @click="tab = 'gear'; saring = ''"><Ikon nama="link-2" :ukuran="15" />Alat bantu angkat <b class="num">{{ (props.gear ?? []).length }}</b></button>
      </nav>
      <div class="flex flex-wrap gap-2 items-center">
        <button class="eq-btn-utama inline-flex items-center gap-2" @click="tambah = !tambah"><Ikon nama="clipboard-list" :ukuran="16" />{{ tambah ? 'Tutup isian' : (tab === 'alat' ? 'Daftarkan crane' : 'Daftarkan alat bantu') }}</button>
        <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
      </div>
    </section>

    <section class="flex flex-wrap gap-2">
      <button class="pa-chip" :class="{ aktif: !saring }" @click="saring = ''">Semua</button>
      <button v-for="(l, k) in KELAYAKAN" v-show="hitung[k]" :key="k" class="pa-chip" :class="{ aktif: saring === k }" @click="saring = String(k)">
        <i :style="{ background: l.warna }" />{{ l.pendek }} <b class="num">{{ hitung[k] ?? 0 }}</b>
      </button>
    </section>

    <Kartu v-if="tambah && tab === 'alat'" judul="Daftarkan pesawat angkat" ket="Crane baru belum ber-SUKET — riksa uji pertama dibuka sesudah disimpan" ikon="construction" warna="amber">
      <form class="grid gap-3 md:grid-cols-3" @submit.prevent="simpanAlat">
        <label class="pa-l md:col-span-2">Nama / merek & model<input v-model="fAlat.nama" required maxlength="160" placeholder="mis. Tadano GR-1000XL rough terrain" class="pa-i"></label>
        <label class="pa-l">Jenis<select v-model="fAlat.jenis" class="pa-i"><option v-for="a in pil.alat" :key="a.nilai" :value="a.nilai">{{ a.label }}</option></select></label>
        <label class="pa-l">Kapasitas (t)<input v-model.number="fAlat.kapasitas_t" type="number" step="0.5" min="0.5" required class="pa-i num"></label>
        <label class="pa-l">Boom maks (m)<input v-model.number="fAlat.boom_maks_m" type="number" step="0.5" min="1" class="pa-i num"></label>
        <label class="pa-l">Pabrikan<input v-model="fAlat.pabrikan" maxlength="80" class="pa-i"></label>
        <label class="pa-l">Tahun<input v-model.number="fAlat.tahun" type="number" min="1970" class="pa-i num"></label>
        <label class="pa-l">Nomor seri<input v-model="fAlat.nomor_seri" maxlength="60" class="pa-i"></label>
        <label class="pa-l">Lokasi<input v-model="fAlat.lokasi" maxlength="150" class="pa-i"></label>
        <label class="pa-l">Operator tetap<input v-model="fAlat.operator_nama" maxlength="120" class="pa-i"></label>
        <label class="pa-l">No. SIO<input v-model="fAlat.operator_sio" maxlength="80" class="pa-i"></label>
        <label class="pa-l">SIO berlaku s.d.<input v-model="fAlat.sio_berlaku" type="date" class="pa-i"></label>
        <p v-if="Object.keys(fAlat.errors).length" class="md:col-span-3 text-[12px] pa-galat">{{ Object.values(fAlat.errors)[0] }}</p>
        <div class="md:col-span-3"><button class="eq-btn-utama" :disabled="fAlat.processing">Simpan & lanjut riksa uji</button></div>
      </form>
    </Kartu>

    <Kartu v-if="tambah && tab === 'gear'" judul="Daftarkan alat bantu angkat" ket="Faktor keamanan diisi dari jenisnya (ASME B30.9/B30.26)" ikon="link-2" warna="hijau">
      <form class="grid gap-3 md:grid-cols-3" @submit.prevent="simpanGear">
        <label class="pa-l">Nomor seri / ID tag<input v-model="fGear.kode" required maxlength="50" placeholder="mis. WRS-28-014" class="pa-i"></label>
        <label class="pa-l md:col-span-2">Nama & spesifikasi<input v-model="fGear.nama" required maxlength="160" placeholder="mis. Wire rope sling IWRC 6×36 Ø28 mm × 8 m 2-kaki" class="pa-i"></label>
        <label class="pa-l">Jenis<select v-model="fGear.jenis" class="pa-i"><option v-for="g in pil.gear" :key="g.nilai" :value="g.nilai">{{ g.label }}</option></select></label>
        <label class="pa-l">WLL (t)<input v-model.number="fGear.wll_t" type="number" step="0.25" min="0.1" required class="pa-i num"></label>
        <label class="pa-l">Jumlah kaki<input v-model.number="fGear.jumlah_kaki" type="number" min="1" max="4" class="pa-i num"></label>
        <label class="pa-l">Diameter (mm)<input v-model.number="fGear.diameter_mm" type="number" step="0.5" class="pa-i num"></label>
        <label class="pa-l">Panjang (m)<input v-model.number="fGear.panjang_m" type="number" step="0.1" class="pa-i num"></label>
        <label class="pa-l">Lokasi simpan<input v-model="fGear.lokasi_simpan" maxlength="120" class="pa-i"></label>
        <p v-if="Object.keys(fGear.errors).length" class="md:col-span-3 text-[12px] pa-galat">{{ Object.values(fGear.errors)[0] }}</p>
        <div class="md:col-span-3"><button class="eq-btn-utama" :disabled="fGear.processing">Simpan & lanjut riksa uji</button></div>
      </form>
    </Kartu>

    <!-- pesawat angkat -->
    <section v-if="tab === 'alat'" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="a in alat" :key="a.id" class="pa-kartu" :style="{ borderTopColor: kelayakan(a.status).warna }">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0"><p class="text-[11px] font-bold text-stone-500">{{ a.kode }} · {{ a.jenis_label }}</p><p class="text-[14px] font-extrabold text-cam-ink leading-tight">{{ a.nama }}</p></div>
          <span class="pr-pil shrink-0" :class="kelayakan(a.status).kelas">{{ kelayakan(a.status).pendek }}</span>
        </div>
        <div class="grid grid-cols-3 gap-2 mt-3">
          <div class="pa-angka"><span>Kapasitas</span><b class="num">{{ n(a.kapasitas_t, 0) }} t</b></div>
          <div class="pa-angka"><span>Boom</span><b class="num">{{ n(a.boom_maks_m, 0) }} m</b></div>
          <div class="pa-angka"><span>Rencana aktif</span><b class="num">{{ a.rencana_aktif }}</b></div>
        </div>
        <p class="text-[11.5px] text-stone-500 mt-3">{{ a.suket_nomor ?? 'Belum ber-SUKET' }} · s.d. {{ tanggal(a.suket_berlaku) }} <b :class="a.status === 'layak' ? '' : 'pa-galat'">({{ sisaHari(a.suket_berlaku) }})</b></p>
        <p class="text-[11.5px] text-stone-500">{{ a.lokasi ?? '—' }} · operator {{ a.operator_nama ?? '—' }} · SIO {{ a.operator_sio ?? '—' }}</p>
        <div class="flex gap-2 mt-3">
          <Link :href="`/pengangkatan/riksa-uji/baru?objek=alat&id=${a.id}`" class="eq-btn-mini">Catat riksa uji</Link>
        </div>
      </article>
      <p v-if="!alat.length" class="text-[12.5px] text-stone-400">Tidak ada crane pada saringan ini.</p>
    </section>

    <!-- alat bantu -->
    <Kartu v-else judul="Alat bantu angkat" :ket="`${gear.length} unit · kode warna triwulan berlaku`" ikon="link-2" warna="hijau" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead><tr class="pa-kepala"><th>Warna</th><th>Nomor seri · nama</th><th>Jenis</th><th class="text-right">WLL</th><th>Uji terakhir</th><th>Uji berikut</th><th>Status</th><th /></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="g in gear" :key="g.id" class="pa-baris">
              <td><span class="pa-cat" :style="{ background: WARNA_KODE[g.kode_warna] ?? '#E7E5E4' }" :title="g.kode_warna ?? 'belum diuji'" /></td>
              <td><p class="font-bold text-cam-ink">{{ g.kode }}</p><p class="text-[11px] text-stone-500">{{ g.nama }}</p></td>
              <td class="whitespace-nowrap">{{ g.jenis_label }}</td>
              <td class="text-right num font-bold whitespace-nowrap">{{ n(g.wll_t, 2) }} t</td>
              <td class="whitespace-nowrap">{{ tanggal(g.uji_terakhir) }}</td>
              <td class="whitespace-nowrap">{{ tanggal(g.uji_berikut) }}<p class="text-[10.5px] text-stone-500">{{ sisaHari(g.uji_berikut) }}</p></td>
              <td><span class="pr-pil" :class="kelayakan(g.status).kelas">{{ kelayakan(g.status).pendek }}</span>
                <p v-if="g.rencana_aktif" class="text-[10.5px] text-stone-500 mt-0.5">dipakai {{ g.rencana_aktif }} rencana</p></td>
              <td class="whitespace-nowrap text-right">
                <Link v-if="g.status !== 'afkir'" :href="`/pengangkatan/riksa-uji/baru?objek=gear&id=${g.id}`" class="eq-btn-mini">Riksa</Link>
                <button v-if="g.status === 'karantina'" class="eq-btn-mini ml-1" @click="afkir(g)">Afkir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.pa-tab { display: inline-flex; gap: 4px; padding: 4px; border-radius: 14px; background: #F5F5F4; }
.pa-tab button { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 11px; font-size: 12.5px; font-weight: 700; color: #57534E; }
.pa-tab button b { font-weight: 900; }
.pa-tab button.aktif { background: #fff; color: #1D4ED8; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
.pa-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; border: 1px solid #E7E5E4; background: #fff; font-size: 12px; font-weight: 700; color: #44403C; }
.pa-chip i { width: 8px; height: 8px; border-radius: 999px; }
.pa-chip.aktif { border-color: #1D4ED8; background: #EFF6FF; color: #1E3A8A; }
.pa-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.pa-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.pa-galat { color: #B91C1C; }
.pa-kartu { border-radius: 18px; background: #fff; border: 1px solid #E7E5E4; border-top-width: 5px; padding: 14px 16px; box-shadow: 0 8px 22px -18px rgba(15, 23, 32, .5); }
.pa-angka { border-radius: 12px; background: #F8FAFC; padding: 7px 9px; display: grid; }
.pa-angka span { font-size: 10px; font-weight: 700; color: #78716C; }
.pa-angka b { font-size: 15px; font-weight: 900; color: #1C1917; }
.pa-kepala th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; }
.pa-kepala th:first-child { padding-left: 20px; }
.pa-baris td { padding: 9px 12px; vertical-align: middle; }
.pa-baris td:first-child { padding-left: 20px; }
.pa-cat { display: inline-block; width: 22px; height: 22px; border-radius: 7px; border: 2px solid rgba(0, 0, 0, .12); }
:root[data-tema="gelap"] .pa-tab { background: #20272B; }
:root[data-tema="gelap"] .pa-tab button { color: #B4BEC3; }
:root[data-tema="gelap"] .pa-tab button.aktif { background: #2E373C; color: #93C5FD; }
:root[data-tema="gelap"] .pa-chip { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .pa-chip.aktif { background: #14233A; border-color: #3B82F6; color: #BFDBFE; }
:root[data-tema="gelap"] .pa-l { color: #93A1A8; }
:root[data-tema="gelap"] .pa-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .pa-kartu { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .pa-angka { background: #20272B; }
:root[data-tema="gelap"] .pa-angka b { color: #E8EDEF; }
:root[data-tema="gelap"] .pa-kepala th { background: #20272B; color: #93A1A8; }
</style>
