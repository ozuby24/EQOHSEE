<script setup lang="ts">
/**
 * Detail Induction — `detail_employee_induction` (pengaju) dan
 * `admin_employee_induction` (HSE) Safe Track.
 *
 * Pengaju menambahkan peserta dari daftar calon (MCU disetujui, Fit /
 * Fit With Note, belum pernah didaftarkan) — daftar kosong menyebut
 * sebabnya. HSE yang ditugasi mengisi tanggal, lokasi, dan Lulus / Tidak
 * Lulus tiap peserta lalu menekan "update data". Sertifikat terbit
 * sesudah pendaftarannya disetujui, bagi yang tidak Tidak Lulus.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import TabelPersetujuan from '../../Components/Miners/TabelPersetujuan.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();

/* Penegasan memakai dialog aplikasi, bukan confirm() peramban — di
   webview ponsel dialog bawaan dibungkam dan tindakannya diam-diam batal. */
const { dialog, tanya, batal, lanjut } = useDialog();

/* Pilihan kelulusan peserta — disebut satu per satu, bukan diulang
   dari kunci objek server. Nilainya sama dengan InduksiOrang::PILIHAN
   yang memvalidasinya di server. */
const KELULUSAN = [
  { nilai: 'lulus', label: 'Lulus' },
  { nilai: 'gagal', label: 'Tidak Lulus' },
] as const;

const ind = computed<any>(() => props.induksi ?? {});
const orang = computed<any[]>(() => (props.orang ?? []) as any[]);
const calon = computed<any[]>(() => (props.calon ?? []) as any[]);
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);

/* ─────────── jadwal & kelulusan (HSE) ─────────── */

const jadwal = useForm<{ peserta: { id: number; tanggal_induksi: string; lokasi: string; status: string }[] }>({ peserta: [] });

function isiJadwal() {
  jadwal.peserta = orang.value.map((o) => ({
    id: o.id,
    tanggal_induksi: o.tanggal ?? '',
    lokasi: o.lokasi ?? o.lokasiPilihan?.[0] ?? '',
    status: ['lulus', 'gagal'].includes(o.status) ? o.status : 'lulus',
  }));
}
watch(orang, isiJadwal, { immediate: true });

function simpanJadwal() {
  jadwal.post(`/miners/induksi/${ind.value.id}/jadwal`, { preserveScroll: true });
}

/* ─────────── peserta ─────────── */

const tambahTerbuka = ref(false);
const tambah = useForm({ mcu_orang_id: '' as string | number });

function simpanPeserta() {
  tambah.post(`/miners/induksi/${ind.value.id}/orang`, { preserveScroll: true, onSuccess: () => { tambahTerbuka.value = false; tambah.reset(); } });
}

const hapusForm = useForm({});
async function hapusPeserta(o: any) {
  if (!await tanya({ judul: 'Keluarkan peserta', pesan: `Keluarkan ${o.nama} dari pengajuan induksi ini?`, labelAksi: 'Keluarkan', nada: 'bahaya' })) return;
  hapusForm.delete(`/miners/induksi/${ind.value.id}/orang/${o.id}`, { preserveScroll: true });
}

/* ─────────── alur ─────────── */

const kirimForm = useForm({});
const kirimTerbuka = ref(false);
function kirim() {
  kirimForm.post(`/miners/induksi/${ind.value.id}/kirim`, { preserveScroll: true, onFinish: () => { kirimTerbuka.value = false; } });
}
function ajukanUlang() {
  kirimForm.post(`/miners/induksi/${ind.value.id}/ajukan-ulang`, { preserveScroll: true });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);
