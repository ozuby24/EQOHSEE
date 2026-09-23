<script setup lang="ts">
/**
 * Performa per operator — pengganti sheet "Operator Performance".
 *
 * Peringkat menurut konsistensi on-target lalu rata-rata SELISIH
 * terhadap plan, bukan rata-rata CT mentah: plan berbeda per material,
 * dan operator yang lebih sering ditempatkan di material severe akan
 * selalu tampak lebih lambat bila CT-nya dibandingkan langsung.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { OpsiBulan, RekapOperator, TautanFrop } from './tipe';
import { bertanda, detik, persen, tgl } from './fmt';
import './frop.css';

type Baris = RekapOperator & {
  peringkat: number; operator: string; unit: string; terakhir: string; rata_sel: number | null;
  url: string; sesi_url: string; coaching: string;
};

const props = defineProps<{
  judul: string;
  saring: { bulan: string };
  opsi: { bulan: OpsiBulan[] };
  daftar: Baris[];
  ambang: { top: number; average: number; spot: number };
  tautan: TautanFrop;
}>();

const bulan = ref(props.saring.bulan);
const ubah = () => router.get(props.tautan.operatorRekap, { bulan: bulan.value }, { preserveState: true, replace: true });
</script>

<template>
  <Head :title="judul" />

  <div class="space-y-4">
    <div class="fr-kartu">
      <div class="fr-saring">
        <label>
          <span>Periode</span>
          <select v-model="bulan" class="fr-pilih" @change="ubah">
            <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
          </select>
        </label>
        <p class="fr-ket grow">
          Top Performer: ≥ {{ persen(ambang.top) }} sesi on target dan PTY ≥ 80% · Perlu Coaching: &lt; {{ persen(ambang.average) }}
          on target atau PTY &lt; 60% · selain itu Average. Tren = CT sesi terakhir − CT sesi pertama.
        </p>
      </div>
    </div>

    <section class="fr-kartu overflow-hidden">
      <div class="fr-kartu-kepala">
        <h3>Peringkat Operator</h3>
        <span class="fr-ket num">{{ daftar.length }} operator</span>
      </div>
      <div class="fr-gulir">
        <table class="fr-tabel">
          <thead>
            <tr>
              <th class="tengah">#</th>
              <th>Operator</th>
              <th class="kanan">Sesi</th>
              <th class="kanan">On target</th>
              <th class="kanan">Rata selisih</th>
              <th class="kanan">Rata CT</th>
              <th class="kanan">Terbaik / terburuk</th>
              <th class="kanan">Spot</th>
              <th class="kanan">Dig</th>
              <th class="kanan">PTY</th>
              <th class="kanan">Heap</th>
              <th>Tren</th>
              <th>Performer &amp; catatan coaching</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in daftar" :key="o.operator">
              <td class="tengah num"><b>{{ o.peringkat }}</b></td>
              <td style="min-width: 10rem">
                <Link :href="o.url" class="fr-tautan">{{ o.operator }}</Link>
                <span class="block fr-ket">{{ o.unit }} · terakhir {{ tgl(o.terakhir) }}</span>
              </td>
              <td class="kanan num">{{ o.total }}</td>
              <td class="kanan num">
                <b :class="(o.konsistensi ?? 0) >= ambang.top ? 'fr-teks-baik' : (o.konsistensi ?? 0) >= ambang.average ? 'fr-teks-ingat' : 'fr-teks-gawat'">
                  {{ persen(o.konsistensi) }}
                </b>
                <span class="block fr-ket">{{ o.on_target }}/{{ o.total }}</span>
              </td>
              <td class="kanan num" :class="o.rata_sel === null ? '' : o.rata_sel <= 0 ? 'fr-teks-baik' : 'fr-teks-gawat'">
                {{ bertanda(o.rata_sel) }}
              </td>
              <td class="kanan num">{{ detik(o.rata_ct) }}</td>
              <td class="kanan num whitespace-nowrap">{{ detik(o.terbaik) }} / {{ detik(o.terburuk) }}</td>
              <td class="kanan num" :class="(o.rata_spot ?? 0) > ambang.spot ? 'fr-teks-ingat' : ''">{{ detik(o.rata_spot, 1) }}</td>
              <td class="kanan num">{{ detik(o.rata_dig, 1) }}</td>
              <td class="kanan num" :class="o.rata_pty === null ? 'fr-redup' : o.rata_pty >= 0.9 ? 'fr-teks-baik' : 'fr-teks-ingat'">
                {{ persen(o.rata_pty) }}
              </td>
              <td class="kanan num">{{ persen(o.heap) }}</td>
              <td class="whitespace-nowrap num"
                  :class="o.arah === 'membaik' ? 'fr-teks-baik' : o.arah === 'menurun' ? 'fr-teks-gawat' : 'fr-redup'">
                <template v-if="o.arah">{{ o.arah === 'membaik' ? '↓' : o.arah === 'menurun' ? '↑' : '→' }} {{ bertanda(o.tren) }}
                  <span class="block fr-ket">{{ o.arah }}</span></template>
                <template v-else>– <span class="block fr-ket">1 sesi</span></template>
              </td>
              <td style="min-width: 16rem">
                <span class="fr-lencana" :class="'fr-' + (o.performer.nada ?? 'netral')">{{ o.performer.teks }}</span>
                <span class="block text-[11.5px] text-stone-600 mt-1">{{ o.catatan }}</span>
                <Link :href="o.coaching" class="fr-ket underline">coaching log</Link>
              </td>
            </tr>
            <tr v-if="!daftar.length">
              <td colspan="13"><p class="eq-kosong"><strong>Belum ada sesi pada periode ini.</strong></p></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
