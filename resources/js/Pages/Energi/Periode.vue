<script setup lang="ts">
/*
  Verifikasi data harian & kunci periode (FSD §7, §8.2).
  Kalender kelengkapan: hijau lengkap & terverifikasi, kuning belum
  diverifikasi, merah kosong, abu terkunci.
*/
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n, tgl } from '../../energi';

const props = propHalaman();
const WARNA: Record<string, string> = {
  lengkap: 'bg-emerald-50 text-emerald-700', belum: 'bg-amber-50 text-amber-800', kosong: 'bg-red-50 text-red-700',
  terkunci: 'bg-slate-50 text-slate-700', mendatang: 'bg-stone-50 text-stone-400',
};
const pindah = (bulan: number, tahun = props.tahun) => router.get('/energi/periode', { tahun, bulan, perusahaan: props.perusahaan ?? undefined }, { preserveScroll: true });

const pilih = reactive<Record<string, boolean>>({});
const kunciBaris = (b: any) => `${b.jenis}:${b.id}`;
const terpilih = computed(() => (props.antre ?? []).filter((b: any) => pilih[kunciBaris(b)]));
const semua = (on: boolean) => (props.antre ?? []).forEach((b: any) => { pilih[kunciBaris(b)] = on; });
const alasan = ref('');
const kirim = (aksi: string) => router.post('/energi/periode/verifikasi', {
  aksi, alasan: alasan.value || null, baris: terpilih.value.map((b: any) => ({ jenis: b.jenis, id: b.id })),
}, { preserveScroll: true, onSuccess: () => { Object.keys(pilih).forEach(k => delete pilih[k]); alasan.value = ''; } });

const kunci = () => router.post(`/energi/periode/${props.periode.id}/kunci`, {}, { preserveScroll: true });

const koreksi = useForm<Record<string, any>>({ jenis: 'bbm', id: '', kolom: 'liter', nilaiBaru: '', alasan: '' });
const ajukan = () => koreksi.transform(d => ({ jenis: d.jenis, id: d.id, alasan: d.alasan, nilai: { [d.kolom]: d.nilaiBaru } }))
  .post('/energi/periode/koreksi', { preserveScroll: true, onSuccess: () => koreksi.reset('id', 'nilaiBaru', 'alasan') });
const putus = (k: any, putusan: string) => router.post(`/energi/periode/koreksi/${k.id}`, { putusan }, { preserveScroll: true });
</script>

