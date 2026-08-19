<script setup lang="ts">
/**
 * PTPKKP — Program Improvement.
 *
 * Tiga bentuk perubahan pada satu halaman: menambah program, memperbarui
 * status/progres, dan menghapus. Ketiganya memakai rute yang sudah ada
 * tanpa perubahan bentuk kiriman, sehingga versi Blade-nya tetap jalan
 * selama masa peralihan.
 *
 * Baris status disunting di tempat dengan salinan lokal. Mengikat v-model
 * langsung ke prop membuat angkanya berubah di layar begitu diketik,
 * padahal belum dikirim ke server — dan setelah gagal simpan, layar
 * menunjukkan angka yang tidak tersimpan di mana pun.
 */
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { BarisProgram, BarisSunting, HalamanProgram } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanProgram>();

/* ── tambah ── */

const baru = reactive({ param: '', durasi: '', sasaran: '', target: '', opsi: '' });
const menambah = ref(false);

function tambah() {
  menambah.value = true;

  router.post(`/tpkkp/program?tahun=${props.tahun}`, { ...baru }, {
    preserveScroll: true,
    onSuccess: () => Object.assign(baru, { param: '', durasi: '', sasaran: '', target: '', opsi: '' }),
    onFinish:  () => { menambah.value = false; },
  });
}

/* ── sunting baris ── */

/**
 * Salinan lokal per baris, disiapkan di luar render.
 *
 * Membuatnya di dalam template — memanggil fungsi yang menambah kunci saat
 * baris digambar — berarti mengubah keadaan reaktif selagi render, dan
 * Vue menggambar ulang karenanya.
 */
const sunting = ref<Record<string, BarisSunting>>({});

function segarkan() {
  const baru: Record<string, BarisSunting> = {};

  for (const r of props.program) {
    if (r.id) baru[r.id] = { status: r.status, progress: r.progress };
  }

  sunting.value = baru;
}

segarkan();
watch(() => props.program, segarkan, { deep: true });

function berubah(r: BarisProgram) {
  const s = r.id ? sunting.value[r.id] : null;

  return !!s && (s.status !== r.status || Number(s.progress) !== r.progress);
}

function simpanBaris(id: string) {
  const s = sunting.value[id];
  if (!s) return;

  router.put(`/tpkkp/program/${id}/status?tahun=${props.tahun}`,
    { status: s.status, progress: Number(s.progress) },
    { preserveScroll: true });
}

async function hapus(id: string) {
  if (!await tanya('Hapus program ini?')) return;

  router.delete(`/tpkkp/program/${id}?tahun=${props.tahun}`, { preserveScroll: true });
}

const angka = (n: number) => (n >= 0 ? '+' : '') + n.toFixed(3);
</script>

<template>
  <Head title="PTPKKP — Program Improvement" />

  <div class="max-w-5xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Parameter dengan selisih target terbesar</h3>

      <p v-if="!saran.length" class="text-[12.5px] text-stone-400">
        Belum ada nilai yang bisa dibandingkan dengan target.
      </p>

      <div v-else class="space-y-1.5">
        <div v-for="s in saran" :key="s.kode" class="flex justify-between text-[12px] gap-3">
          <span class="text-stone-600"><b class="num">{{ s.kode }}</b> · {{ s.nama }}</span>
          <span class="num font-bold whitespace-nowrap"
                :class="s.gap < 0 ? 'text-red-600' : 'text-cam-lime-deep'">
            {{ angka(s.gap) }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="bisaSunting" class="bg-white rounded-2xl border border-stone-200 p-5 space-y-3">
      <h3 class="text-[13px] font-bold text-cam-ink">Tambah program</h3>

      <div class="grid sm:grid-cols-4 gap-3">
        <input v-model="baru.param" placeholder="Parameter (mis. 2.1)"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input v-model="baru.durasi" placeholder="Durasi"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input v-model="baru.sasaran" placeholder="Sasaran"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input v-model="baru.target" placeholder="Target"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
      </div>

      <textarea v-model="baru.opsi" rows="2" placeholder="Opsi perbaikan"
                class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]"></textarea>

      <button type="button" :disabled="menambah || !baru.param.trim() || !baru.opsi.trim()" @click="tambah"
              class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2 text-[12.5px] font-bold
                     hover:brightness-105 transition disabled:opacity-40 disabled:cursor-not-allowed">
        {{ menambah ? 'Menambah…' : 'Tambah' }}
      </button>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Daftar Program ({{ program.length }})</h3>
      </div>

      <p v-if="!program.length" class="px-5 py-8 text-[12.5px] text-stone-400">Belum ada program.</p>

      <div v-else class="divide-y divide-stone-100">
        <div v-for="(r, i) in program" :key="r.id ?? `x${i}`" class="px-5 py-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 basis-64 pr-3">
              <span class="num text-[11px] font-bold text-stone-400">Parameter {{ r.param || '—' }}</span>
              <div class="text-[12.5px] text-cam-ink leading-snug mt-0.5">{{ r.opsi }}</div>
              <div class="text-[11px] text-stone-500 mt-1.5">
                <span v-if="r.durasi">durasi: {{ r.durasi }}</span>
                <span v-if="r.sasaran"> · sasaran: {{ r.sasaran }}</span>
                <span v-if="r.target"> · target: {{ r.target }}</span>
              </div>
            </div>

            <div v-if="bisaSunting && r.id && sunting[r.id]" class="flex shrink-0 items-center gap-2">
              <!-- pr-7: tanda panah select digambar di dalam kotaknya, jadi
                   tanpa ruang di kanan ia menindih teks pilihan. -->
              <select v-model="sunting[r.id].status"
                      class="ring-focus rounded-lg border border-stone-200 pl-2 pr-7 py-1 text-[11.5px] font-semibold" aria-label="Status">
                <option v-for="s in statusPilihan" :key="s" :value="s">{{ s }}</option>
              </select>
              <input v-model.number="sunting[r.id].progress"
                     type="number" min="0" max="100"
                     class="ring-focus w-16 rounded-lg border border-stone-200 px-2 py-1 text-[11.5px] num">
              <button type="button" :disabled="!berubah(r)"
                      @click="simpanBaris(r.id)"
                      class="rounded-lg bg-cam-ink text-white px-2.5 py-1 text-[11.5px] font-bold
                             disabled:opacity-30 disabled:cursor-not-allowed">
                Simpan
              </button>
              <button type="button" @click="hapus(r.id)"
                      class="rounded-lg border border-stone-200 px-2.5 py-1 text-[11.5px] text-stone-500
                             hover:border-red-200 hover:text-red-600 transition">
                Hapus
              </button>
            </div>

            <span v-else class="text-[11.5px] font-semibold text-stone-500">
              {{ r.status }} · {{ r.progress }}%
            </span>
          </div>

          <div class="h-1.5 rounded-full bg-stone-100 mt-3 overflow-hidden">
            <div class="h-full rounded-full bg-cam-lime" :style="{ width: `${r.progress}%` }"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
