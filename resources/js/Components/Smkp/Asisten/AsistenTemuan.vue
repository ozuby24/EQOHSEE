<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TombolAsisten from './TombolAsisten.vue';
import PanelAsisten from './PanelAsisten.vue';
import BagianAsisten from './BagianAsisten.vue';
import UsulanIsian from './UsulanIsian.vue';
import { gunakanAsisten } from './api';
import type { AksiRtp, IsianTemuanAi, SaranTemuan } from '../../../smkp/types';

/*
 * Asisten untuk satu temuan/CAR (Smkp/Temuan): akar masalah 5-Why, koreksi,
 * RTP, PIC, target, bukti penutupan, dan tingkat risiko. Setiap kolom
 * formulir diterapkan satu per satu lewat pratinjau — tidak ada yang
 * menimpa diam-diam dan tidak ada yang tersimpan sebelum auditor menekan Simpan.
 */
const props = defineProps<{
  auditId: number;
  temuan: { id: number; kode: string; uraian?: string };
  isian: IsianTemuanAi;
}>();
const emit = defineEmits<{ terapkan: [kolom: keyof IsianTemuanAi, nilai: string] }>();

const page = usePage<any>();
const buka = ref(false);
const { hasil, memuat, galat, minta } = gunakanAsisten<SaranTemuan>(
  () => `/asisten/smkp/${props.auditId}/temuan/${props.temuan.id}`,
  () => ({ akar_masalah: props.isian.akar_masalah, tindakan: props.isian.tindakan }),
);

/*
 * Hasil analisis langsung disalin ke kolom Akar masalah & Tindakan perbaikan
 * yang masih kosong — atau masih berisi salinan analisis sebelumnya, sehingga
 * "Analisis ulang" ikut memperbaruinya. Teks yang diketik pengguna tidak
 * ditimpa (tetap lewat tombol Terapkan/Ganti). Tersimpan setelah Simpan.
 */
const SALIN = ['akar_masalah', 'tindakan'] as const;
const salinan: Partial<Record<keyof IsianTemuanAi, string>> = {};
const tersalin = ref<string[]>([]);
watch(hasil, (h) => {
  tersalin.value = [];
  if (!h) return;
  for (const k of SALIN) {
    const usul = (h.isian[k] ?? '').trim();
    const kini = (props.isian[k] ?? '').trim();
    if (!usul || (kini !== '' && kini !== salinan[k])) continue;
    emit('terapkan', k, usul);
    salinan[k] = usul;
    tersalin.value.push(k === 'akar_masalah' ? 'Akar masalah' : 'Tindakan perbaikan');
  }
});

function bukaPanel() {
  buka.value = true;
  if (page.props.asisten?.aktif && !hasil.value && !memuat.value) minta();
}

const tanggal = (iso: string) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '');
const rincian = (a: AksiRtp) => [a.pic && `PIC: ${a.pic}`, `${a.hari} hari`].filter(Boolean).join(' · ');
const pakai = (kolom: keyof IsianTemuanAi) => (nilai: string) => emit('terapkan', kolom, nilai);

const kelompok = computed(() => (hasil.value
  ? [{ judul: 'Tindakan korektif', aksi: hasil.value.korektif }, { judul: 'Tindakan pencegahan', aksi: hasil.value.preventif }]
  : []));
const teksRisiko = computed(() => (hasil.value ? `Tingkat risiko ${hasil.value.risiko.label}. ${hasil.value.risiko.alasan}` : ''));
const RISIKO: Record<string, string> = {
  rendah: 'bg-slate-100 text-slate-600', sedang: 'bg-amber-50 text-amber-700', tinggi: 'bg-rose-50 text-rose-700', ekstrem: 'bg-rose-600 text-white',
};
</script>

