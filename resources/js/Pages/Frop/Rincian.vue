<script setup lang="ts">
/**
 * Laporan satu sesi observasi — pengganti "Daily PTY Report" dan
 * "Dashboard Daily" berkas kerjanya.
 *
 * Urutannya mengikuti cara laporan itu dibahas bersama operator:
 * hasilnya dulu (CT, status, kesimpulan), lalu komponen mana yang
 * membuatnya lewat plan, lalu kondisi yang menjelaskannya, lalu apa
 * yang harus dilakukan — dan riwayat operator itu sendiri sebagai
 * pembanding, bukan baris di atasnya yang milik orang lain.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { KATEGORI_WARNA, type BarisCoaching, type BarisSesi, type Komponen, type Nilai,
         type RekapOperator, type TautanFrop } from './tipe';
import { bertanda, detik, persen, tgl } from './fmt';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import './frop.css';

type Sesi = BarisSesi & {
  gl_front: string | null; verified_by: string | null;
  kondisi_mesin: string | null; mode_kerja: string | null; material: string | null;
  metode_posisi: string | null; operating_condition: string | null; metode_loading: string | null;
  mto: string | null; tinggi_jenjang: number | null; lebar_front: number | null;
  kondisi_permukaan: string | null; boulder: boolean | null; sudut_pass: string | null; cuaca: string | null;
  spotting: number | null; loading: string | null; loading_lewat: boolean;
  n_passing: string | null; passing_lewat: boolean; bucket_heap: boolean | null;
  target_pty: number | null; aktual_pty: number | null; gap: number | null;
  corrective_action: string | null; pic_ca: string | null; deadline_ca: string | null; selesai_ca: string | null;
  catatan: string | null; sumber: string | null;
  status_sesi: Nilai; spot_tinggi: boolean;
  butir: Array<{ teks: string; kat: string; nama: string; ulang: boolean }>;
};

const props = defineProps<{
  judul: string;
  sesi: Sesi;
  komponen: Komponen[];
  riwayat: Array<{ id: number; url: string; ke: number; tanggal: string; unit: string; level: string;
                   ct: number | null; plan: number | null; selisih: number | null; on: boolean | null;
                   pty: number | null; banding: number | null; kini: boolean }>;
  rekap: RekapOperator;
  coaching: BarisCoaching[];
  acuan: { loading_maks: string; passing_maks: number; spot_tinggi: number };
  opsi: { status_ca: string[] };
  bolehHapus: boolean;
  tautan: TautanFrop & { ubah: string; hapus: string; ca: string; coaching: string; operator: string };
}>();

const ca = useForm({
  corrective_action: props.sesi.corrective_action ?? '',
  pic_ca: props.sesi.pic_ca ?? '',
  deadline_ca: props.sesi.deadline_ca ?? '',
  status_ca: props.sesi.status_ca,
  selesai_ca: props.sesi.selesai_ca ?? '',
});
const bukaCa = ref(false);
const hariIni = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
watch(() => ca.status_ca, (v) => { if (v === 'Closed' && !ca.selesai_ca) ca.selesai_ca = hariIni(); });
const simpanCa = () =>
  ca.transform((d) => ({ ...d, _method: 'put' })).post(props.tautan.ca, {
    preserveScroll: true, preserveState: true, onSuccess: () => { bukaCa.value = false; },
  });

const { dialog, tanya, batal, lanjut } = useDialog();
const hapus = async () => {
  if (await tanya({ judul: 'Hapus sesi observasi?', pesan: 'Sesi ' + props.sesi.operator + ' tanggal ' + tgl(props.sesi.tanggal)
                    + ' dihapus beserta temuannya. Tindakan ini tidak dapat dibatalkan.', labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(props.tautan.hapus);
  }
};

const ya = (v: boolean | null, benar = 'Ya', salah = 'Tidak') => (v === null ? '–' : v ? benar : salah);
const lebarPita = (k: Komponen) => Math.min(100, ((k.aktual ?? 0) / Math.max(k.plan ?? 1, k.aktual ?? 0, 0.01)) * 100);
const lebarPlan = (k: Komponen) => Math.min(100, ((k.plan ?? 0) / Math.max(k.plan ?? 1, k.aktual ?? 0, 0.01)) * 100);
</script>

<template>
  <Head :title="'Observasi ' + sesi.operator" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="space-y-4">
    <!-- Bilah tindakan -->
    <div class="flex flex-wrap items-center gap-2">
      <Link :href="tautan.index" class="eq-btn-mini">← Daftar sesi</Link>
      <span class="fr-lencana" :class="'fr-' + (sesi.status_sesi.nada ?? 'netral')">{{ sesi.status_sesi.teks }}</span>
      <span class="fr-lencana" :class="'fr-' + (sesi.on === null ? 'netral' : sesi.on ? 'baik' : 'gawat')">
        {{ sesi.on === null ? 'CT BELUM DINILAI' : sesi.on ? 'ON TARGET' : 'OVER TARGET' }}
      </span>
      <span class="ml-auto flex gap-2">
        <Link :href="tautan.coaching" class="eq-btn-mini">+ Catat coaching</Link>
        <Link :href="tautan.ubah" class="eq-btn-mini">Ubah</Link>
        <button v-if="bolehHapus" type="button" class="eq-btn-mini bahaya" @click="hapus">Hapus</button>
      </span>
    </div>

    <!-- Kesimpulan -->
    <section class="fr-kartu fr-isi" :style="{ borderLeft: '4px solid ' + (sesi.kesimpulan.nada === 'baik' ? '#16A34A' : sesi.kesimpulan.nada === 'gawat' ? '#DC2626' : sesi.kesimpulan.nada === 'ingat' ? '#D97706' : '#A8A29E') }">
      <div class="fr-ket uppercase font-bold tracking-wide">Kesimpulan hasil observasi</div>
      <p class="text-[14px] font-bold text-stone-800 mt-1">{{ sesi.kesimpulan.teks }}</p>
      <p class="text-[12px] mt-2">
        <span class="fr-ket uppercase font-bold tracking-wide">Rekomendasi coaching · </span>
        <b :class="sesi.rekomendasi.kode === 'pertahankan' ? 'fr-teks-baik' : 'fr-teks-gawat'">{{ sesi.rekomendasi.teks }}</b>
      </p>
    </section>

    <div class="fr-kisi fr-kisi-2">
      <!-- Komponen vs plan -->
      <section class="fr-kartu">
        <div class="fr-kartu-kepala">
          <h3>Komponen CT vs Plan</h3>
          <span class="fr-ket num">plan {{ detik(sesi.plan, 1) }} dtk · {{ sesi.level_label }}</span>
        </div>
        <div class="fr-isi space-y-3">
          <div v-for="k in komponen" :key="k.kunci">
            <div class="flex items-baseline justify-between text-[12px]">
              <span><b>{{ k.label }}</b> <span class="fr-ket">{{ k.ket }}</span></span>
              <span class="num">
                <b :class="k.ok === false ? 'fr-teks-gawat' : 'fr-teks-baik'">{{ detik(k.aktual) }}</b>
                <span class="fr-redup"> / {{ detik(k.plan, 1) }}</span>
                <span class="ml-1 text-[11px]" :class="k.ok === false ? 'fr-teks-gawat' : 'fr-redup'">{{ bertanda(k.selisih) }}</span>
              </span>
            </div>
            <div class="relative mt-1 fr-pita">
              <div :style="{ width: lebarPita(k) + '%', background: k.ok === false ? '#DC2626' : '#16A34A' }"></div>
              <span class="absolute top-[-2px] h-[12px] w-[2px] bg-stone-700" :style="{ left: 'calc(' + lebarPlan(k) + '% - 1px)' }"
                    title="plan"></span>
            </div>
            <div class="fr-ket num mt-0.5">{{ persen(k.porsi) }} dari CT</div>
          </div>

          <div class="grid grid-cols-3 gap-2 border-t border-stone-100 pt-3 text-center">
            <div><div class="fr-ket">Aktual CT</div><div class="num text-[18px] font-extrabold">{{ detik(sesi.ct) }}</div></div>
            <div><div class="fr-ket">Plan CT</div><div class="num text-[18px] font-extrabold text-stone-400">{{ detik(sesi.plan, 1) }}</div></div>
            <div><div class="fr-ket">Selisih</div>
              <div class="num text-[18px] font-extrabold" :class="sesi.on ? 'fr-teks-baik' : 'fr-teks-gawat'">{{ bertanda(sesi.selisih) }}</div></div>
          </div>
          <p class="fr-ket">
            Spotting {{ detik(sesi.spotting) }} dtk — tidak termasuk CT.
            <b v-if="sesi.spot_tinggi" class="fr-teks-ingat">Lebih dari {{ acuan.spot_tinggi }} dtk: cek hauler rotation.</b>
          </p>
        </div>
      </section>

      <!-- Loading & PTY -->
      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Loading &amp; Produktivitas</h3><span class="fr-ket">{{ sesi.jam ?? '' }}</span></div>
        <div class="fr-isi">
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl p-2" :class="sesi.loading_lewat ? 'bg-amber-50' : 'bg-stone-50'">
              <div class="fr-ket">Loading time</div>
              <div class="num text-[18px] font-extrabold" :class="sesi.loading_lewat ? 'fr-teks-ingat' : ''">{{ sesi.loading ?? '–' }}</div>
              <div class="fr-ket">std ≤ {{ acuan.loading_maks }}</div>
            </div>
            <div class="rounded-xl p-2" :class="sesi.passing_lewat ? 'bg-amber-50' : 'bg-stone-50'">
              <div class="fr-ket">N passing</div>
              <div class="num text-[18px] font-extrabold" :class="sesi.passing_lewat ? 'fr-teks-ingat' : ''">{{ sesi.n_passing ?? '–' }}</div>
              <div class="fr-ket">std ≤ {{ acuan.passing_maks }}</div>
            </div>
            <div class="rounded-xl p-2" :class="sesi.bucket_heap === false ? 'bg-amber-50' : 'bg-stone-50'">
              <div class="fr-ket">Bucket heap</div>
              <div class="text-[18px] font-extrabold">{{ sesi.bucket_heap === null ? '–' : sesi.bucket_heap ? '✅' : '❌' }}</div>
              <div class="fr-ket">{{ ya(sesi.bucket_heap, 'heap', 'tidak heap') }}</div>
            </div>
          </div>

          <div class="mt-4">
            <div class="flex justify-between text-[12px]">
              <span><b>PTY</b> <span class="fr-ket num">{{ sesi.aktual_pty ?? '–' }} / {{ sesi.target_pty ?? '–' }} BCM</span></span>
              <b class="num" :class="sesi.pty === null ? 'fr-redup' : sesi.pty >= 0.9 ? 'fr-teks-baik' : 'fr-teks-ingat'">{{ persen(sesi.pty) }}</b>
            </div>
            <div class="fr-pita mt-1">
              <div :style="{ width: Math.min(100, (sesi.pty ?? 0) * 100) + '%', background: (sesi.pty ?? 0) >= 0.9 ? '#16A34A' : '#D97706' }"></div>
            </div>
            <div class="fr-ket num mt-1">Gap {{ sesi.gap === null ? '–' : (sesi.gap > 0 ? '+' : '') + sesi.gap + ' BCM' }} · tercapai bila ≥ 90%</div>
          </div>
        </div>
      </section>
    </div>

    <div class="fr-kisi fr-kisi-2">
      <!-- Kondisi -->
      <section class="fr-kartu">
        <div class="fr-kartu-kepala"><h3>Identitas &amp; Kondisi</h3></div>
        <div class="fr-isi">
          <dl class="fr-dl">
            <dt>Tanggal</dt><dd>{{ tgl(sesi.tanggal) }} · shift {{ sesi.shift ?? '–' }}</dd>
            <dt>Unit</dt><dd>{{ sesi.unit }}</dd>
            <dt>GL Front</dt><dd>{{ sesi.gl_front ?? '–' }}</dd>
            <dt>Observer</dt><dd>{{ sesi.observer ?? '–' }}</dd>
            <dt>Verified by</dt><dd>{{ sesi.verified_by ?? '–' }}</dd>
            <dt>Material</dt><dd>{{ sesi.level_label }}<template v-if="sesi.material"> — {{ sesi.material }}</template></dd>
            <dt>Kondisi mesin</dt><dd>{{ sesi.kondisi_mesin ?? '–' }}</dd>
            <dt>Mode kerja</dt><dd>{{ sesi.mode_kerja ?? '–' }}</dd>
            <dt>Metode posisi</dt><dd>{{ sesi.metode_posisi ?? '–' }}</dd>
            <dt>Metode loading</dt><dd>{{ sesi.metode_loading ?? '–' }}</dd>
            <dt>Operating condition</dt><dd>{{ sesi.operating_condition ?? '–' }}</dd>
            <dt>MTO</dt><dd>{{ sesi.mto ?? '–' }}</dd>
            <dt>Tinggi jenjang</dt><dd class="num">{{ sesi.tinggi_jenjang === null ? '–' : detik(sesi.tinggi_jenjang) + ' m' }}</dd>
            <dt>Lebar front</dt><dd class="num">{{ sesi.lebar_front === null ? '–' : detik(sesi.lebar_front) + ' m' }}</dd>
            <dt>Permukaan front</dt><dd>{{ sesi.kondisi_permukaan ?? '–' }}</dd>
            <dt>Boulder</dt><dd>{{ ya(sesi.boulder, 'Ada', 'Tidak ada') }}</dd>
            <dt>Sudut pass pertama</dt><dd>{{ sesi.sudut_pass ?? '–' }}</dd>
            <dt>Cuaca</dt><dd>{{ sesi.cuaca ?? '–' }}</dd>
          </dl>
          <p v-if="sesi.catatan" class="text-[12px] mt-3 text-stone-600">{{ sesi.catatan }}</p>
          <p v-if="sesi.sumber" class="fr-ket mt-2">Diimpor dari {{ sesi.sumber }}</p>
        </div>
      </section>

      <!-- Temuan & CA -->
      <section class="fr-kartu">
        <div class="fr-kartu-kepala">
          <h3>Temuan &amp; Corrective Action</h3>
          <span class="fr-lencana" :class="sesi.status_ca === 'Closed' ? 'fr-baik' : sesi.status_ca === 'In Progress' ? 'fr-ingat' : 'fr-gawat'">
            {{ sesi.status_ca }}
          </span>
        </div>
        <div class="fr-isi space-y-3">
          <ul v-if="sesi.butir.length" class="space-y-1.5">
            <li v-for="(b, i) in sesi.butir" :key="i" class="flex items-start gap-2 text-[12.5px]">
              <span class="fr-kat mt-0.5" :style="{ background: KATEGORI_WARNA[b.kat] }">{{ b.nama }}</span>
              <span>{{ b.teks }}
                <b v-if="b.ulang" class="fr-teks-gawat text-[11px]" title="Kategori yang sama pernah tercatat pada unit ini">🔁 berulang</b>
              </span>
            </li>
          </ul>
          <p v-else class="fr-redup text-[12px]">Tidak ada temuan dicatat.</p>

          <div v-if="!bukaCa">
            <p class="text-[12.5px]"><span class="fr-ket uppercase font-bold">CA · </span>{{ sesi.corrective_action ?? '–' }}</p>
            <p class="fr-ket mt-1">
              PIC {{ sesi.pic_ca ?? sesi.observer ?? '–' }} · deadline {{ tgl(sesi.deadline_ca) }}
              <template v-if="sesi.selesai_ca"> · selesai {{ tgl(sesi.selesai_ca) }}</template>
            </p>
            <button type="button" class="eq-btn-mini mt-2" @click="bukaCa = true">Perbarui tindak lanjut</button>
          </div>

          <form v-else class="grid gap-2" @submit.prevent="simpanCa">
            <label class="fr-label" for="ca-teks">Corrective action</label>
            <textarea id="ca-teks" v-model="ca.corrective_action" rows="3" maxlength="2000" class="fr-isian"></textarea>
            <p v-if="ca.errors.corrective_action" class="fr-galat">{{ ca.errors.corrective_action }}</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <div><label class="fr-label" for="ca-pic">PIC</label><input id="ca-pic" v-model="ca.pic_ca" class="fr-isian"></div>
              <div><label class="fr-label" for="ca-dl">Deadline</label><input id="ca-dl" v-model="ca.deadline_ca" type="date" class="fr-isian"></div>
              <div>
                <label class="fr-label" for="ca-st">Status</label>
                <select id="ca-st" v-model="ca.status_ca" class="fr-isian"><option v-for="o in opsi.status_ca" :key="o">{{ o }}</option></select>
              </div>
              <div>
                <label class="fr-label" for="ca-sl">Tgl selesai</label>
                <input id="ca-sl" v-model="ca.selesai_ca" type="date" class="fr-isian" :disabled="ca.status_ca !== 'Closed'">
              </div>
            </div>
            <p v-if="ca.errors.selesai_ca" class="fr-galat">{{ ca.errors.selesai_ca }}</p>
            <div class="flex gap-2">
              <button type="submit" class="eq-btn-utama" style="flex:none" :disabled="ca.processing">Simpan</button>
              <button type="button" class="eq-btn-mini" @click="bukaCa = false">Batal</button>
            </div>
          </form>
        </div>
      </section>
    </div>

    <!-- Riwayat operator -->
    <section class="fr-kartu overflow-hidden">
      <div class="fr-kartu-kepala">
        <h3>Riwayat {{ sesi.operator }}</h3>
        <span class="fr-ket">
          {{ rekap.total }} sesi · {{ rekap.on_target }} on target ({{ persen(rekap.konsistensi) }}) ·
          <span class="fr-lencana" :class="'fr-' + (rekap.performer.nada ?? 'netral')">{{ rekap.performer.teks }}</span>
          · <Link :href="tautan.operator" class="fr-tautan">lihat di daftar</Link>
        </span>
      </div>
      <div class="fr-isi pb-0">
        <p class="text-[12px] text-stone-600"><b>Catatan coaching:</b> {{ rekap.catatan }}</p>
      </div>
      <div class="fr-gulir mt-3">
        <table class="fr-tabel">
          <thead>
            <tr>
              <th>Sesi</th><th>Tanggal</th><th>Unit</th><th>Material</th>
              <th class="kanan">CT</th><th class="kanan">Plan</th><th class="kanan">Selisih</th>
              <th class="kanan">vs sesi sebelumnya</th><th class="kanan">PTY</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in riwayat" :key="h.id" :class="{ kini: h.kini }">
              <td class="num">ke-{{ h.ke }}</td>
              <td class="num whitespace-nowrap"><Link :href="h.url" class="fr-tautan">{{ tgl(h.tanggal) }}</Link></td>
              <td>{{ h.unit }}</td>
              <td>{{ h.level }}</td>
              <td class="kanan num"><b>{{ detik(h.ct) }}</b></td>
              <td class="kanan num fr-redup">{{ detik(h.plan, 1) }}</td>
              <td class="kanan num" :class="h.on === null ? '' : h.on ? 'fr-teks-baik' : 'fr-teks-gawat'">{{ bertanda(h.selisih) }}</td>
              <td class="kanan num" :class="h.banding === null ? 'fr-redup' : h.banding < 0 ? 'fr-teks-baik' : h.banding > 0 ? 'fr-teks-gawat' : ''">
                {{ h.banding === null ? '–' : bertanda(h.banding) + (h.banding < 0 ? ' ↓' : h.banding > 0 ? ' ↑' : '') }}
              </td>
              <td class="kanan num">{{ persen(h.pty) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Coaching -->
    <section class="fr-kartu">
      <div class="fr-kartu-kepala">
        <h3>Coaching {{ sesi.operator }}</h3>
        <Link :href="tautan.coaching" class="eq-btn-mini">+ Catat coaching</Link>
      </div>
      <div class="fr-isi">
        <ul v-if="coaching.length" class="space-y-2">
          <li v-for="c in coaching" :key="c.id" class="rounded-xl border border-stone-100 p-3 text-[12px]">
            <div class="flex flex-wrap items-center gap-2">
              <b class="num">{{ tgl(c.tanggal) }}</b>
              <span class="fr-ket">oleh {{ c.coach ?? '–' }}</span>
              <span class="fr-lencana ml-auto" :class="c.status === 'Closed' ? 'fr-baik' : 'fr-ingat'">{{ c.status }}</span>
            </div>
            <p class="mt-1">{{ c.materi }}</p>
            <p v-if="c.follow_up" class="fr-ket mt-1">Follow up: {{ c.follow_up }}</p>
          </li>
        </ul>
        <p v-else class="fr-redup text-[12px]">Belum ada coaching tercatat untuk operator ini.</p>
      </div>
    </section>
  </div>
</template>
