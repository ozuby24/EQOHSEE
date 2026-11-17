<script setup lang="ts">
/**
 * Coaching Log — pendampingan operator dan tindak lanjutnya.
 *
 * Dibuka dari rincian sesi, isiannya sudah terisi: operator, unit, dan
 * rekomendasi coaching sesi itu sebagai materi awal. Coaching yang
 * lahir dari sesi observasi tetap tertaut ke sesinya.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { BarisCoaching, IsianCoaching, TautanFrop } from './tipe';
import { tgl } from './fmt';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import './frop.css';

const props = defineProps<{
  judul: string;
  saring: { operator: string };
  awal: IsianCoaching;
  bukaForm: boolean;
  daftar: BarisCoaching[];
  opsi: { status: string[]; operator: string[] };
  bolehHapus: boolean;
  tautan: TautanFrop & { simpanCoaching: string };
}>();

const f = useForm<IsianCoaching>({ ...props.awal });
const tampil = ref(props.bukaForm);
const sunting = ref<BarisCoaching | null>(null);

const baru = () => { sunting.value = null; f.defaults({ ...props.awal }); f.reset(); f.clearErrors(); tampil.value = true; };
const ubah = (c: BarisCoaching) => {
  sunting.value = c;
  f.defaults({
    observasi_id: c.observasi_id ? String(c.observasi_id) : '', tanggal: c.tanggal, operator: c.operator,
    unit: c.unit ?? '', materi: c.materi, respons: c.respons ?? '', coach: c.coach ?? '',
    follow_up: c.follow_up ?? '', target_selesai: c.target_selesai ?? '', status: c.status,
  });
  f.reset(); f.clearErrors(); tampil.value = true;
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const kirim = () => {
  const opsi = { preserveScroll: true, onSuccess: () => { tampil.value = false; sunting.value = null; } };
  if (sunting.value) f.transform((d) => ({ ...d, _method: 'put' })).post(sunting.value.ubah, opsi);
  else f.post(props.tautan.simpanCoaching, opsi);
};

const { dialog, tanya, batal, lanjut } = useDialog();
const hapus = async (c: BarisCoaching) => {
  if (await tanya({ judul: 'Hapus catatan coaching?', pesan: `Coaching ${c.operator} tanggal ${tgl(c.tanggal)} dihapus.`,
                    labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(c.hapus, { preserveScroll: true });
  }
};

const saring = ref(props.saring.operator);
const terapkan = () => router.get(props.tautan.coachingLog, saring.value ? { operator: saring.value } : {}, { preserveState: true, replace: true });
</script>

<template>
  <Head :title="judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="space-y-4">
    <div class="fr-kartu">
      <div class="fr-saring">
        <label>
          <span>Operator</span>
          <select v-model="saring" class="fr-pilih" @change="terapkan">
            <option value="">Semua</option>
            <option v-for="o in opsi.operator" :key="o" :value="o">{{ o }}</option>
          </select>
        </label>
        <button type="button" class="eq-btn-utama ml-auto" style="flex:none" @click="baru">+ Catat Coaching</button>
      </div>
    </div>

    <section v-if="tampil" class="fr-kartu">
      <div class="fr-kartu-kepala">
        <h3>{{ sunting ? 'Ubah coaching' : 'Coaching baru' }}</h3>
        <span v-if="f.observasi_id" class="fr-ket">tertaut ke sesi observasi #{{ f.observasi_id }}</span>
      </div>
      <form class="fr-isi grid gap-3 sm:grid-cols-4" @submit.prevent="kirim">
        <div>
          <label class="fr-label" for="c-tgl">Tanggal *</label>
          <input id="c-tgl" v-model="f.tanggal" type="date" class="fr-isian">
          <p v-if="f.errors.tanggal" class="fr-galat">{{ f.errors.tanggal }}</p>
        </div>
        <div>
          <label class="fr-label" for="c-op">Operator *</label>
          <input id="c-op" v-model="f.operator" class="fr-isian" list="c-operator" autocomplete="off">
          <datalist id="c-operator"><option v-for="o in opsi.operator" :key="o" :value="o" /></datalist>
          <p v-if="f.errors.operator" class="fr-galat">{{ f.errors.operator }}</p>
        </div>
        <div>
          <label class="fr-label" for="c-unit">CN Unit</label>
          <input id="c-unit" v-model="f.unit" class="fr-isian">
        </div>
        <div>
          <label class="fr-label" for="c-coach">PIC (coach)</label>
          <input id="c-coach" v-model="f.coach" class="fr-isian">
        </div>
        <div class="sm:col-span-2">
          <label class="fr-label" for="c-materi">Materi coaching *</label>
          <textarea id="c-materi" v-model="f.materi" rows="3" maxlength="2000" class="fr-isian"></textarea>
          <p v-if="f.errors.materi" class="fr-galat">{{ f.errors.materi }}</p>
        </div>
        <div class="sm:col-span-2">
          <label class="fr-label" for="c-respons">Respons operator</label>
          <textarea id="c-respons" v-model="f.respons" rows="3" maxlength="2000" class="fr-isian"></textarea>
        </div>
        <div class="sm:col-span-2">
          <label class="fr-label" for="c-fu">Follow up / action</label>
          <textarea id="c-fu" v-model="f.follow_up" rows="2" maxlength="2000" class="fr-isian"></textarea>
        </div>
        <div>
          <label class="fr-label" for="c-target">Target selesai</label>
          <input id="c-target" v-model="f.target_selesai" type="date" class="fr-isian">
        </div>
        <div>
          <label class="fr-label" for="c-status">Status follow up</label>
          <select id="c-status" v-model="f.status" class="fr-isian"><option v-for="o in opsi.status" :key="o">{{ o }}</option></select>
        </div>
        <div class="sm:col-span-4 flex gap-2">
          <button type="submit" class="eq-btn-utama" style="flex:none" :disabled="f.processing">{{ f.processing ? 'Menyimpan…' : 'Simpan' }}</button>
          <button type="button" class="eq-btn-mini" @click="tampil = false">Batal</button>
        </div>
      </form>
    </section>

    <section class="fr-kartu overflow-hidden">
      <div class="fr-kartu-kepala"><h3>Coaching Log</h3><span class="fr-ket num">{{ daftar.length }} catatan</span></div>
      <div class="fr-gulir">
        <table class="fr-tabel">
          <thead>
            <tr><th>Tanggal</th><th>Operator</th><th>Materi coaching</th><th>Respons</th><th>Coach</th>
                <th>Follow up</th><th>Target</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in daftar" :key="c.id">
              <td class="num whitespace-nowrap">{{ tgl(c.tanggal) }}
                <Link v-if="c.observasi_url" :href="c.observasi_url" class="block fr-ket underline">sesi observasi</Link></td>
              <td style="min-width: 9rem"><b>{{ c.operator }}</b><span class="block fr-ket">{{ c.unit ?? '' }}</span></td>
              <td style="min-width: 14rem">{{ c.materi }}</td>
              <td style="min-width: 10rem" class="text-stone-600">{{ c.respons ?? '–' }}</td>
              <td>{{ c.coach ?? '–' }}</td>
              <td style="min-width: 10rem" class="text-stone-600">{{ c.follow_up ?? '–' }}</td>
              <td class="num whitespace-nowrap">{{ tgl(c.target_selesai) }}</td>
              <td><span class="fr-lencana" :class="c.status === 'Closed' ? 'fr-baik' : c.status === 'In Progress' ? 'fr-ingat' : 'fr-gawat'">{{ c.status }}</span></td>
              <td class="whitespace-nowrap">
                <button type="button" class="eq-btn-mini" @click="ubah(c)">Ubah</button>
                <button v-if="bolehHapus" type="button" class="eq-btn-mini bahaya ml-1" @click="hapus(c)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!daftar.length">
              <td colspan="9"><p class="eq-kosong"><strong>Belum ada coaching tercatat.</strong>
                <span class="block halus">Catat dari halaman rincian sesi agar materi dan operatornya terisi otomatis.</span></p></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
