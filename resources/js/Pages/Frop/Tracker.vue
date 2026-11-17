<script setup lang="ts">
/**
 * Temuan & tindakan perbaikan — pengganti "Problem & CA Tracker".
 *
 * Tiap temuan dipecah menjadi butirnya dan dikelompokkan per kategori.
 * Butir ditandai berulang bila kategori yang sama pernah tercatat pada
 * UNIT yang sama sebelumnya: front undulating di dua excavator berbeda
 * adalah dua front, bukan satu masalah yang kembali.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { KATEGORI_WARNA, type OpsiBulan, type TautanFrop } from './tipe';
import { tgl } from './fmt';
import './frop.css';

type Baris = {
  id: number; url: string; ca_url: string; tanggal: string; shift: number | null; unit: string; operator: string;
  butir: Array<{ teks: string; kat: string; ulang: boolean }>;
  corrective_action: string | null; pic_ca: string | null; pic_isian: string | null;
  deadline_ca: string | null; selesai_ca: string | null; status_ca: string; lewat: boolean;
};

const props = defineProps<{
  judul: string;
  saring: { bulan: string; kategori: string; status: string };
  opsi: { bulan: OpsiBulan[]; status: string[]; kategori: Array<{ kunci: string; nama: string }> };
  frekuensi: Array<{ kunci: string; nama: string; butir: number; sesi: number; ulang: number }>;
  daftar: Baris[];
  tautan: TautanFrop;
}>();

const s = reactive({ ...props.saring });
const terapkan = () => router.get(props.tautan.tracker, { ...s }, { preserveState: true, preserveScroll: true, replace: true });
const pilihKategori = (k: string) => { s.kategori = s.kategori === k ? '' : k; terapkan(); };

const maks = Math.max(1, ...props.frekuensi.map((f) => f.butir));
const namaKat = (k: string) => props.opsi.kategori.find((x) => x.kunci === k)?.nama ?? k;

const sunting = ref<number | null>(null);
const f = useForm({ corrective_action: '', pic_ca: '', deadline_ca: '', status_ca: 'Open', selesai_ca: '' });
const hariIni = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
watch(() => f.status_ca, (v) => { if (v === 'Closed' && !f.selesai_ca) f.selesai_ca = hariIni(); });
const buka = (b: Baris) => {
  f.clearErrors();
  f.defaults({ corrective_action: b.corrective_action ?? '', pic_ca: b.pic_isian ?? '', deadline_ca: b.deadline_ca ?? '',
               status_ca: b.status_ca, selesai_ca: b.selesai_ca ?? '' });
  f.reset();
  sunting.value = b.id;
};
const simpan = (b: Baris) =>
  f.transform((d) => ({ ...d, _method: 'put' })).post(b.ca_url, {
    preserveScroll: true, preserveState: true, onSuccess: () => { sunting.value = null; },
  });
</script>

<template>
  <Head :title="judul" />

  <div class="space-y-4">
    <div class="fr-kisi fr-kisi-3-1">
      <section class="fr-kartu">
        <div class="fr-kartu-kepala">
          <h3>Frekuensi Masalah per Kategori</h3>
          <span class="fr-ket">klik kategori untuk menyaring</span>
        </div>
        <div class="fr-isi space-y-2.5">
          <button v-for="k in frekuensi" :key="k.kunci" type="button" class="block w-full text-left rounded-lg px-2 py-1 transition"
                  :class="s.kategori === k.kunci ? 'fr-pilihan' : 'hover:bg-stone-50'"
                  @click="pilihKategori(k.kunci)">
            <div class="flex items-baseline justify-between text-[12px]">
              <b>{{ k.nama }}</b>
              <span class="num fr-ket">
                {{ k.butir }} butir · {{ k.sesi }} sesi
                <b v-if="k.ulang" class="fr-teks-gawat">· {{ k.ulang }} berulang</b>
              </span>
            </div>
            <div class="fr-pita mt-1"><div :style="{ width: (k.butir / maks * 100) + '%', background: KATEGORI_WARNA[k.kunci] }"></div></div>
          </button>
          <p v-if="!frekuensi.length" class="fr-redup text-[12px]">Belum ada temuan pada periode ini.</p>
        </div>
      </section>

      <div class="fr-kartu">
        <div class="fr-saring" style="flex-direction: column; align-items: stretch">
          <label>
            <span>Periode</span>
            <select v-model="s.bulan" class="fr-pilih" @change="terapkan">
              <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
            </select>
          </label>
          <label>
            <span>Kategori</span>
            <select v-model="s.kategori" class="fr-pilih" @change="terapkan">
              <option value="">Semua</option>
              <option v-for="k in opsi.kategori" :key="k.kunci" :value="k.kunci">{{ k.nama }}</option>
            </select>
          </label>
          <label>
            <span>Status CA</span>
            <select v-model="s.status" class="fr-pilih" @change="terapkan">
              <option value="">Semua</option>
              <option v-for="o in opsi.status" :key="o">{{ o }}</option>
            </select>
          </label>
          <p class="fr-ket">🔁 = kategori yang sama pernah tercatat pada unit yang sama sebelumnya.</p>
        </div>
      </div>
    </div>

    <section class="fr-kartu overflow-hidden">
      <div class="fr-kartu-kepala">
        <h3>Temuan &amp; Tindak Lanjut</h3>
        <span class="fr-ket num">{{ daftar.length }} sesi bertemuan</span>
      </div>
      <div class="fr-gulir">
        <table class="fr-tabel">
          <thead>
            <tr><th>Tanggal</th><th>Unit &amp; operator</th><th>Temuan</th><th>Corrective action</th>
                <th>PIC</th><th>Deadline</th><th>Status</th></tr>
          </thead>
          <tbody>
            <template v-for="b in daftar" :key="b.id">
              <tr>
                <td class="num whitespace-nowrap"><Link :href="b.url" class="fr-tautan">{{ tgl(b.tanggal) }}</Link>
                  <span class="block fr-ket">Shift {{ b.shift ?? '–' }}</span></td>
                <td style="min-width: 9rem"><b>{{ b.unit }}</b><span class="block fr-ket">{{ b.operator }}</span></td>
                <td style="min-width: 16rem">
                  <div v-for="(t, i) in b.butir" :key="i" class="flex items-start gap-1.5 mb-1">
                    <span class="fr-kat" :style="{ background: KATEGORI_WARNA[t.kat] }" :title="namaKat(t.kat)">{{ namaKat(t.kat).split(' ')[0] }}</span>
                    <span>{{ t.teks }} <b v-if="t.ulang" class="fr-teks-gawat" title="berulang pada unit ini">🔁</b></span>
                  </div>
                </td>
                <td style="min-width: 14rem" class="text-stone-600">{{ b.corrective_action ?? '–' }}</td>
                <td class="whitespace-nowrap">{{ b.pic_ca ?? '–' }}</td>
                <td class="num whitespace-nowrap" :class="b.lewat ? 'fr-teks-gawat font-bold' : ''">
                  {{ tgl(b.deadline_ca) }}<span v-if="b.lewat" class="block text-[10px]">lewat tenggat</span>
                </td>
                <td class="whitespace-nowrap">
                  <span class="fr-lencana" :class="b.status_ca === 'Closed' ? 'fr-baik' : b.status_ca === 'In Progress' ? 'fr-ingat' : 'fr-gawat'">{{ b.status_ca }}</span>
                  <span v-if="b.selesai_ca" class="block fr-ket num">{{ tgl(b.selesai_ca) }}</span>
                  <button type="button" class="block fr-ket underline mt-1" @click="sunting === b.id ? (sunting = null) : buka(b)">
                    {{ sunting === b.id ? 'tutup' : 'perbarui' }}
                  </button>
                </td>
              </tr>
              <tr v-if="sunting === b.id">
                <td colspan="7" class="fr-sunting">
                  <form class="grid gap-2 sm:grid-cols-6 items-end" @submit.prevent="simpan(b)">
                    <div class="sm:col-span-2">
                      <label class="fr-label" :for="'ca-' + b.id">Corrective action</label>
                      <textarea :id="'ca-' + b.id" v-model="f.corrective_action" rows="2" maxlength="2000" class="fr-isian"></textarea>
                    </div>
                    <div><label class="fr-label" :for="'pic-' + b.id">PIC</label><input :id="'pic-' + b.id" v-model="f.pic_ca" class="fr-isian"></div>
                    <div><label class="fr-label" :for="'dl-' + b.id">Deadline</label><input :id="'dl-' + b.id" v-model="f.deadline_ca" type="date" class="fr-isian"></div>
                    <div>
                      <label class="fr-label" :for="'st-' + b.id">Status</label>
                      <select :id="'st-' + b.id" v-model="f.status_ca" class="fr-isian"><option v-for="o in opsi.status" :key="o">{{ o }}</option></select>
                    </div>
                    <div>
                      <label class="fr-label" :for="'sl-' + b.id">Tgl selesai</label>
                      <input :id="'sl-' + b.id" v-model="f.selesai_ca" type="date" class="fr-isian" :disabled="f.status_ca !== 'Closed'">
                    </div>
                    <p v-for="(g, k) in f.errors" :key="k" class="fr-galat sm:col-span-6">{{ g }}</p>
                    <div class="sm:col-span-6 flex gap-2">
                      <button type="submit" class="eq-btn-utama" style="flex:none" :disabled="f.processing">Simpan</button>
                      <button type="button" class="eq-btn-mini" @click="sunting = null">Batal</button>
                    </div>
                  </form>
                </td>
              </tr>
            </template>
            <tr v-if="!daftar.length">
              <td colspan="7"><p class="eq-kosong"><strong>Tidak ada temuan pada saringan ini.</strong></p></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
