<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import KepalaHalaman from '../../Components/Smkp/KepalaHalaman.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti konfirmasi() Aurorasys. */
const { dialog, tanya, batal, lanjut } = useDialog();

const props = defineProps<{
  audit: { id: number | null; tahun: number; judul: string; company_id: number | string; status: string; tanggal_mulai: string; tanggal_selesai: string; ketua_auditor: string };
  perusahaan: Array<{ id: number; nama: string; kategori: string }>;
  statusPilihan: Record<string, string>;
  bisaHapus?: boolean;
}>();

const form = useForm({ ...props.audit });
const KAT: Record<string, string> = { induk: 'Pemegang IUP', pjp: 'PJP', sister: 'Sister Company', lainnya: 'Lainnya' };

function simpan() {
  if (props.audit.id) form.put(`/smkp/${props.audit.id}`, { preserveScroll: true });
  else form.post('/smkp', { preserveScroll: true });
}

async function hapus() {
  if (!props.audit.id) return;
  if (await tanya({ judul: 'Hapus periode audit ini?', pesan: 'Seluruh penilaian, temuan, dan daftar hadir audit ini ikut terhapus permanen.', labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/smkp/${props.audit.id}`);
  }
}
</script>

<template>
  <div class="smkp-ui">
    <Head :title="audit.id ? 'Ubah Audit' : 'Audit Baru'" />
    <KepalaHalaman :judul="audit.id ? 'Ubah Periode Audit' : 'Audit SMKP Baru'"
                   sub="Satu periode audit per perusahaan per tahun. Penilaian, Tahap I, dan Rencana Audit diisi setelah periode dibuat."
                   :jejak="[{ label: 'Audit SMKP', url: '/smkp' }, ...(audit.id ? [{ label: audit.judul || `Audit ${audit.tahun}`, url: `/smkp/${audit.id}` }] : []), { label: audit.id ? 'Ubah' : 'Baru' }]" />

    <form class="kartu max-w-3xl" @submit.prevent="simpan">
      <div class="p-5 sm:p-7 grid sm:grid-cols-2 gap-5">
        <div class="sm:col-span-2">
          <label class="label">Perusahaan yang diaudit</label>
          <select v-model="form.company_id" class="input" required>
            <option value="" disabled>Pilih perusahaan…</option>
            <option v-for="p in perusahaan" :key="p.id" :value="p.id">{{ p.nama }} — {{ KAT[p.kategori] ?? p.kategori }}</option>
          </select>
          <p v-if="form.errors.company_id" class="galat">{{ form.errors.company_id }}</p>
          <p v-else-if="!perusahaan.length" class="bantu">Belum ada perusahaan dalam jangkauan akun Anda.</p>
        </div>
        <div class="sm:col-span-2">
          <label class="label">Judul audit</label>
          <input v-model="form.judul" class="input" :placeholder="`Audit Internal SMKP ${form.tahun}`">
          <p class="bantu">Kosongkan untuk memakai judul bawaan.</p>
        </div>
        <div>
          <label class="label">Tahun periode</label>
          <input v-model.number="form.tahun" type="number" min="2000" max="2100" class="input" required>
          <p v-if="form.errors.tahun" class="galat">{{ form.errors.tahun }}</p>
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="form.status" class="input">
            <option v-for="(label, k) in statusPilihan" :key="k" :value="k">{{ label }}</option>
          </select>
          <p v-if="form.errors.status" class="galat">{{ form.errors.status }}</p>
          <p v-else class="text-[11.5px] text-slate-400 mt-1">Audit berstatus Selesai saat dipindah ke tahap Pelaporan.</p>
        </div>
        <div>
          <label class="label">Tanggal mulai</label>
          <input v-model="form.tanggal_mulai" type="date" class="input">
        </div>
        <div>
          <label class="label">Tanggal selesai</label>
          <input v-model="form.tanggal_selesai" type="date" class="input">
          <p v-if="form.errors.tanggal_selesai" class="galat">{{ form.errors.tanggal_selesai }}</p>
        </div>
        <div class="sm:col-span-2">
          <label class="label">Ketua tim audit</label>
          <input v-model="form.ketua_auditor" class="input" placeholder="Nama ketua tim audit">
        </div>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-7 py-4 border-t border-slate-100 bg-slate-50/60 rounded-b-2xl">
        <button v-if="audit.id && bisaHapus" type="button" class="btn-garis-bahaya" @click="hapus"><Ikon nama="hapus" class="w-4 h-4" /> Hapus audit</button>
        <span v-else />
        <div class="flex gap-2">
          <Link :href="audit.id ? `/smkp/${audit.id}` : '/smkp'" class="btn-garis">Batal</Link>
          <button class="btn-utama" :disabled="form.processing"><Ikon nama="simpan" class="w-4 h-4" /> {{ audit.id ? 'Simpan perubahan' : 'Buat audit' }}</button>
        </div>
      </div>
    </form>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
