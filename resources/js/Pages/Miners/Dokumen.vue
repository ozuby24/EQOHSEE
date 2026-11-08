<script setup lang="ts">
/**
 * Halaman rincian satu dokumen Miners — susunan halaman detail Safe Track.
 *
 * SATU HALAMAN UNTUK EMPAT JENIS — MCU, induksi, Mine Permit, SIMPER.
 * Isinya sudah dinormalkan di App\Support\Miners\RincianDokumen menjadi
 * baris label/nilai, lampiran, dan daftar orang/unit, sehingga halaman
 * ini tidak perlu tahu sedang menggambar dokumen yang mana.
 *
 * Bagian PERSETUJUAN mengikuti include/persetujuan.blade.php: kalimat
 * posisi, satu kolom per langkah dengan jabatan dan organisasinya, lalu
 * tombol sesuai giliran pembukanya:
 *
 *   · pengaju pada drafnya          → "Kirim"
 *   · pengaju pada berkas ditolak / dikembalikan → "Ajukan Ulang"
 *   · penyetuju yang ditugasi        → Approve / Kembalikan / Reject
 *
 * Kembalikan dan Reject meminta alasan lebih dulu — server pun
 * menolaknya tanpa alasan.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import TabelPersetujuan from '../../Components/Miners/TabelPersetujuan.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';

const props = propHalaman();

const d        = computed<any>(() => props.dokumen ?? {});
const rincian  = computed<any[]>(() => (props.rincian ?? []) as any[]);
const lampiran = computed<any[]>(() => (props.lampiran ?? []) as any[]);
const orang    = computed<any[]>(() => (props.orang ?? []) as any[]);
const wajib    = computed<any[]>(() => (props.wajib ?? []) as any[]);
const kurang   = computed(() => wajib.value.filter((w) => !w.ada));

const judulDaftar = computed(() =>
  d.value.jenis === 'simper' ? 'Unit yang diizinkan'
  : d.value.jenis === 'induksi' ? 'Peserta'
  : 'Manpower pada surat');

const remah = computed<[string, string | null][]>(() => [
  ['Home', '/miners/dasbor'],
  [d.value.label ?? 'Miners', d.value.daftar ?? null],
  [d.value.nomor || 'Detail', null],
]);

const tindakan = useForm<{ keadaan: string; catatan: string }>({ keadaan: 'setuju', catatan: '' });
const kirimForm = useForm<{ kirim?: string; ajukanUlang?: string }>({});

const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);

function tindak(keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/${d.value.jenis}/${d.value.id}/tindak`, {
    preserveScroll: true,
    onSuccess: () => { alasanTerbuka.value = null; tindakan.reset('catatan'); },
  });
}

function kirim() {
  kirimForm.post(`/miners/${d.value.jenis}/${d.value.id}/kirim`, { preserveScroll: true });
}

function ajukanUlang() {
  kirimForm.post(`/miners/${d.value.jenis}/${d.value.id}/ajukan-ulang`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="`Detail ${d.label ?? ''}`" :remah="remah">
      <template #aksi>
        <Link :href="d.daftar" class="mn-btn mn-btn-garis">← Kembali</Link>
      </template>
    </KepalaHalaman>

    <!-- ══════════ Rincian ══════════ -->
    <Kartu :judul="d.nomor || `${d.label} tanpa nomor`" :keterangan="props.subjudul">
      <template #kanan><LencanaStatus :status="d.status" /></template>

      <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="b in rincian" :key="b.label" class="min-w-0">
          <dt class="mn-label">{{ b.label }}</dt>
          <dd class="text-[13px] break-words" :style="b.nada === 'peringatan' ? 'color:#b45309;font-weight:600' : ''">
            {{ b.nilai || '—' }}
          </dd>
          <dd v-if="b.sebab" class="mn-petunjuk" style="color:#b45309">{{ b.sebab }}</dd>
        </div>
      </dl>
    </Kartu>

    <!-- ══════════ Lampiran ══════════ -->
    <Kartu v-if="lampiran.length" judul="Lampiran">
      <table class="mn-tabel">
        <thead><tr><th style="width:42px">No</th><th>Lampiran</th><th>Nomor</th><th>Berkas</th></tr></thead>
        <tbody>
          <tr v-for="(b, i) in lampiran" :key="b.label">
            <td>{{ i + 1 }}</td>
            <td>{{ b.label }} <span v-if="b.kunci && b.kunci !== b.label" class="mn-redup">· {{ b.kunci }}</span></td>
            <td>{{ b.nomor || '—' }}</td>
            <td>
              <a v-if="b.url" :href="b.url" target="_blank" rel="noopener" class="mn-tautan">Lihat</a>
              <span v-else-if="b.ada" class="mn-redup"
                    title="Sudah diunggah, tetapi hanya dapat dibuka paramedis, tim OHSE, atau administrator.">terjaga</span>
              <span v-else class="mn-lencana mn-l-terang">belum diunggah</span>
            </td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <!-- ══════════ Lampiran wajib ══════════ -->
    <Kartu v-if="wajib.length" judul="Lampiran wajib" :keterangan="`${wajib.length - kurang.length} / ${wajib.length} terpenuhi`">
      <ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2 text-[13px]">
        <li v-for="w in wajib" :key="w.kunci" class="flex items-baseline gap-2">
          <span aria-hidden="true" :style="{ color: w.ada ? '#198754' : '#fd7e14' }">{{ w.ada ? '✓' : '○' }}</span>
          <span :class="{ 'mn-redup': w.ada }">{{ w.label }}</span>
          <span class="sr-only">{{ w.ada ? '— sudah dilampirkan' : '— belum dilampirkan' }}</span>
        </li>
      </ul>
      <p v-if="kurang.length" class="mn-petunjuk mt-2" style="color:#b45309">
        Berkas belum dapat dikirim sampai seluruh lampiran wajib terpenuhi.
      </p>
    </Kartu>

    <!-- ══════════ Manpower / peserta / unit ══════════ -->
    <Kartu v-if="orang.length" :judul="judulDaftar" :keterangan="`${orang.length} data`">
      <div class="mn-gulir">
        <table class="mn-tabel">
          <thead>
            <tr>
              <th style="width:42px">No</th>
              <th>{{ d.jenis === 'simper' ? 'Unit' : 'Nama Employee' }}</th>
              <th v-if="d.jenis === 'mcu'">NIK</th>
              <th v-if="d.jenis === 'mcu'">Hasil MCU</th>
              <th v-if="d.jenis === 'mcu'">Masa Berlaku MCU</th>
              <th v-if="d.jenis === 'induksi'">Nilai</th>
              <th v-if="d.jenis === 'induksi'">Keterangan</th>
              <th v-if="d.jenis === 'simper'">Master Unit</th>
              <th v-if="d.jenis === 'simper'">Kewenangan</th>
              <th v-if="d.jenis === 'simper'">Nilai</th>
              <th>Berkas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, i) in orang" :key="o.id">
              <td>{{ i + 1 }}</td>
              <td>
                <Link v-if="o.pekerja_id" :href="`/miners/${o.pekerja_id}`" class="mn-tautan">{{ o.nama }}</Link>
                <span v-else>{{ o.nama || '—' }}</span>
              </td>
              <template v-if="d.jenis === 'mcu'">
                <td>{{ o.nik || '—' }}</td>
                <td>{{ o.hasil || 'Belum Dinilai' }}<span v-if="o.napza" class="mn-redup"> · napza {{ o.napza }}</span></td>
                <td><LencanaMasaBerlaku :tanggal="o.sampai" /></td>
              </template>
              <template v-if="d.jenis === 'induksi'">
                <td>{{ o.nilai ?? '—' }}</td>
                <td>
                  <span class="mn-lencana" :class="o.lulus ? 'mn-l-sukses' : 'mn-l-bahaya'">{{ o.lulus ? 'Lulus' : 'Tidak Lulus' }}</span>
                  <span class="mn-redup"> · percobaan {{ o.percobaan }}</span>
                </td>
              </template>
              <template v-if="d.jenis === 'simper'">
                <td>{{ o.golongan || '—' }}</td>
                <td>{{ o.kewenangan || '—' }}</td>
                <td class="mn-redup">P2H {{ o.nilai?.P2H ?? '—' }} · praktik {{ o.nilai?.Praktik ?? '—' }}</td>
              </template>
              <td>
                <template v-for="b in (o.berkas ?? [])" :key="b.jenis">
                  <a v-if="b.url" :href="b.url" target="_blank" rel="noopener" class="mn-tautan mr-2">{{ b.label }}</a>
                  <span v-else-if="b.ada" class="mn-redup mr-2">{{ b.label }} · terjaga</span>
                </template>
                <div v-for="j in (o.rujukan ?? [])" :key="j.id" class="mn-redup text-[11.5px]">
                  Rujukan {{ tglId(j.tanggal) }} · {{ j.dokter }}
                  <a v-if="j.url" :href="j.url" target="_blank" rel="noopener" class="mn-tautan">surat</a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <!-- ══════════ Persetujuan ══════════ -->
    <Kartu judul="Persetujuan" keterangan="alur persetujuan dokumen ini">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <!-- Kirim: langkah pengaju disetujui oleh pengajunya sendiri. -->
        <button v-if="props.bolehKirim && !props.bolehAjukanUlang" type="button" class="mn-btn mn-btn-sukses"
                :disabled="kirimForm.processing" @click="kirim">Kirim</button>

        <button v-if="props.bolehAjukanUlang" type="button" class="mn-btn mn-btn-utama"
                :disabled="kirimForm.processing" @click="ajukanUlang">Ajukan Ulang</button>

        <template v-if="props.bolehTindak">
          <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">Approve</button>
          <button type="button" class="mn-btn mn-btn-peringatan" :disabled="tindakan.processing"
                  @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
          <button type="button" class="mn-btn mn-btn-bahaya" :disabled="tindakan.processing"
                  @click="alasanTerbuka = 'tolak'">Reject</button>
        </template>

        <span v-if="!props.bolehKirim && !props.bolehTindak && !props.bolehAjukanUlang" class="mn-petunjuk">
          Tidak ada langkah yang menunggu tindakan Anda.
        </span>
      </div>

      <p v-if="kirimForm.errors.kirim || kirimForm.errors.ajukanUlang" class="mn-galat mt-2">
        {{ kirimForm.errors.kirim || kirimForm.errors.ajukanUlang }}
      </p>
      <p v-if="tindakan.errors.keadaan" class="mn-galat mt-2">{{ tindakan.errors.keadaan }}</p>
    </Kartu>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Reject pengajuan' : 'Kembalikan untuk diperbaiki'"
           @tutup="alasanTerbuka = null">
      <p class="text-[13px] mb-3">
        <template v-if="alasanTerbuka === 'tolak'">
          Status menjadi <b>reject</b>. Pengaju dapat memperbaiki lalu mengajukan ulang; alurnya diulang dari awal.
        </template>
        <template v-else>
          Berkas kembali menjadi draf pengaju untuk dilengkapi, lalu dikirim ulang.
        </template>
      </p>
      <Medan label="Alasan" untuk="alasan-tindak" wajib :galat="tindakan.errors.keadaan">
        <textarea id="alasan-tindak" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="alasanTerbuka = null">Close</button>
        <button type="button" class="mn-btn" :class="alasanTerbuka === 'tolak' ? 'mn-btn-bahaya' : 'mn-btn-peringatan'"
                :disabled="tindakan.processing || !tindakan.catatan.trim()" @click="tindak(alasanTerbuka!)">
          {{ alasanTerbuka === 'tolak' ? 'Reject' : 'Kembalikan' }}
        </button>
      </template>
    </Modal>
  </div>
</template>
