<script setup lang="ts">
/**
 * Perpanjangan Mine Permit Detail — `perpanjangan-users` Safe Track:
 * rincian pengajuan, lampirannya, persetujuan (pengaju → dokter → HSE →
 * KTT), dan riwayat perpanjangan kartunya. Rupa: kepala profil + daftar
 * definisi + stepper; tombol penyetuju di bilah aksi.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import TabelPersetujuan from '../../Components/Miners/TabelPersetujuan.vue';
import KepalaProfil from '../../Components/Miners/KepalaProfil.vue';
import DaftarDefinisi from '../../Components/Miners/DaftarDefinisi.vue';
import Butir from '../../Components/Miners/Butir.vue';
import ChipBerkas from '../../Components/Miners/ChipBerkas.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();

/* Penegasan memakai dialog aplikasi, bukan confirm() peramban — di
   webview ponsel dialog bawaan dibungkam dan tindakannya diam-diam batal. */
const { dialog, tanya, batal, lanjut } = useDialog();
const a = computed<any>(() => props.ajuan ?? {});
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);

async function tindak(keadaan: string) {
  if (keadaan === 'setuju' && !await tanya({ judul: 'Confirm Approved?', labelAksi: 'Approve' })) return;
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

    <KepalaProfil :judul="a.nama || '—'" :eyebrow="`Perpanjangan Mine Permit · ${a.nomor || 'tanpa nomor'}`"
                  :keterangan="[['Noid', a.noid], ['Jabatan', a.jabatan], ['Perusahaan', a.perusahaan]]">
      <template #lencana>
        <LencanaStatus :status="a.status" />
        <span class="mn-lencana mn-l-terang">Berlaku kini {{ tglId(a.berlakuKini) }}</span>
        <span class="mn-lencana mn-l-utama">Berlaku baru {{ tglId(a.berlakuBaru) }}</span>
      </template>
      <template #aksi>
        <Link v-if="a.permit_id" :href="`/miners/permit/${a.permit_id}`" class="mn-btn mn-btn-garis">Lihat Mine Permit</Link>
      </template>
    </KepalaProfil>

    <div v-if="props.bolehTindak" class="mn-bilah-aksi">
      <div class="mn-bilah-aksi-teks"><b>Menunggu persetujuan Anda</b>Persetujuan dokter menulis tanggal periksa dan masa berlaku MCU baru; tanggal kartu berpindah sesudah KTT menyetujui.</div>
      <div class="mn-bilah-aksi-tombol">
        <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">Approve</button>
        <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
        <button type="button" class="mn-btn mn-btn-garis-bahaya" @click="alasanTerbuka = 'tolak'">Reject</button>
      </div>
    </div>

    <Kartu :judul="a.nomor || 'Perpanjangan'">
      <DaftarDefinisi>
        <Butir label="Tanggal Pengajuan" :nilai="tglId(a.tanggal)" />
        <Butir label="Masa Berlaku Baru"><b>{{ tglId(a.berlakuBaru) }}</b></Butir>
        <Butir label="Nama Employee" :nilai="a.nama" />
        <Butir label="Noid">
          <Link v-if="a.permit_id" :href="`/miners/permit/${a.permit_id}`" class="mn-tautan">{{ a.noid || '—' }}</Link><template v-else>{{ a.noid || '—' }}</template>
        </Butir>
        <Butir label="Jabatan" :nilai="a.jabatan" />
        <Butir label="Departemen" :nilai="a.departemen" />
        <Butir label="Perusahaan/Contractor" :nilai="a.perusahaan" />
        <Butir label="Sertifikat MCU">
          <ChipBerkas v-if="a.mcu?.url" :href="a.mcu.url" nama="Sertifikat MCU" aksi="download" />
          <span v-else class="mn-redup">{{ a.mcu?.ada ? 'terjaga' : '—' }}</span>
        </Butir>
        <Butir label="Tanggal Periksa MCU" :nilai="tglId(a.tanggalMcu)" />
        <Butir label="Form Pengajuan">
          <ChipBerkas v-if="a.form?.url" :href="a.form.url" nama="Form Pengajuan" aksi="download" />
          <span v-else class="mn-redup">—</span>
        </Butir>
        <Butir label="Masa berlaku kartu saat ini" :nilai="tglId(a.berlakuKini)" />
        <Butir label="Created By" :nilai="a.pengaju" />
        <Butir label="Remarks" :nilai="a.alasanTolak || a.catatan" penuh />
      </DaftarDefinisi>
    </Kartu>

    <Kartu judul="Persetujuan" keterangan="Pengaju → Dokter → HSE → KTT">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />
      <p class="mn-petunjuk mt-3">Persetujuan dokter menulis tanggal periksa dan masa berlaku MCU baru ke register MCU; tanggal kartu baru berpindah sesudah KTT menyetujui.</p>
      <div v-if="props.bolehAjukanUlang" class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="mn-btn mn-btn-peringatan" :disabled="ulang.processing" @click="ajukanUlang">Ajukan Ulang</button>
      </div>
    </Kartu>

    <Kartu v-if="props.riwayat?.length" judul="Riwayat Perpanjangan" :keterangan="`${props.riwayat.length} kali`">
      <table class="mn-tabel">
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
                :disabled="tindakan.processing || !tindakan.catatan.trim()" @click="tindak(alasanTerbuka!)">
          {{ tindakan.processing ? 'Sending…' : alasanTerbuka === 'tolak' ? 'Reject' : 'Kembalikan' }}
        </button>
      </template>
    </Modal>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
