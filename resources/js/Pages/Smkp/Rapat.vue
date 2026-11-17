<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import type { RingkasAudit } from '../../smkp/types';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti konfirmasi() Aurorasys. */
const { dialog, tanya, batal, lanjut } = useDialog();

interface Peserta { id: number; nama: string; jabatan: string | null; perusahaan: string | null }

const props = defineProps<{
  audit: RingkasAudit;
  rapat: Array<{ kunci: string; label: string; cetak: string; peserta: Peserta[] }>;
  siap: boolean;
  bisaSunting: boolean;
}>();

const isian = reactive<Record<string, { nama: string; jabatan: string; perusahaan: string; proses: boolean }>>({});
for (const r of props.rapat) isian[r.kunci] = { nama: '', jabatan: '', perusahaan: props.audit.perusahaan.nama, proses: false };

function tambah(kunci: string) {
  const f = isian[kunci];
  if (!f.nama.trim()) return;
  f.proses = true;
  router.post(`/smkp/${props.audit.id}/rapat`, { rapat: kunci, nama: f.nama, jabatan: f.jabatan, perusahaan: f.perusahaan }, {
    preserveScroll: true,
    onSuccess: () => { f.nama = ''; f.jabatan = ''; },
    onFinish: () => { f.proses = false; },
  });
}

async function hapus(p: Peserta) {
  if (await tanya({ judul: `Hapus ${p.nama}?`, pesan: 'Peserta dikeluarkan dari daftar hadir.', labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/smkp/${props.audit.id}/rapat/${p.id}`, { preserveScroll: true });
  }
}
</script>

<template>
  <div class="smkp-ui">
    <Head title="Rapat & Daftar Hadir" />
    <KepalaAudit :audit="audit" aktif="rapat" :bisa-sunting="bisaSunting" />

    <div v-if="!siap" class="mb-5 flex items-start gap-3 rounded-kartu border border-amber-200 bg-amber-50 px-4 py-3.5 text-[13px] text-amber-800">
      <Ikon nama="info" class="w-5 h-5 shrink-0 mt-0.5" />
      <p>Tahap I belum tuntas. Peserta tetap dapat dicatat, tetapi lengkapi dulu
        <Link :href="`/smkp/${audit.id}/tahap-1`" class="font-bold underline">kecukupan dokumentasi</Link> dan
        <Link :href="`/smkp/${audit.id}/rencana`" class="font-bold underline">Rencana Audit</Link>.</p>
    </div>

    <div class="grid xl:grid-cols-2 gap-5">
      <section v-for="r in rapat" :key="r.kunci" class="kartu overflow-hidden flex flex-col">
        <div class="kartu-kepala">
          <div class="flex items-center gap-3">
            <span class="grid place-items-center w-10 h-10 rounded-xl" :class="r.kunci === 'pembukaan' ? 'bg-brand-50 text-brand-600' : 'bg-hijau-50 text-hijau-600'"><Ikon nama="rapat" class="w-5 h-5" /></span>
            <div><h2 class="kartu-judul">{{ r.label }}</h2><p class="kartu-sub">{{ r.peserta.length }} peserta tercatat</p></div>
          </div>
          <Link :href="r.cetak" class="btn-garis btn-kecil"><Ikon nama="cetak" class="w-4 h-4" /> Daftar hadir</Link>
        </div>

        <div class="flex-1">
          <table v-if="r.peserta.length" class="tabel">
            <thead><tr><th class="w-10">No</th><th>Nama</th><th>Jabatan</th><th>Perusahaan</th><th v-if="bisaSunting" class="w-10" /></tr></thead>
            <tbody>
              <tr v-for="(p, i) in r.peserta" :key="p.id">
                <td class="text-slate-400 tabular-nums">{{ i + 1 }}</td>
                <td class="font-semibold text-navy-900">{{ p.nama }}</td>
                <td class="text-slate-600">{{ p.jabatan || '—' }}</td>
                <td class="text-slate-600">{{ p.perusahaan || '—' }}</td>
                <td v-if="bisaSunting" class="text-right"><button type="button" class="p-1.5 rounded-md text-slate-300 hover:text-rose-600 hover:bg-rose-50" @click="hapus(p)" aria-label="Hapus peserta"><Ikon nama="hapus" class="w-4 h-4" /></button></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="px-6 py-8 text-center text-[13px] text-slate-400">Belum ada peserta.</p>
        </div>

        <form v-if="bisaSunting" class="p-5 border-t border-slate-100 bg-slate-50/60 grid sm:grid-cols-[1.2fr_1fr_1fr_auto] gap-2.5" @submit.prevent="tambah(r.kunci)">
          <input v-model="isian[r.kunci].nama" class="input" placeholder="Nama peserta" required>
          <input v-model="isian[r.kunci].jabatan" class="input" placeholder="Jabatan">
          <input v-model="isian[r.kunci].perusahaan" class="input" placeholder="Perusahaan">
          <button class="btn-utama" :disabled="isian[r.kunci].proses"><Ikon nama="tambah" class="w-4 h-4" /> Tambah</button>
        </form>
      </section>
    </div>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
