<script setup lang="ts">
/**
 * Respon Perusahaan Terhadap Pelaksanaan Audit Internal SMKP — tanggapan dan
 * komitmen auditi atas pelaksanaan dan hasil audit, ditandatangani KTT (atau
 * PJO untuk perusahaan jasa pertambangan). Bukan bagian lampiran resmi
 * Kepdirjen 185.K/37.04/DJB/2019 — lihat KopDokumen::daftar().
 *
 * Satu-satunya isian bebas: tempat penandatanganan ("Padang, 12 Desember
 * 2025"); nama perusahaan, tanggal pelaksanaan audit, dan site diturunkan
 * dari data audit yang sudah ada, seperti lampiran lain.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import type { KopDok, PihakTtd, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

const props = defineProps<{
  audit: RingkasAudit;
  dok: KopDok;
  ttd: TtdForm;
  kembali: string;
  /** "{Induk} site {Perusahaan}" hanya berlaku untuk audit perusahaan jasa pertambangan. */
  site: string | null;
  pihak: Array<{ peran: string; kunci: PihakTtd; nama: string }>;
  tempat: string;
  bisaSunting: boolean;
}>();

const pt = props.audit.perusahaan.nama;
// Awalan spasi: dipakai langsung setelah kata sebelumnya ("Manajemen Puncak{{ diSite }}").
const diSite = props.site ? ` ${pt} site ${props.site}` : ` ${pt}`;
const periode = (props.audit.mulai && props.audit.selesai ? `Pada tanggal ${props.audit.mulai} s.d. ${props.audit.selesai}, ` : '')
  + `${diSite.trim()} telah melaksanakan Audit Internal Sistem Manajemen Keselamatan Pertambangan (SMKP) sesuai Rencana Audit yang ditetapkan.`;

/* Tempat penandatanganan: satu kolom bebas, disimpan terpisah dari tanggal (sudah ditangani FormTtd). */
const menyimpanTempat = ref(false);
const draf = ref(props.tempat);
function simpanTempat() {
  if (menyimpanTempat.value || draf.value === props.tempat) return;
  menyimpanTempat.value = true;
  router.post(`/smkp/${props.audit.id}/formulir/respon`, { tempat: draf.value }, {
    preserveScroll: true, preserveState: true, only: ['tempat'],
    onFinish: () => { menyimpanTempat.value = false; },
  });
}
</script>

<template>
  <Head title="Respon Perusahaan" />
  <CetakLayout judul="Respon Perusahaan Terhadap Pelaksanaan Audit" :kembali="kembali">
    <Lembar :dok="dok" :halaman="1" :dari="1">
      <p class="form-judul">Respon Perusahaan Terhadap Pelaksanaan<br>Audit Internal SMKP Tahun {{ audit.tahun }}</p>

      <div class="space-y-3 text-[11.5px] leading-relaxed text-justify">
        <p>
          {{ pt }} menyadari bahwa audit internal merupakan bagian dari langkah untuk mencapai peningkatan berkelanjutan.
          Oleh sebab itu proses ini selalu mendapatkan dukungan penuh dari Manajemen Puncak{{ diSite }} dan harus dilaksanakan dengan sebaik-baiknya.
          Dalam penerapan Sistem Manajemen Keselamatan Pertambangan (SMKP), audit internal merupakan kewajiban yang dipersyaratkan dan pelaksanaannya diatur dalam
          Kepmen ESDM Nomor 1827 K/30/MEM/2018 dan Kepdirjen Minerba Nomor 185.K/37.04/DJB/2019. {{ periode }}
        </p>

        <ol class="list-decimal pl-5 space-y-1.5">
          <li>Dengan hasil penilaian dari audit tersebut, {{ pt }} dapat mengetahui sejauh mana tingkat kesesuaian terhadap ketentuan SMKP Minerba.</li>
          <li>Dengan temuan-temuan audit tersebut, {{ pt }} dapat mengidentifikasi langkah-langkah perbaikan guna meningkatkan tingkat kesesuaian terhadap SMKP Minerba.</li>
          <li>Mengacu kepada hasil audit tersebut, kami berkomitmen untuk segera melakukan tindakan koreksi dan pencegahan yang diperlukan untuk perbaikan berkelanjutan.</li>
        </ol>

        <p>Demikian tanggapan dan komitmen ini, kami mewakili perusahaan atas pelaksanaan dan hasil Audit Internal SMKP Minerba di{{ diSite }}.</p>
      </div>

      <div class="mt-8 flex items-center gap-1.5 text-[11.5px]">
        <template v-if="bisaSunting">
          <input v-model="draf" class="no-print input-kecil w-40" maxlength="150" placeholder="Tempat, mis. Padang" @blur="simpanTempat" @keydown.enter.prevent="simpanTempat">
          <span class="hidden print:inline">{{ tempat || '…………' }}</span>
        </template>
        <span v-else>{{ tempat || '…………' }}</span>
        <span>, {{ ttd.tanggal.respon?.[pihak[0].kunci]?.teks ?? '…………' }}</span>
      </div>

      <FormTtd :pihak="pihak" :ttd="ttd" bagian="respon" />
    </Lembar>
  </CetakLayout>
</template>
