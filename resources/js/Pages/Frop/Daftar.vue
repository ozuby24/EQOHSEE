<script setup lang="ts">
/**
 * Daftar sesi observasi operator loader.
 *
 * Tiap baris membawa komponen cycle time, CT terhadap plan-nya, dan
 * kesimpulannya — bukan hanya nama dan tanggal. Yang membuka daftar ini
 * ingin tahu siapa yang lewat plan dan mengapa, dan pertanyaan itu
 * tidak terjawab oleh daftar nama.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import UbinAngka from '../../Components/UbinAngka.vue';
import IkonStat from '../../Components/IkonStat.vue';
import { KATEGORI_WARNA, type BarisSesi, type OpsiBulan, type TautanFrop } from './tipe';
import { bertanda, detik, persen, tgl } from './fmt';
import './frop.css';

const props = defineProps<{
  judul: string;
  saring: { bulan: string; operator: string; unit: string; status: string; cari: string };
  opsi: { bulan: OpsiBulan[]; operator: string[]; unit: string[] };
  ringkas: { sesi: number; dinilai: number; on: number; rata_sel: number | null;
             pty: number | null; open: number; operator: number };
  daftar: BarisSesi[];
  tautan: TautanFrop;
}>();

const s = reactive({ ...props.saring });

let tunda: ReturnType<typeof setTimeout> | undefined;
const terapkan = (segera = true) => {
  clearTimeout(tunda);
  tunda = setTimeout(() => router.get(props.tautan.index, { ...s }, { preserveState: true, preserveScroll: true, replace: true }),
                     segera ? 0 : 350);
};

const KAT: Record<string, string> = {
  unit: 'Unit', jalan: 'Jalan', hauler: 'Hauler', material: 'Material', metode: 'Metode', front: 'Front', lain: 'Lain',
};
</script>

<template>
  <Head :title="judul" />

  <div class="space-y-4">
    <div class="fr-ubin-baris">
      <UbinAngka :angka="ringkas.sesi" label="Sesi observasi">
        <template #ikon><IkonStat nama="materi" /></template>
      </UbinAngka>
      <UbinAngka :angka="ringkas.on + ' / ' + ringkas.dinilai" label="Sesi ON TARGET"
                 :dari="ringkas.dinilai || null" :nilai="ringkas.on"
                 :nada="ringkas.dinilai && ringkas.on / ringkas.dinilai >= 0.8 ? 'baik' : 'ingat'">
        <template #ikon><IkonStat nama="tuntas" /></template>
      </UbinAngka>
      <UbinAngka :angka="bertanda(ringkas.rata_sel) + ' dtk'" label="Rata-rata selisih vs plan"
                 :nada="ringkas.rata_sel === null ? 'netral' : ringkas.rata_sel <= 0 ? 'baik' : 'gawat'"
                 catatan="negatif = lebih cepat dari plan">
        <template #ikon><IkonStat nama="laju" /></template>
      </UbinAngka>
      <UbinAngka :angka="persen(ringkas.pty)" label="Rata-rata pencapaian PTY"
                 :nada="ringkas.pty === null ? 'netral' : ringkas.pty >= 0.9 ? 'baik' : 'ingat'">
        <template #ikon><IkonStat nama="penilaian" /></template>
      </UbinAngka>
      <UbinAngka :angka="ringkas.operator" label="Operator">
        <template #ikon><IkonStat nama="kursus" /></template>
      </UbinAngka>
      <UbinAngka :angka="ringkas.open" label="Temuan belum Closed" :nada="ringkas.open ? 'ingat' : 'baik'">
        <template #ikon><IkonStat nama="hazard" /></template>
      </UbinAngka>
    </div>

    <div class="fr-kartu">
      <div class="fr-saring">
        <label>
          <span>Periode</span>
          <select v-model="s.bulan" class="fr-pilih" @change="terapkan()">
            <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
          </select>
        </label>
        <label>
          <span>Operator</span>
          <select v-model="s.operator" class="fr-pilih" @change="terapkan()">
            <option value="">Semua</option>
            <option v-for="o in opsi.operator" :key="o" :value="o">{{ o }}</option>
          </select>
        </label>
        <label>
          <span>Unit</span>
          <select v-model="s.unit" class="fr-pilih" @change="terapkan()">
            <option value="">Semua</option>
            <option v-for="u in opsi.unit" :key="u" :value="u">{{ u }}</option>
          </select>
        </label>
        <label>
          <span>Status CT</span>
          <select v-model="s.status" class="fr-pilih" @change="terapkan()">
            <option value="">Semua</option>
            <option value="on">ON TARGET</option>
            <option value="over">OVER TARGET</option>
          </select>
        </label>
        <label class="grow" style="min-width: 12rem">
          <span>Cari</span>
          <input v-model="s.cari" class="fr-isian" placeholder="operator, unit, temuan…" @input="terapkan(false)">
        </label>
        <Link :href="tautan.buat" class="eq-btn-utama" style="flex:none">+ Sesi Observasi</Link>
      </div>
    </div>

    <div class="fr-kartu overflow-hidden">
      <div class="fr-kartu-kepala">
        <h3>Sesi Observasi</h3>
        <span class="fr-ket num">{{ daftar.length }} sesi · CT = Digging + SWL + Dump + SWE (spotting tidak termasuk)</span>
      </div>

      <div class="fr-gulir">
        <table class="fr-tabel">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Operator &amp; Unit</th>
              <th>Material</th>
              <th class="kanan">Dig</th>
              <th class="kanan">SWL</th>
              <th class="kanan">Dump</th>
              <th class="kanan">SWE</th>
              <th class="kanan">CT</th>
              <th class="kanan">Plan</th>
              <th class="kanan">Selisih</th>
              <th class="kanan">PTY</th>
              <th>Kesimpulan</th>
              <th>Temuan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in daftar" :key="r.id">
              <td class="num whitespace-nowrap">
                {{ tgl(r.tanggal) }}
                <span class="block fr-ket">Shift {{ r.shift ?? '–' }}<template v-if="r.jam"> · {{ r.jam }}</template></span>
              </td>
              <td style="min-width: 10rem">
                <Link :href="r.url" class="fr-tautan">{{ r.operator }}</Link>
                <span class="block fr-ket">{{ r.unit }}</span>
              </td>
              <td class="whitespace-nowrap">{{ r.level_label }}</td>
              <td class="kanan num">{{ detik(r.digging) }}</td>
              <td class="kanan num">{{ detik(r.swl) }}</td>
              <td class="kanan num">{{ detik(r.dump) }}</td>
              <td class="kanan num">{{ detik(r.swe) }}</td>
              <td class="kanan num"><b>{{ detik(r.ct) }}</b></td>
              <td class="kanan num fr-redup">{{ detik(r.plan) }}</td>
              <td class="kanan num">
                <span :class="r.on === null ? '' : r.on ? 'fr-teks-baik' : 'fr-teks-gawat'" style="font-weight:800">
                  {{ bertanda(r.selisih) }}
                </span>
              </td>
              <td class="kanan num" :class="r.pty === null ? 'fr-redup' : r.pty >= 0.9 ? 'fr-teks-baik' : 'fr-teks-ingat'">
                {{ persen(r.pty) }}
              </td>
              <td style="min-width: 13rem">
                <span class="fr-lencana" :class="'fr-' + (r.on === null ? 'netral' : r.on ? 'baik' : 'gawat')">
                  {{ r.on === null ? 'BELUM DINILAI' : r.on ? 'ON TARGET' : 'OVER TARGET' }}
                </span>
                <span class="block fr-ket mt-1" style="color:#57534E">{{ r.kesimpulan.teks }}</span>
              </td>
              <td style="min-width: 9rem">
                <span v-for="k in r.kategori" :key="k" class="fr-kat mr-1 mb-1"
                      :style="{ background: KATEGORI_WARNA[k] }">{{ KAT[k] }}</span>
                <span v-if="r.temuan" class="block fr-ket">CA: {{ r.status_ca }}</span>
                <span v-else class="fr-redup">–</span>
              </td>
            </tr>

            <tr v-if="!daftar.length">
              <td colspan="13">
                <p class="eq-kosong">
                  <strong>Belum ada sesi observasi pada saringan ini.</strong>
                  <span class="block halus">Catat sesi baru, atau impor berkas kerja observasi yang sudah ada.</span>
                  <Link :href="tautan.buat" class="eq-btn-utama">+ Sesi Observasi</Link>
                  <Link :href="tautan.impor" class="eq-btn-mini ml-2">Impor berkas .xlsx</Link>
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
