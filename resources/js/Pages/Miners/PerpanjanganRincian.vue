<script setup lang="ts">
/**
 * Perpanjangan Mine Permit Detail — `perpanjangan-users` Safe Track:
 * tabel rincian pengajuan, lampirannya, tabel persetujuan (pengaju →
 * dokter → HSE → KTT), dan riwayat perpanjangan kartunya.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import TabelPersetujuan from '../../Components/Miners/TabelPersetujuan.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';

const props = propHalaman();
const a = computed<any>(() => props.ajuan ?? {});
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);

function tindak(keadaan: string) {
  if (keadaan === 'setuju' && !window.confirm('Confirm Approved?')) return;
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/perpanjangan-permit/${a.value.id}/tindak`, {
    preserveScroll: true, onSuccess: () => { alasanTerbuka.value = null; tindakan.reset('catatan'); },
  });
}

const ulang = useForm({});
function ajukanUlang() {
  ulang.post(`/miners/perpanjangan-permit/${a.value.id}/ajukan-ulang`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Perpanjangan Mine Permit Detail"
                   :remah="[['Home', '/miners/dasbor'], ['Perpanjangan Permit', '/miners/perpanjangan-permit'], [a.nomor || 'Detail', null]]">
      <template #aksi><Link href="/miners/perpanjangan-permit" class="mn-btn mn-btn-garis">← Kembali</Link></template>
    </KepalaHalaman>

    <div v-for="k in ['kirim', 'ajukanUlang', 'keadaan']" :key="k">
      <div v-if="galat[k]" class="mn-alert mn-alert-bahaya" role="alert">{{ galat[k] }}</div>
    </div>

    <Kartu :judul="a.nomor || 'Perpanjangan'">
      <template #kanan><LencanaStatus :status="a.status" /></template>
      <div class="mn-gulir">
        <table class="mn-tabel">
          <tbody>
            <tr><td class="mn-redup">Tanggal Pengajuan</td><td>{{ tglId(a.tanggal) }}</td><td class="mn-redup">Masa Berlaku Baru</td><td><b>{{ tglId(a.berlakuBaru) }}</b></td></tr>
            <tr><td class="mn-redup">Nama Employee</td><td>{{ a.nama || '—' }}</td><td class="mn-redup">Noid</td>
              <td><Link v-if="a.permit_id" :href="`/miners/permit/${a.permit_id}`" class="mn-tautan">{{ a.noid || '—' }}</Link></td></tr>
            <tr><td class="mn-redup">Jabatan</td><td>{{ a.jabatan || '—' }}</td><td class="mn-redup">Departemen</td><td>{{ a.departemen || '—' }}</td></tr>
            <tr><td class="mn-redup">Perusahaan/Contractor</td><td>{{ a.perusahaan || '—' }}</td><td class="mn-redup">Sertifikat MCU</td>
              <td>
                <a v-if="a.mcu?.url" :href="a.mcu.url" target="_blank" rel="noopener" class="mn-btn mn-btn-utama">download</a>
                <span v-else class="mn-redup">{{ a.mcu?.ada ? 'terjaga' : '—' }}</span>
              </td></tr>
            <tr><td class="mn-redup">Tanggal Periksa MCU</td><td>{{ tglId(a.tanggalMcu) }}</td><td class="mn-redup">Form Pengajuan</td>
              <td>
                <a v-if="a.form?.url" :href="a.form.url" target="_blank" rel="noopener" class="mn-btn mn-btn-utama">download</a>
                <span v-else class="mn-redup">—</span>
              </td></tr>
            <tr><td class="mn-redup">Masa berlaku kartu saat ini</td><td>{{ tglId(a.berlakuKini) }}</td><td class="mn-redup">Created By</td><td>{{ a.pengaju || '—' }}</td></tr>
            <tr><td class="mn-redup">Remarks</td><td colspan="3">{{ a.alasanTolak || a.catatan || '' }}</td></tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <Kartu judul="Persetujuan">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />
      <p class="mn-petunjuk mt-2">Persetujuan dokter menulis tanggal periksa dan masa berlaku MCU baru ke register MCU; tanggal kartu baru berpindah sesudah KTT menyetujui.</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <template v-if="props.bolehTindak">
          <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">Approve</button>
          <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
          <button type="button" class="mn-btn mn-btn-bahaya" @click="alasanTerbuka = 'tolak'">Reject</button>
        </template>
        <button v-if="props.bolehAjukanUlang" type="button" class="mn-btn mn-btn-peringatan" :disabled="ulang.processing" @click="ajukanUlang">Ajukan Ulang</button>
      </div>
    </Kartu>

    <Kartu v-if="props.riwayat?.length" judul="Riwayat Perpanjangan" :keterangan="`${props.riwayat.length} kali`">
      <table class="mn-tabel" style="font-size:12px">
        <thead><tr><th>Ke-</th><th>Berlaku lama</th><th>Berlaku baru</th><th>Disetujui</th><th>Oleh</th></tr></thead>
        <tbody>
          <tr v-for="h in props.riwayat" :key="h.urutan">
            <td>{{ h.urutan }}</td><td>{{ tglId(h.lama) }}</td><td>{{ tglId(h.baru) }}</td><td>{{ h.pada }}</td><td>{{ h.oleh || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Reject Perpanjangan' : 'Kembalikan untuk diperbaiki'" @tutup="alasanTerbuka = null">
      <Medan label="Alasan" untuk="pr-alasan" wajib :galat="tindakan.errors.keadaan">
        <textarea id="pr-alasan" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="alasanTerbuka = null">Close</button>
        <button type="button" class="mn-btn" :class="alasanTerbuka === 'tolak' ? 'mn-btn-bahaya' : 'mn-btn-peringatan'"
                :disabled="tindakan.processing || !tindakan.catatan.trim()" @click="tindak(alasanTerbuka!)">Sending</button>
      </template>
    </Modal>
  </div>
</template>
