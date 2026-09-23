<script setup lang="ts">
/**
 * KPI bulanan program FROP — pengganti sheet "KPI Report".
 *
 * Pekannya sama dengan berkas asalnya: 1–7, 8–14, 15–21, 22–akhir.
 * Tiap persentase dibagi dengan sesi yang benar-benar dapat dinilai,
 * bukan dengan seluruh baris lembar kerja.
 */
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { OpsiBulan, TautanFrop } from './tipe';
import { bertanda, detik, persen, tgl } from './fmt';
import './frop.css';

type Angka = { sesi: number; on_target: number | null; rata_ct: number | null; rata_sel: number | null;
               pty: number | null; ca_closed: number | null; berulang: number | null; operator: number };

const props = defineProps<{
  judul: string;
  saring: { bulan: string };
  opsi: { bulan: OpsiBulan[] };
  pekan: Array<[string, string]>;
  nilai: Angka[];
  total: Angka;
  baris: Array<{ kunci: keyof Angka; label: string; bentuk: 'angka' | 'persen' | 'detik';
                 target: number | null; target_teks: string; status: boolean | null }>;
  level: Array<{ kunci: string; label: string; plan: number; sesi: number; on: number | null; rata_ct: number | null }>;
  tautan: TautanFrop;
}>();

const bulan = ref(props.saring.bulan);
const ubah = () => router.get(props.tautan.kpi, { bulan: bulan.value }, { preserveState: true, replace: true });

const tulis = (v: number | null, bentuk: string) =>
  v === null ? '–' : bentuk === 'persen' ? persen(v) : bentuk === 'detik' ? detik(v) : String(v);
const tulisSel = (k: string, v: number | null, bentuk: string) => (k === 'rata_sel' ? bertanda(v) : tulis(v, bentuk));
const tglPendek = (iso: string) => tgl(iso).replace(/ \d{4}$/, '');
</script>

<template>
  <Head :title="judul" />

  <div class="space-y-4">
    <div class="fr-kartu">
      <div class="fr-saring">
        <label>
          <span>Bulan</span>
          <select v-model="bulan" class="fr-pilih" @change="ubah">
            <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
          </select>
        </label>
      </div>
    </div>

    <section class="fr-kartu overflow-hidden">
      <div class="fr-kartu-kepala"><h3>KPI per Pekan</h3><span class="fr-ket">target dari KPI Report program</span></div>
      <div class="fr-gulir">
        <table class="fr-tabel">
          <thead>
            <tr>
              <th>KPI</th>
              <th>Target</th>
              <th v-for="(p, i) in pekan" :key="i" class="kanan">
                Pekan {{ i + 1 }}<span class="block font-normal normal-case tracking-normal">{{ tglPendek(p[0]) }}–{{ tglPendek(p[1]) }}</span>
              </th>
              <th class="kanan">Bulan</th>
              <th class="tengah">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in baris" :key="b.kunci">
              <td style="min-width: 14rem"><b>{{ b.label }}</b></td>
              <td class="fr-ket whitespace-nowrap">{{ b.target_teks }}</td>
              <td v-for="(n, i) in nilai" :key="i" class="kanan num"
                  :class="n.sesi === 0 ? 'fr-redup' : ''">{{ n.sesi === 0 && b.kunci !== 'sesi' ? '–' : tulisSel(b.kunci, n[b.kunci] as number | null, b.bentuk) }}</td>
              <td class="kanan num"><b>{{ tulisSel(b.kunci, total[b.kunci] as number | null, b.bentuk) }}</b></td>
              <td class="tengah">
                <span v-if="b.status === null" class="fr-lencana fr-netral">–</span>
                <span v-else class="fr-lencana" :class="b.status ? 'fr-baik' : 'fr-gawat'">{{ b.status ? 'TERCAPAI' : 'BELUM' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="fr-kartu">
      <div class="fr-kartu-kepala">
        <h3>Per Jenis Material</h3>
        <span class="fr-ket">% on target yang tinggi di bulan yang hampir seluruhnya Easy tidak sama artinya dengan di bulan Severe</span>
      </div>
      <div class="fr-isi grid gap-3 sm:grid-cols-3">
        <div v-for="l in level" :key="l.kunci" class="rounded-xl border border-stone-100 p-3">
          <div class="flex items-baseline justify-between">
            <b class="text-[13px]">{{ l.label }}</b><span class="fr-ket num">plan {{ l.plan }} dtk</span>
          </div>
          <div class="num text-[22px] font-extrabold mt-1"
               :class="l.on === null ? 'fr-redup' : l.on >= 0.8 ? 'fr-teks-baik' : 'fr-teks-gawat'">{{ persen(l.on) }}</div>
          <div class="fr-ket num">{{ l.sesi }} sesi · rata CT {{ detik(l.rata_ct) }} dtk</div>
        </div>
      </div>
    </section>
  </div>
</template>