<template>
  <Head title="Verifikasi & Periode" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <label class="text-[11px] font-semibold text-stone-500">Tahun<input :value="props.tahun" type="number" min="2000" max="2100" class="block mt-1 rounded-lg border-stone-200 text-[12px] w-28" @change="pindah(props.bulan, Number(($event.target as HTMLInputElement).value))"></label>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" :nama="props.namaPerusahaan" />
    </section>

    <Kartu judul="Kalender kelengkapan" ket="Klik bulan untuk memeriksa antrean verifikasi dan syarat kuncinya.">
      <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-12 gap-2">
        <button v-for="k in props.kalender" :key="k.bulan" type="button" :disabled="k.keadaan === 'mendatang'" @click="pindah(k.bulan)"
                class="rounded-xl p-2 text-center border-2" :class="[WARNA[k.keadaan], k.bulan === props.bulan ? 'border-cam-orange' : 'border-transparent']">
          <span class="block text-[12px] font-bold">{{ k.label }}</span>
          <span class="block text-[10px]">{{ k.keadaan === 'terkunci' ? 'terkunci' : k.kelengkapan !== null && k.kelengkapan !== undefined ? n(k.kelengkapan, 0) + '%' : '—' }}</span>
          <span v-if="k.belum" class="block text-[9.5px]">{{ k.belum }} belum</span>
        </button>
      </div>
    </Kartu>

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
      <Kartu :judul="`Antrean verifikasi — ${props.periode.label}`" ket="Centang massal per tanggal; penolakan wajib beralasan dan dikembalikan ke penginput.">
        <template #aksi>
          <button type="button" class="eq-btn-mini" @click="semua(true)">Pilih semua</button>
          <button type="button" class="eq-btn-mini" @click="semua(false)">Lepas</button>
        </template>
        <div class="max-h-[420px] overflow-y-auto">
          <table class="w-full text-[12px]">
            <tbody>
              <tr v-for="b in props.antre" :key="kunciBaris(b)" class="border-b border-stone-50">
                <td class="py-1.5 w-6"><input v-model="pilih[kunciBaris(b)]" type="checkbox" :aria-label="`Pilih ${b.label} ${b.tanggal}`"></td>
                <td class="py-1.5">{{ tgl(b.tanggal) }}</td><td class="py-1.5 text-stone-500">{{ b.label }}</td><td class="py-1.5">{{ b.uraian }}</td>
                <td class="py-1.5"><Lencana :status="b.status" /><span v-if="b.alasan_tolak" class="block text-[10px] text-red-600">{{ b.alasan_tolak }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!props.antre?.length" class="text-[12px] text-stone-400 py-6 text-center">Tidak ada baris yang menunggu verifikasi pada bulan ini.</p>
        </div>
        <div v-if="props.boleh.verifikasi && props.antre?.length" class="flex flex-wrap items-end gap-2 mt-3">
          <button type="button" class="eq-btn-setuju" :disabled="!terpilih.length" @click="kirim('verifikasi')">Verifikasi {{ terpilih.length }} baris</button>
          <label class="text-[11px] font-semibold text-stone-500 flex-1 min-w-[200px]">Alasan penolakan<input v-model="alasan" maxlength="500" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <button type="button" class="eq-btn-tolak" :disabled="!terpilih.length || !alasan" @click="kirim('tolak')">Tolak</button>
        </div>

        <div v-if="props.anomali?.length" class="mt-4">
          <p class="text-[12px] font-bold text-red-700 mb-1">Anomali konsumsi bulan ini</p>
          <ul class="text-[11.5px] space-y-0.5"><li v-for="[i, a] in Object.entries((props.anomali ?? {}) as Record<string, any>)" :key="i">{{ tgl(a.tanggal) }} · <b>{{ a.unit }}</b> — {{ a.pesan }}</li></ul>
        </div>
      </Kartu>

      <Kartu :judul="`Kunci periode ${props.periode.label}`" :ket="props.periode.status === 'terkunci' ? `Dikunci ${props.periode.pengunci ?? ''} ${props.periode.dikunci_pada ?? ''} · kelengkapan ${n(props.periode.kelengkapan_persen, 1)}%` : 'Keempat syarat FSD §7.2 harus terpenuhi.'">
        <template #aksi><Lencana :status="props.periode.status" /></template>
        <ul v-if="props.syarat" class="space-y-2">
          <li v-for="s in props.syarat.syarat" :key="s.kode" class="flex items-start gap-2 text-[12px]">
            <span class="w-5 h-5 shrink-0 rounded-full grid place-items-center text-[11px] font-bold" :class="s.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">{{ s.ok ? '✔' : '✖' }}</span>
            <span><b>{{ s.label }}</b><span class="block text-[11px] text-stone-500">{{ s.rincian }}</span></span>
          </li>
        </ul>
        <button v-if="props.syarat && props.boleh.kunci" type="button" class="eq-btn-utama mt-3" :disabled="!props.syarat.boleh" @click="kunci">Kunci periode</button>
        <p v-if="props.periode.status === 'terkunci'" class="text-[12px] text-stone-600">Data bulan ini dibekukan. Perubahan hanya lewat pengajuan koreksi di bawah; koreksi yang disetujui menandai laporan terbit sebagai perlu revisi.</p>
      </Kartu>
    </section>

    <Kartu judul="Koreksi pasca-kunci" ket="Setiap koreksi menyimpan nilai lama → baru, alasan, pengaju, dan pemutusnya. Pemutus bukan pengajunya.">
      <form v-if="props.boleh.koreksi" class="grid gap-3 md:grid-cols-6 items-end mb-4" @submit.prevent="ajukan">
        <label class="text-[11px] font-semibold text-stone-500">Jenis data<select v-model="koreksi.jenis" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="bbm">Solar per unit</option><option value="listrik">Listrik</option><option value="lain">Energi lain</option><option value="produksi">Produksi</option><option value="rekon">Rekonsiliasi</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">ID baris<input v-model="koreksi.id" required type="number" min="1" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Kolom<select v-model="koreksi.kolom" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="k in (props.kolomKoreksi?.[koreksi.jenis] ?? [])" :key="k" :value="k">{{ k }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Nilai baru<input v-model="koreksi.nilaiBaru" required type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Alasan<input v-model="koreksi.alasan" required maxlength="2000" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-utama" :disabled="koreksi.processing">Ajukan koreksi</button>
      </form>
      <p v-for="(pesan, k) in koreksi.errors" :key="k" class="text-[11px] text-red-600 mb-2">{{ pesan }}</p>
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Diajukan</th><th class="py-2">Periode</th><th class="py-2">Baris</th><th class="py-2">Lama → baru</th><th class="py-2">Alasan</th><th class="py-2">Status</th><th class="py-2"></th></tr></thead>
        <tbody>
          <tr v-for="k in props.koreksi" :key="k.id" class="border-b border-stone-50 align-top">
            <td class="py-2">{{ k.diajukan_pada }}<span class="block text-[10.5px] text-stone-400">{{ k.pengaju }}</span></td>
            <td class="py-2">{{ k.periode }}</td><td class="py-2">{{ k.subjek }}</td>
            <td class="py-2 font-mono text-[11px]">{{ JSON.stringify(k.nilai_lama) }} → {{ JSON.stringify(k.nilai_baru) }}</td>
            <td class="py-2">{{ k.alasan }}<span v-if="k.catatan_putusan" class="block text-[10.5px] text-stone-500">Putusan: {{ k.catatan_putusan }}</span></td>
            <td class="py-2"><Lencana :status="k.status" /><span v-if="k.pemutus" class="block text-[10.5px] text-stone-400">{{ k.pemutus }}</span></td>
            <td class="py-2 text-right whitespace-nowrap"><template v-if="k.status === 'diajukan' && props.boleh.putus">
              <button type="button" class="eq-btn-setuju" @click="putus(k, 'setujui')">Setujui</button>
              <button type="button" class="eq-btn-tolak ml-1" @click="putus(k, 'tolak')">Tolak</button>
            </template></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!props.koreksi?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada pengajuan koreksi.</p>
    </Kartu>
  </div>
</template>