async function tindak(keadaan: string) {
  if (keadaan === 'setuju' && !await tanya({ judul: 'Confirm Approved?', labelAksi: 'Approve' })) return;
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/induksi/${ind.value.id}/tindak`, {
    preserveScroll: true, onSuccess: () => { alasanTerbuka.value = null; tindakan.reset('catatan'); },
  });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Detail Induction" :remah="[['Home', '/miners/dasbor'], ['Induction', '/miners/induksi'], [ind.nomor || 'Detail', null]]">
      <template #aksi><Link href="/miners/induksi" class="mn-btn mn-btn-garis">← Kembali</Link></template>
    </KepalaHalaman>

    <div v-for="k in ['hapus', 'kirim', 'ajukanUlang', 'keadaan', 'mcu_orang_id', 'peserta']" :key="k">
      <div v-if="galat[k]" class="mn-alert mn-alert-bahaya" role="alert">{{ galat[k] }}</div>
    </div>

    <Kartu judul="Detail Induction">
      <template #kanan><LencanaStatus :status="ind.status" /></template>

      <div v-if="ind.alasanTolak && ['draf', 'ditolak'].includes(ind.status)" class="mn-langkah mn-langkah-tolak">
        <div>
          <div class="mn-langkah-label">{{ ind.status === 'ditolak' ? 'Ditolak' : 'Dikembalikan' }} — alasan</div>
          <div class="mn-langkah-kurang">{{ ind.alasanTolak }}</div>
        </div>
      </div>

      <div class="mn-baca">
        <Medan label="No Register"><span class="mn-nilai-baca">{{ ind.nomor || '—' }}</span></Medan>
        <Medan label="Date Induction"><span class="mn-nilai-baca">{{ tglId(ind.tanggal) }}</span></Medan>
        <Medan label="Title"><span class="mn-nilai-baca">{{ ind.perihal || '—' }}</span></Medan>
        <Medan label="File Permohonan">
          <div>
            <a v-if="ind.permohonan?.url" :href="ind.permohonan.url" target="_blank" rel="noopener" class="mn-btn mn-btn-garis">Attachment Unduh</a>
            <span v-else class="mn-redup">Belum diunggah</span>
          </div>
        </Medan>
        <Medan label="Creator"><span class="mn-nilai-baca">{{ ind.pengaju || '—' }}</span></Medan>
        <Medan label="IUP penyelenggara"><span class="mn-nilai-baca">{{ ind.iup || '—' }}</span></Medan>
        <Medan label="Remarks" penuh><span class="mn-nilai-baca">{{ ind.catatan || '' }}</span></Medan>
      </div>
    </Kartu>

    <Kartu judul="Manpower Induction" :keterangan="`${orang.length} peserta`">
      <div v-if="props.bolehTambah" class="mb-3">
        <button type="button" class="mn-btn mn-btn-utama" @click="tambahTerbuka = true">+ Add Peserta</button>
      </div>

      <div class="mn-gulir">
        <table class="mn-tabel" style="font-size:12px">
          <thead>
            <tr>
              <th style="width:36px">No</th>
              <th>Expired MCU</th>
              <th>Nama Lengkap</th>
              <th>Perusahaan</th>
              <th>Tanggal Induksi</th>
              <th>Tempat Induksi</th>
              <th>Status</th>
              <th>Sertifikat</th>
              <th>#</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, i) in orang" :key="o.id">
              <td>{{ i + 1 }}</td>
              <td>{{ tglId(o.mcuSampai) }}</td>
              <td>
                <Link v-if="o.pekerja_id" :href="`/miners/${o.pekerja_id}`" class="mn-tautan">{{ o.nama }}</Link>
                <span v-else>{{ o.nama }}</span>
              </td>
              <td>{{ o.perusahaan || '—' }}</td>
              <template v-if="props.bolehNilai && jadwal.peserta[i]">
                <td><input v-model="jadwal.peserta[i].tanggal_induksi" type="date" class="mn-isian" :aria-label="`Tanggal induksi ${o.nama}`"></td>
                <td>
                  <select v-model="jadwal.peserta[i].lokasi" class="mn-isian" :aria-label="`Lokasi induksi ${o.nama}`">
                    <option v-for="l in o.lokasiPilihan" :key="l" :value="l">{{ l }}</option>
                  </select>
                </td>
                <td>
                  <select v-model="jadwal.peserta[i].status" class="mn-isian" :aria-label="`Kelulusan ${o.nama}`">
                    <option v-for="p in KELULUSAN" :key="p.nilai" :value="p.nilai">{{ p.label }}</option>
                  </select>
                </td>
              </template>
              <template v-else>
                <td>{{ tglId(o.tanggal) }}</td>
                <td>{{ o.lokasi || '—' }}</td>
                <td>
                  <span v-if="o.status === 'lulus'" class="mn-lencana mn-l-sukses">Lulus</span>
                  <span v-else-if="o.status === 'gagal'" class="mn-lencana mn-l-bahaya">Tidak Lulus</span>
                  <span v-else class="mn-redup">{{ props.STATUS_ORANG?.[o.status] ?? '—' }}</span>
                </td>
              </template>
              <td>
                <a v-if="o.sertifikat" :href="o.sertifikat" target="_blank" rel="noopener" class="mn-lencana mn-l-sukses">Sertifikat</a>
                <span v-else-if="o.status === 'gagal'" class="mn-lencana mn-l-bahaya" :title="o.sertifikatAlasan">tidak lulus</span>
                <span v-else class="mn-lencana mn-l-abu" :title="o.sertifikatAlasan">belum terbit</span>
                <a v-if="o.unggahan?.url" :href="o.unggahan.url" target="_blank" rel="noopener" class="mn-lencana mn-l-gelap ml-1"
                   style="background:#212529" title="Berkas sertifikat yang diunggah">unggahan</a>
              </td>
              <td><TombolIkon v-if="o.bolehHapus" ikon="hapus" varian="bahaya" judul="Keluarkan peserta" @click="hapusPeserta(o)" /></td>
            </tr>
            <tr v-if="!orang.length"><td colspan="9" class="mn-kosong">Belum ada peserta pada pengajuan induksi ini.</td></tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.bolehNilai && orang.length" class="mt-3">
        <button type="button" class="mn-btn mn-btn-utama" :disabled="jadwal.processing" @click="simpanJadwal">update data</button>
      </div>
    </Kartu>

    <div v-if="ind.status === 'draf' && props.bolehKirim" class="mn-langkah">
      <div>
        <div class="mn-langkah-label">Status: draft — belum dikirim</div>
        <div v-if="!orang.length" class="mn-langkah-kurang">Tambahkan sedikitnya satu peserta, barulah pengajuan ini bisa dikirim ke HSE.</div>
        <div v-else class="mn-langkah-teks">{{ orang.length }} peserta terdaftar. Kirim supaya HSE menerima permohonan induksinya.</div>
      </div>
      <button type="button" class="mn-btn mn-btn-utama" :disabled="!orang.length" @click="kirimTerbuka = true">Sending</button>
    </div>

    <Kartu v-if="ind.status !== 'draf' || props.bolehAjukanUlang" judul="Persetujuan">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />
      <div class="mt-4 flex flex-wrap gap-2">
        <template v-if="props.bolehTindak">
          <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">approve</button>
          <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
          <button type="button" class="mn-btn mn-btn-garis-bahaya" @click="alasanTerbuka = 'tolak'">reject</button>
        </template>
        <button v-if="props.bolehAjukanUlang" type="button" class="mn-btn mn-btn-utama" :disabled="kirimForm.processing" @click="ajukanUlang">Re-Submit</button>
      </div>
    </Kartu>

    <Modal :terbuka="tambahTerbuka" judul="Add Peserta Induksi" @tutup="tambahTerbuka = false">
      <div class="mn-form">
        <Medan label="Jenis Induction" untuk="ip-jenis">
          <select id="ip-jenis" class="mn-isian" title="Jenis induction"><option>Induksi Baru</option></select>
        </Medan>
        <Medan label="Nama Employee" untuk="ip-nama" wajib :galat="tambah.errors.mcu_orang_id">
          <select v-if="calon.length" id="ip-nama" v-model="tambah.mcu_orang_id" class="mn-isian" title="Nama employee">
            <option value="">— pilih —</option>
            <option v-for="c in calon" :key="c.id" :value="c.id">{{ c.nama }}{{ c.noid ? ` — ${c.noid}` : '' }}{{ c.perusahaan ? ` (${c.perusahaan})` : '' }}</option>
          </select>
          <div v-else class="mn-alert mn-alert-peringatan" style="margin:0">
            <div>
              Belum ada karyawan yang bisa diajukan induksi.<br>
              <span class="mn-redup">Yang muncul di sini hanya karyawan yang MCU-nya sudah <b>disetujui</b>, hasilnya <b>Fit</b> atau
                <b>Fit With Note</b>, dan belum pernah didaftarkan induksi.</span>
              <ul v-if="props.sebabKosong?.length" class="mt-2 list-disc pl-4">
                <li v-for="s in props.sebabKosong" :key="s"><b>{{ s }}</b></li>
              </ul>
            </div>
          </div>
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="tambahTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="tambah.processing || !calon.length || !tambah.mcu_orang_id" @click="simpanPeserta">Save Data</button>
      </template>
    </Modal>

    <Modal :terbuka="kirimTerbuka" judul="Alert Confirmation" @tutup="kirimTerbuka = false">
      <p class="text-[13px]">Are you sure sending Induction? Pengajuan dikirim ke HSE untuk disetujui.</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="kirimTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="kirimForm.processing" @click="kirim">Sending</button>
      </template>
    </Modal>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Alert Reject' : 'Kembalikan untuk diperbaiki'" @tutup="alasanTerbuka = null">
      <Medan label="Remarks" untuk="ia-alasan" wajib :galat="tindakan.errors.keadaan">
        <textarea id="ia-alasan" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="alasanTerbuka = null">Close</button>
        <button type="button" class="mn-btn" :class="alasanTerbuka === 'tolak' ? 'mn-btn-bahaya' : 'mn-btn-peringatan'"
                :disabled="tindakan.processing || !tindakan.catatan.trim()" @click="tindak(alasanTerbuka!)">Sending</button>
      </template>
    </Modal>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