<template>
  <span class="inline-flex">
    <TombolAsisten @click="bukaPanel" />
    <PanelAsisten v-model:buka="buka" judul="Analisis temuan & RTP" :sub="[temuan.kode, temuan.uraian].filter(Boolean).join(' · ')"
                  :memuat="memuat" :galat="galat" :ada-hasil="!!hasil" @ulang="minta">
      <template v-if="hasil">
        <p v-if="tersalin.length" class="rounded-lg border border-hijau-200 bg-hijau-50 px-3 py-2 text-[12.5px] text-hijau-800">
          <b class="font-semibold">{{ tersalin.join(' & ') }}</b> sudah disalin ke formulir. Periksa, lalu tekan <b class="font-semibold">Simpan</b>.
        </p>
        <BagianAsisten judul="Akar masalah" :teks="hasil.isian.akar_masalah">
          <template #lencana><span class="lencana bg-slate-100 text-slate-600">{{ hasil.akar.label }}</span></template>
          <ol v-if="hasil.akar.mengapa.length" class="space-y-2">
            <li v-for="(m, i) in hasil.akar.mengapa" :key="i" class="flex gap-2.5">
              <span class="tabular-nums mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-md bg-slate-100 text-[10.5px] font-semibold text-slate-600">{{ i + 1 }}</span>
              <span><b class="font-semibold text-slate-900">{{ m.tanya }}</b><br>{{ m.jawab }}</span>
            </li>
          </ol>
          <p v-if="hasil.akar.pernyataan" class="rounded-lg bg-slate-50 px-3 py-2"><b class="font-semibold text-slate-900">Akar masalah:</b> {{ hasil.akar.pernyataan }}</p>
          <template #aksi>
            <UsulanIsian label="Kolom Akar masalah" :usulan="hasil.isian.akar_masalah" :sekarang="isian.akar_masalah" @pakai="pakai('akar_masalah')" />
          </template>
        </BagianAsisten>

        <BagianAsisten judul="Koreksi & rencana tindak perbaikan" :teks="hasil.isian.tindakan">
          <p v-if="hasil.koreksi"><b class="font-semibold text-slate-900">Koreksi segera:</b> {{ hasil.koreksi }}</p>
          <div v-for="k in kelompok" :key="k.judul">
            <template v-if="k.aksi.length">
              <p class="font-semibold text-slate-900">{{ k.judul }}</p>
              <ul class="mt-1 space-y-2">
                <li v-for="(a, i) in k.aksi" :key="i" class="rounded-lg border border-slate-100 px-3 py-2">
                  <p>{{ a.tindakan }}</p>
                  <p class="bantu mt-0.5">{{ rincian(a) }}<template v-if="a.bukti"> · Bukti: {{ a.bukti }}</template></p>
                </li>
              </ul>
            </template>
          </div>
          <template #aksi>
            <UsulanIsian label="Kolom Tindakan perbaikan" :usulan="hasil.isian.tindakan" :sekarang="isian.tindakan" @pakai="pakai('tindakan')" />
          </template>
        </BagianAsisten>

        <BagianAsisten judul="Penanggung jawab & target">
          <p>Usulan target penutupan <b class="font-semibold text-slate-900">{{ hasil.target.hari }} hari</b> — {{ tanggal(hasil.target.tanggal) }}.</p>
          <template #aksi>
            <UsulanIsian label="Penanggung jawab" mode="nilai" :maks="150" :usulan="hasil.isian.penanggung_jawab" :sekarang="isian.penanggung_jawab" @pakai="pakai('penanggung_jawab')" />
            <UsulanIsian label="Target selesai" mode="nilai" :usulan="hasil.isian.target_selesai" :sekarang="isian.target_selesai"
                         :tampil="tanggal(hasil.isian.target_selesai)" :tampil-sekarang="tanggal(isian.target_selesai)" @pakai="pakai('target_selesai')" />
          </template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.bukti_penutupan.length" judul="Bukti penutupan" :teks="hasil.isian.verifikasi">
          <ul class="list-disc space-y-1 pl-5"><li v-for="(b, i) in hasil.bukti_penutupan" :key="i">{{ b }}</li></ul>
          <template #aksi>
            <UsulanIsian label="Kolom Verifikasi keefektifan" :usulan="hasil.isian.verifikasi" :sekarang="isian.verifikasi" @pakai="pakai('verifikasi')" />
          </template>
        </BagianAsisten>

        <BagianAsisten judul="Tingkat risiko" :teks="teksRisiko">
          <template #lencana><span class="lencana" :class="RISIKO[hasil.risiko.tingkat] ?? RISIKO.sedang">{{ hasil.risiko.label }}</span></template>
          <p>{{ hasil.risiko.alasan }}</p>
        </BagianAsisten>
      </template>
    </PanelAsisten>
  </span>
</template>
