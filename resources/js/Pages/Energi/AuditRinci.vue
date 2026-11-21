<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import { n, rp, tgl } from '../../energi';

const props = propHalaman();
const { dialog, minta, batal, lanjut } = useDialog();
const a = props.audit;
const audit = useForm<Record<string, any>>({ jenis: a.jenis, judul: a.judul, ruang_lingkup: a.ruang_lingkup ?? '', seu: a.seu ?? [], auditor_ids: a.auditor_ids ?? [],
  mulai: a.mulai, selesai: a.selesai ?? '', standar: a.standar ?? '', status: a.status, ringkasan: a.ringkasan ?? '' });
const rek = useForm<Record<string, any>>({ uraian: '', potensi_hemat_gj: '', potensi_hemat_rp: '', biaya_rp: '', prioritas: 'sedang', tenggat: props.tenggatBawaan ?? '', peluang_id: '' });

const simpanAudit = () => audit.transform(d => ({ ...d, selesai: d.selesai || null })).put(`/energi/manajemen/audit/${a.id}`, { preserveScroll: true });
const simpanRek = () => rek.transform(d => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])))
  .post(`/energi/manajemen/audit/${a.id}/rekomendasi`, { preserveScroll: true, onSuccess: () => rek.reset('uraian', 'potensi_hemat_gj', 'potensi_hemat_rp', 'biaya_rp') });

async function ubahStatus(r: any, status: string) {
  let alasan: string | null = null;
  if (status === 'tidak_layak') { alasan = await minta('Alasan rekomendasi tidak layak dilaksanakan:'); if (!alasan) return; }
  router.put(`/energi/manajemen/rekomendasi/${r.id}`, { uraian: r.uraian, prioritas: r.prioritas, tenggat: r.tenggat, status, alasan_tidak_layak: alasan,
    potensi_hemat_gj: r.potensi_hemat_gj, potensi_hemat_rp: r.potensi_hemat_rp, biaya_rp: r.biaya_rp, peluang_id: r.peluang_id }, { preserveScroll: true });
}
</script>

<template>
  <Head :title="a.judul" />
  <div class="space-y-4">
    <Link href="/energi/manajemen/audit" class="eq-tautan">← Semua audit energi</Link>

    <Kartu :judul="a.judul" :ket="`${a.jenis} · ${tgl(a.mulai)} – ${tgl(a.selesai)} · ${a.standar ?? ''}`">
      <template #aksi><Lencana :status="a.status" /></template>
      <p class="text-[12px] text-stone-600">Auditor: {{ a.auditor.length ? a.auditor.join(', ') : '—' }} · SEU: {{ a.seu.length ? a.seu.join(', ') : 'belum ada SEU — audit belum memenuhi Ps. 13' }}</p>
      <form v-if="props.boleh.kelola" class="grid gap-3 md:grid-cols-4 mt-3" @submit.prevent="simpanAudit">
        <label class="text-[11px] font-semibold text-stone-500">Status<select v-model="audit.status" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="s in props.status" :key="s" :value="s">{{ s }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Selesai<input v-model="audit.selesai" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Auditor<select v-model="audit.auditor_ids" multiple class="block w-full mt-1 rounded-lg border-stone-200 text-[12px] h-16"><option v-for="p in props.auditorPilihan" :key="p.id" :value="p.id">{{ p.nama }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-4">Ringkasan temuan<textarea v-model="audit.ringkasan" rows="3" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
        <div><button class="eq-btn-utama" :disabled="audit.processing">Simpan audit</button></div>
      </form>
    </Kartu>

    <Kartu judul="Rekomendasi" ket="Setiap rekomendasi membuka tindak lanjut ber-PIC dan masuk pengingat tenggat; status tidak layak wajib beralasan.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Uraian</th><th class="py-2 text-right">Potensi</th><th class="py-2">Prioritas</th><th class="py-2">Tenggat</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="r in props.rekomendasi" :key="r.id" class="border-b border-stone-50 align-top">
            <td class="py-2">{{ r.uraian }}<span v-if="r.alasan_tidak_layak" class="block text-[10.5px] text-stone-500">Alasan: {{ r.alasan_tidak_layak }}</span><span v-if="r.peluang" class="block text-[10.5px] text-stone-400">Peluang: {{ r.peluang }}</span></td>
            <td class="py-2 text-right num">{{ n(r.potensi_hemat_gj, 0) }} GJ<span class="block text-[10.5px] text-stone-400">{{ rp(r.potensi_hemat_rp) }} · biaya {{ rp(r.biaya_rp) }}</span></td>
            <td class="py-2">{{ r.prioritas }}</td>
            <td class="py-2" :class="r.lewat ? 'text-red-600 font-bold' : ''">{{ tgl(r.tenggat) }}</td>
            <td class="py-2">
              <select v-if="props.boleh.kelola" :value="r.status" class="rounded border-stone-200 text-[11px]" aria-label="Status rekomendasi" @change="ubahStatus(r, ($event.target as HTMLSelectElement).value)">
                <option v-for="s in props.statusRek" :key="s" :value="s">{{ s }}</option>
              </select>
              <Lencana v-else :status="r.status" />
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!props.rekomendasi?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada rekomendasi.</p>

      <form v-if="props.boleh.kelola" class="grid gap-3 md:grid-cols-4 mt-4 pt-4 border-t border-stone-100" @submit.prevent="simpanRek">
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-4">Uraian rekomendasi<textarea v-model="rek.uraian" required rows="2" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></textarea></label>
        <label class="text-[11px] font-semibold text-stone-500">Potensi hemat (GJ/thn)<input v-model="rek.potensi_hemat_gj" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Potensi hemat (Rp/thn)<input v-model="rek.potensi_hemat_rp" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Biaya (Rp)<input v-model="rek.biaya_rp" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Prioritas<select v-model="rek.prioritas" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option v-for="p in props.prioritas" :key="p" :value="p">{{ p }}</option></select></label>
        <label class="text-[11px] font-semibold text-stone-500">Tenggat (bawaan +3 tahun)<input v-model="rek.tenggat" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500 md:col-span-2">Peluang penghematan terkait<select v-model="rek.peluang_id" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"><option value="">—</option><option v-for="p in props.peluang" :key="p.id" :value="p.id">{{ p.judul }}</option></select></label>
        <div class="flex items-end"><button class="eq-btn-utama" :disabled="rek.processing">Tambah rekomendasi</button></div>
      </form>
      <p v-for="(pesan, k) in rek.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
