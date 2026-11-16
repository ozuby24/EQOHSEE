<script setup lang="ts">
/**
 * Pusat Kendali → Aturan Akses Audit (port DBEST #16).
 *
 * Per perusahaan: auditor (yang boleh mengaudit perusahaan itu di Audit
 * SMKP dan Audit Lingkungan) dan pengunggah data (yang mengunggah bukti dan
 * menjawab temuan/RTP). Perusahaan tanpa penugasan memakai aturan bawaan
 * (peran audit akun dan grup perusahaannya). Satu akun satu peran per
 * perusahaan — aturan independensi.
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

interface Orang { id: number; nama: string; email: string }
interface Baris { id: number; nama: string; kode: string; induk: boolean; auditor: Orang[]; pengunggah: Orang[] }

const props = defineProps<{
  judul: string;
  baris: Baris[];
  peran: Record<string, string>;
  akun: Array<{ id: number; nama: string; email: string; auditor: boolean }>;
}>();

const { dialog, tanya, batal, lanjut } = useDialog();
const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});

const cari = ref('');
const tampil = computed(() => props.baris.filter((b) => !cari.value.trim() || b.nama.toLowerCase().includes(cari.value.trim().toLowerCase())));

const form = useForm({ company_id: '' as string | number, user_id: '' as string | number, peran: 'auditor' });
const pilihanAkun = computed(() => props.akun.filter((a) => form.peran !== 'auditor' || a.auditor));
function tetapkan(companyId?: number) {
  if (companyId) form.company_id = companyId;
  form.post('/admin/akses-audit', { preserveScroll: true, onSuccess: () => form.reset('user_id') });
}
async function cabut(o: Orang, b: Baris, peran: string) {
  const ok = await tanya({
    judul: 'Cabut penugasan?',
    pesan: `${o.nama} tidak lagi menjadi ${props.peran[peran].toLowerCase()} ${b.nama}. Bila tidak ada penugasan tersisa, perusahaan ini kembali memakai aturan bawaan.`,
    labelAksi: 'Cabut', nada: 'bahaya',
  });
  if (ok) router.delete(`/admin/akses-audit/${o.id}`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />
  <div class="space-y-5">
    <section class="rounded-2xl bg-white border border-stone-200 p-5 sm:p-6 shadow-card">
      <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-stone-500">Pusat Kendali · Audit</p>
      <h1 class="mt-1 text-[22px] font-bold text-stone-900">{{ judul }}</h1>
      <div class="mt-4 grid gap-3 sm:grid-cols-3 text-[12.5px] leading-relaxed">
        <div class="rounded-xl bg-sky-50 border border-sky-200 p-3"><b class="block text-stone-900">Administrator</b>Melihat dan mengaudit seluruh perusahaan.</div>
        <div class="rounded-xl bg-teal-50 border border-teal-200 p-3"><b class="block text-stone-900">Auditor</b>Menilai, memverifikasi bukti & RTP, mengisi Tahap I, Rencana, dan Laporan perusahaan yang ditugaskan — juga perusahaan di luar grupnya.</div>
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3"><b class="block text-stone-900">Pengunggah data</b>Mengunggah bukti dan menjawab temuan/RTP — hanya untuk perusahaannya.</div>
      </div>
      <p class="mt-3 text-[12px] text-amber-800 bg-amber-50 rounded-lg px-3 py-2">
        Perusahaan tanpa penugasan memakai aturan bawaan: peran audit akun dan grup perusahaannya. Satu akun tidak boleh menjadi auditor sekaligus pengunggah perusahaan yang sama.
      </p>
    </section>

    <section class="rounded-2xl bg-white border border-stone-200 p-5 sm:p-6 shadow-card">
      <h2 class="text-[15px] font-bold text-stone-900">Tetapkan akses</h2>
      <form class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_180px_auto] items-end" @submit.prevent="tetapkan()">
        <label class="block"><span class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1">Perusahaan</span>
          <select v-model="form.company_id" class="w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px]" required>
            <option value="">— pilih —</option>
            <option v-for="b in baris" :key="b.id" :value="b.id">{{ b.nama }}</option>
          </select></label>
        <label class="block"><span class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1">Akun</span>
          <select v-model="form.user_id" class="w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px]" required>
            <option value="">— pilih —</option>
            <option v-for="a in pilihanAkun" :key="a.id" :value="a.id">{{ a.nama }} · {{ a.email }}</option>
          </select></label>
        <label class="block"><span class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1">Peran</span>
          <select v-model="form.peran" class="w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px]">
            <option v-for="(l, k) in peran" :key="k" :value="k">{{ l }}</option>
          </select></label>
        <button class="eq-btn-utama" style="flex:none" :disabled="form.processing">Tetapkan</button>
      </form>
      <p v-if="galat.user_id" class="mt-2 text-[12.5px] text-red-700">{{ galat.user_id }}</p>
      <p v-if="form.peran === 'auditor'" class="mt-1 text-[11.5px] text-stone-500">Hanya akun berperan auditor yang dapat ditugaskan sebagai auditor.</p>
    </section>

    <section class="rounded-2xl bg-white border border-stone-200 shadow-card overflow-hidden">
      <div class="p-4 border-b border-stone-100">
        <input v-model="cari" type="search" class="w-full sm:w-80 rounded-xl border border-stone-200 px-3 py-2 text-[13px]" placeholder="Cari perusahaan" aria-label="Cari perusahaan">
      </div>
      <div class="gulir-x">
        <table class="w-full min-w-[760px] text-[13px]">
          <thead class="bg-stone-50 text-left text-[10.5px] uppercase tracking-wide text-stone-500">
            <tr><th class="px-4 py-2">Perusahaan</th><th class="px-4 py-2">Auditor</th><th class="px-4 py-2">Pengunggah data</th></tr>
          </thead>
          <tbody>
            <tr v-for="b in tampil" :key="b.id" class="border-t border-stone-100 align-top">
              <td class="px-4 py-3"><b class="text-stone-900">{{ b.nama }}</b><span v-if="b.kode" class="text-stone-400"> · {{ b.kode }}</span>
                <span class="block text-[11.5px] text-stone-500">{{ b.auditor.length || b.pengunggah.length ? 'Aturan khusus' : 'Aturan bawaan' }}</span></td>
              <td v-for="peranKunci in ['auditor', 'pengunggah']" :key="peranKunci" class="px-4 py-3">
                <span v-for="o in (b as any)[peranKunci] as Orang[]" :key="o.id" class="inline-flex items-center gap-1 mr-1 mb-1 rounded-full bg-stone-100 px-2.5 py-1 text-[12px]">
                  {{ o.nama }}
                  <button type="button" class="text-stone-400 hover:text-red-600" :aria-label="`Cabut ${o.nama}`" @click="cabut(o, b, peranKunci)">×</button>
                </span>
                <span v-if="!(b as any)[peranKunci].length" class="text-[12px] text-stone-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
