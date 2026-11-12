<script setup lang="ts">
/**
 * Detail Permit — `detail_permit` Safe Track.
 *
 *   · Detail Permit — foto, data pemegang, IUP dan Contractor, KTP,
 *     permohonan, sertifikat dan hasil MCU, masa berlaku (ikut MCU,
 *     dikunci, atau perpanjangan). Edit HANYA admin/HSE IUP; ganti foto
 *     pemiliknya atau admin.
 *   · Attachment Permit — SPDK (wajib), Induksi Khusus dan LOTO /
 *     Sertifikat Welder (opsional). Berkas dapat dikirim begitu SPDK ada.
 *   · Persetujuan — pengaju → HSE → KTT (stepper).
 *
 * Rupa: kepala profil + daftar definisi bersekat + chip berkas + daftar
 * periksa lampiran, menggantikan kotak isian abu-abu Safe Track; tombol
 * penyetuju di bilah aksi (lengket di bawah pada ponsel).
 *   · Riwayat perpanjangan kartu ini.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import TabelPersetujuan from '../../Components/Miners/TabelPersetujuan.vue';
import KepalaProfil from '../../Components/Miners/KepalaProfil.vue';
import DaftarDefinisi from '../../Components/Miners/DaftarDefinisi.vue';
import Butir from '../../Components/Miners/Butir.vue';
import ChipBerkas from '../../Components/Miners/ChipBerkas.vue';
import ButirLampiran from '../../Components/Miners/ButirLampiran.vue';
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

const p = computed<any>(() => props.permit ?? {});
const lampiran = computed<any[]>(() => (props.lampiran ?? []) as any[]);
const pilihan = computed<any>(() => props.pilihan ?? {});
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);

/* ─────────── lampiran ─────────── */

const lampiranTerbuka = ref(false);
const lampiranForm = useForm<Record<string, File | null>>({ spdk: null, 'induksi-khusus': null, loto: null });

function simpanLampiran() {
  lampiranForm.post(`/miners/permit/${p.value.id}/berkas`, {
    forceFormData: true, preserveScroll: true, onSuccess: () => { lampiranTerbuka.value = false; lampiranForm.reset(); },
  });
}

/* ─────────── foto ─────────── */

const fotoTerbuka = ref(false);
const fotoForm = useForm({ foto: null as File | null });
function simpanFoto() {
  fotoForm.post(`/miners/permit/${p.value.id}/foto`, { forceFormData: true, preserveScroll: true, onSuccess: () => { fotoTerbuka.value = false; } });
}

/* ─────────── edit (admin) ─────────── */

const editTerbuka = ref(false);
const edit = useForm({
  iup_id: '' as string | number, kontraktor_id: '' as string | number, jenis_kartu: '', cakupan_area: '', kode_warna: '',
  departemen_id: '' as string | number, tanggal_lahir: '', gol_darah: '', telepon: '', telepon_darurat: '', jabatan: '', catatan: '',
  ktp: null as File | null, permohonan: null as File | null,
});

function bukaEdit() {
  edit.defaults({
    iup_id: p.value.iup_id ?? '', kontraktor_id: p.value.kontraktor_id ?? '', jenis_kartu: p.value.jenisKartu ?? 'Mine Permit',
    cakupan_area: p.value.cakupan ?? 'FULL', kode_warna: p.value.warna ?? '', departemen_id: p.value.departemen_id ?? '',
    tanggal_lahir: p.value.lahir ?? '', gol_darah: p.value.darah ?? '', telepon: p.value.telepon ?? '',
    telepon_darurat: p.value.darurat ?? '', jabatan: p.value.jabatan ?? '', catatan: p.value.catatan ?? '', ktp: null, permohonan: null,
  });
  edit.reset();
  edit.clearErrors();
  editTerbuka.value = true;
}

function simpanEdit() {
  edit.transform((d) => ({ ...d, _method: 'put' })).post(`/miners/permit/${p.value.id}`, {
    forceFormData: true, preserveScroll: true, onSuccess: () => { editTerbuka.value = false; },
  });
}

/* ─────────── masa berlaku & status ─────────── */

const masaTerbuka = ref(false);
const masaForm = useForm({ sumber: 'mcu', tanggal: '' });
function bukaMasa() {
  masaForm.sumber = p.value.sumberKode === 'manual' ? 'manual' : 'mcu';
  masaForm.tanggal = p.value.berlaku ?? '';
  masaTerbuka.value = true;
}
function simpanMasa() {
  masaForm.post(`/miners/permit/${p.value.id}/masa-berlaku`, { preserveScroll: true, onSuccess: () => { masaTerbuka.value = false; } });
}

const statusTerbuka = ref(false);
const statusForm = useForm({ aktif: false as boolean, alasan: '' });
function bukaStatus() {
  statusForm.aktif = !p.value.aktif;
  statusForm.alasan = '';
  statusTerbuka.value = true;
}
function simpanStatus() {
  statusForm.post(`/miners/permit/${p.value.id}/status`, { preserveScroll: true, onSuccess: () => { statusTerbuka.value = false; } });
}

/* ─────────── alur ─────────── */

const kirimForm = useForm({});
const kirimTerbuka = ref(false);
function kirim() {
  kirimForm.post(`/miners/permit/${p.value.id}/kirim`, { preserveScroll: true, onFinish: () => { kirimTerbuka.value = false; } });
}
function ajukanUlang() {
  kirimForm.post(`/miners/permit/${p.value.id}/ajukan-ulang`, { preserveScroll: true });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);
async function tindak(keadaan: string) {
  if (keadaan === 'setuju' && !await tanya({ judul: 'Confirm Approved?', labelAksi: 'Approve' })) return;
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/permit/${p.value.id}/tindak`, {
    preserveScroll: true, onSuccess: () => { alasanTerbuka.value = null; tindakan.reset('catatan'); },
  });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Detail Permit" :remah="[['Home', '/miners/dasbor'], ['Mine Permit', '/miners/permit'], [p.nomor || 'Detail', null]]">
      <template #aksi><Link href="/miners/permit" class="mn-btn mn-btn-garis">← Kembali</Link></template>
    </KepalaHalaman>

    <div v-for="k in ['kirim', 'ajukanUlang', 'keadaan', 'spdk', 'sumber']" :key="k">
      <div v-if="galat[k]" class="mn-alert mn-alert-bahaya" role="alert">{{ galat[k] }}</div>
    </div>

    <KepalaProfil :judul="p.nama || '—'" :eyebrow="`${p.jenisKartu || 'Mine Permit'} · ${p.nomor || 'tanpa nomor'}`" :foto="p.foto?.url ?? null"
                  :keterangan="[['NIK', p.nik], ['No ID', p.noid], ['Jabatan', p.jabatan], ['Perusahaan', p.kontraktor || p.iup]]">
      <template #lencana>
        <LencanaStatus :status="p.status" />
        <span v-if="!p.aktif" class="mn-lencana mn-l-abu mn-titik" :title="p.alasanNonaktif || ''">non-aktif</span>
        <LencanaMasaBerlaku :tanggal="p.berlaku" :sisa="p.sisa" :sumber="p.sumber" />
      </template>
      <template #aksi>
        <button v-if="props.bolehFoto" type="button" class="mn-btn mn-btn-garis" @click="fotoTerbuka = true">Ganti foto</button>
        <TombolIkon v-if="props.kelola" ikon="pensil" judul="Edit Mine Permit" @click="bukaEdit" />
        <TombolIkon v-if="props.kelola" ikon="kalender" varian="garis" judul="Masa berlaku" @click="bukaMasa" />
        <TombolIkon v-if="props.kelola" ikon="orang" varian="garis" :judul="p.aktif ? 'Nonaktifkan kartu' : 'Aktifkan kembali'" @click="bukaStatus" />
      </template>
    </KepalaProfil>

    <div v-if="!p.aktif" class="mn-langkah mn-langkah-tolak">
      <div>
        <div class="mn-langkah-label">Tidak aktif sejak {{ tglId(p.nonaktifSejak) }}</div>
        <div class="mn-langkah-kurang">{{ p.alasanNonaktif || 'Tanpa keterangan.' }}</div>
      </div>
    </div>

    <div v-if="props.bolehTindak" class="mn-bilah-aksi">
      <div class="mn-bilah-aksi-teks"><b>Menunggu persetujuan Anda</b>Periksa data dan lampiran di bawah, lalu approve, kembalikan, atau reject.</div>
      <div class="mn-bilah-aksi-tombol">
        <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">approve</button>
        <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
        <button type="button" class="mn-btn mn-btn-garis" @click="alasanTerbuka = 'tolak'">reject</button>
      </div>
    </div>

    <Kartu judul="Detail Permit">
      <h3 class="mn-sub-bagian">Data pemegang</h3>
      <DaftarDefinisi>
        <Butir label="Tanggal" :nilai="tglId(p.tanggal)" />
        <Butir label="No Register" :nilai="p.nomor" />
        <Butir label="NIK" :nilai="p.nik" />
        <Butir label="Nama Lengkap">
          <Link v-if="p.pekerja_id" :href="`/miners/${p.pekerja_id}`" class="mn-tautan">{{ p.nama }}</Link><template v-else>{{ p.nama || '—' }}</template>
        </Butir>
        <Butir label="Jabatan" :nilai="p.jabatan" />
        <Butir label="Tanggal Lahir" :nilai="tglId(p.lahir)" />
        <Butir label="Golongan Darah" :nilai="p.darah" />
        <Butir label="Nomor Induk" :nilai="p.noid" />
        <Butir label="Phone" :nilai="p.telepon" />
        <Butir label="Emergency" :nilai="p.darurat" />
      </DaftarDefinisi>

      <h3 class="mn-sub-bagian">Kartu</h3>
      <DaftarDefinisi>
        <Butir label="Type Card" :nilai="p.jenisKartu" />
        <Butir label="Cakupan Area / Warna" :nilai="`${p.cakupan || '—'} · ${p.warna || 'ikut unit SIMPER'}`" />
        <Butir label="Perusahaan (IUP)" :nilai="p.iup" />
        <Butir label="Contractor (IUJP)" :nilai="p.kontraktor || '— karyawan IUP —'" />
        <Butir label="Departemen" :nilai="p.departemen" />
        <Butir label="Masa Berlaku"><LencanaMasaBerlaku :tanggal="p.berlaku" :sisa="p.sisa" :sumber="p.sumber" /></Butir>
      </DaftarDefinisi>

      <h3 class="mn-sub-bagian">MCU dan berkas</h3>
      <DaftarDefinisi>
        <Butir label="Expired MCU" :nilai="`${tglId(p.mcuSampai)} · ${p.mcuHasil || (props.medis === false ? 'hasil terjaga' : 'Belum dinilai')}`" />
        <Butir label="Sertifikat MCU">
          <span class="flex flex-wrap gap-2">
            <ChipBerkas v-if="p.sertifikatMcu" :href="p.sertifikatMcu" nama="Sertifikat MCU" />
            <ChipBerkas v-if="p.hasilMcu?.url" :href="p.hasilMcu.url" nama="Hasil MCU" />
            <span v-if="!p.sertifikatMcu && !p.hasilMcu?.url" class="mn-redup">—</span>
          </span>
        </Butir>
        <Butir label="KTP">
          <ChipBerkas v-if="p.ktp?.url" :href="p.ktp.url" nama="KTP" />
          <span v-else class="mn-redup">{{ p.ktp?.ada ? 'terjaga — hanya admin dan tim OHSE' : '—' }}</span>
        </Butir>
        <Butir label="Permohonan Permit">
          <ChipBerkas v-if="p.permohonan?.url" :href="p.permohonan.url" nama="Permohonan Permit" />
          <span v-else class="mn-redup">—</span>
        </Butir>
        <Butir label="Catatan" :nilai="p.catatan" penuh />
        <Butir label="Cetak Mine Permit" penuh>
          <span class="mn-redup">Kartu CR80 dan cover dicetak lewat Monitoring → Cetak Kartu sesudah kartunya terbit.</span>
        </Butir>
      </DaftarDefinisi>
    </Kartu>

    <Kartu judul="Attachment Permit" keterangan="SPDK wajib; lainnya opsional">
      <template #kanan>
        <button v-if="props.bolehLampiran" type="button" class="mn-btn mn-btn-garis" @click="lampiranTerbuka = true">+ Attachment</button>
      </template>
      <ul class="mn-periksa">
        <ButirLampiran v-for="l in lampiran" :key="l.kunci" :label="l.label" :wajib="l.wajib" :ada="!!(l.url || l.ada)">
          <ChipBerkas v-if="l.url" :href="l.url" :nama="l.label" />
          <span v-else-if="l.ada" class="mn-lencana mn-l-terang" title="Hanya dapat dibuka paramedis, tim OHSE, atau administrator.">terjaga</span>
        </ButirLampiran>
      </ul>
      <div v-if="props.kurang?.length" class="mn-alert mn-alert-peringatan mt-3 mb-0">
        Berkas belum dapat dikirim: lampiran wajib {{ props.kurang.join(', ') }} belum diunggah.
      </div>
      <div v-if="props.lampiranLama?.length" class="mt-4">
        <h3 class="mn-sub-bagian">Lampiran lama (daftar SOP sebelumnya)</h3>
        <div class="flex flex-wrap gap-2">
          <template v-for="b in props.lampiranLama" :key="b.label">
            <ChipBerkas v-if="b.url" :href="b.url" :nama="b.label" />
            <span v-else class="mn-lencana mn-l-terang mn-bungkus">{{ b.label }} · {{ b.ada ? 'terjaga' : 'tanpa berkas' }}</span>
          </template>
        </div>
      </div>
    </Kartu>

    <div v-if="p.status === 'draf' && props.bolehKirim" class="mn-langkah">
      <div>
        <div class="mn-langkah-label">Status: draft — belum dikirim</div>
        <div v-if="props.kurang?.length" class="mn-langkah-kurang">Unggah lampiran SPDK lebih dulu, barulah Mine Permit ini bisa dikirim ke HSE.</div>
        <div v-else class="mn-langkah-teks">Lampiran wajib lengkap. Kirim supaya HSE dan KTT menerima permohonannya.</div>
      </div>
      <button type="button" class="mn-btn mn-btn-utama" :disabled="props.kurang?.length > 0" @click="kirimTerbuka = true">Sending Permit</button>
    </div>

    <Kartu v-if="p.status !== 'draf' || props.bolehAjukanUlang" judul="Persetujuan" keterangan="Pengaju → HSE → KTT">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />
      <div v-if="props.bolehAjukanUlang || (['terbit', 'kedaluwarsa'].includes(p.status) && p.aktif)" class="mt-4 flex flex-wrap gap-2">
        <button v-if="props.bolehAjukanUlang" type="button" class="mn-btn mn-btn-peringatan" :disabled="kirimForm.processing" @click="ajukanUlang">Re-Submit</button>
        <Link v-if="['terbit', 'kedaluwarsa'].includes(p.status) && p.aktif" href="/miners/perpanjangan-permit" class="mn-btn mn-btn-garis-utama">Perpanjang Mine Permit</Link>
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

    <!-- ══════════ modal ══════════ -->
    <Modal :terbuka="lampiranTerbuka" judul="Attachment Permit" @tutup="lampiranTerbuka = false">
      <div class="mn-form">
        <Medan v-for="l in lampiran" :key="l.kunci" :label="l.label + (l.wajib ? '' : ' (opsional)')" :untuk="`lp-${l.kunci}`" :wajib="l.wajib && !l.ada"
               :galat="lampiranForm.errors[l.kunci]" penuh :petunjuk="l.ada ? 'Sudah diunggah — pilih berkas baru untuk mengganti.' : null">
          <input :id="`lp-${l.kunci}`" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                 @change="lampiranForm[l.kunci] = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="lampiranTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="lampiranForm.processing" @click="simpanLampiran">Save Data</button>
      </template>
    </Modal>

    <Modal :terbuka="fotoTerbuka" judul="Ganti Foto" @tutup="fotoTerbuka = false">
      <Medan label="Foto Employee" untuk="f-foto" wajib :galat="fotoForm.errors.foto" petunjuk="JPG atau PNG, maksimal 2 MB.">
        <input id="f-foto" type="file" class="mn-isian" accept=".jpg,.jpeg,.png" @change="fotoForm.foto = ($event.target as HTMLInputElement).files?.[0] ?? null">
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="fotoTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="fotoForm.processing || !fotoForm.foto" @click="simpanFoto">Upload</button>
      </template>
    </Modal>

    <Modal :terbuka="editTerbuka" judul="Edit Mine Permit" lebar @tutup="editTerbuka = false">
      <form v-if="pilihan" class="mn-form" @submit.prevent="simpanEdit">
        <Medan label="Perusahaan (IUP)" untuk="e-iup" wajib :galat="edit.errors.iup_id">
          <select id="e-iup" v-model="edit.iup_id" class="mn-isian" title="Perusahaan (IUP)">
            <option v-for="c in pilihan.iup" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </Medan>
        <Medan label="Contractor (IUJP)" untuk="e-k" :galat="edit.errors.kontraktor_id">
          <select id="e-k" v-model="edit.kontraktor_id" class="mn-isian" title="Contractor (IUJP)">
            <option value="">— Karyawan IUP, tanpa kontraktor —</option>
            <option v-for="c in pilihan.kontraktor" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </Medan>
        <Medan label="Type Card" untuk="e-jenis" wajib :galat="edit.errors.jenis_kartu">
          <select id="e-jenis" v-model="edit.jenis_kartu" class="mn-isian" title="Type card">
            <option v-for="j in pilihan.jenisKartu" :key="j" :value="j">{{ j }}</option>
          </select>
        </Medan>
        <Medan label="Cakupan Area" untuk="e-area" :galat="edit.errors.cakupan_area">
          <select id="e-area" v-model="edit.cakupan_area" class="mn-isian" title="Cakupan area">
            <option v-for="c in pilihan.cakupan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </Medan>
        <Medan label="Kode Warna Akses" untuk="e-warna" :galat="edit.errors.kode_warna">
          <select id="e-warna" v-model="edit.kode_warna" class="mn-isian" title="Kode warna">
            <option value="">Ikuti unit SIMPER (otomatis)</option>
            <option v-for="w in pilihan.warna" :key="w.nilai" :value="w.nilai">{{ w.label }}</option>
          </select>
        </Medan>
        <Medan label="Departemen" untuk="e-dept" wajib :galat="edit.errors.departemen_id">
          <select id="e-dept" v-model="edit.departemen_id" class="mn-isian" title="Departemen">
            <option v-for="d in pilihan.departemen" :key="d.nilai" :value="d.nilai">{{ d.label }}</option>
          </select>
        </Medan>
        <Medan label="Jabatan" untuk="e-jab" :galat="edit.errors.jabatan">
          <input id="e-jab" v-model="edit.jabatan" class="mn-isian" maxlength="120">
        </Medan>
        <Medan label="Tanggal Lahir" untuk="e-lahir" :galat="edit.errors.tanggal_lahir">
          <input id="e-lahir" v-model="edit.tanggal_lahir" type="date" class="mn-isian">
        </Medan>
        <Medan label="Golongan Darah" untuk="e-darah" :galat="edit.errors.gol_darah">
          <select id="e-darah" v-model="edit.gol_darah" class="mn-isian" title="Golongan darah">
            <option value="">—</option>
            <option v-for="g in pilihan.golDarah" :key="g" :value="g">{{ g }}</option>
          </select>
        </Medan>
        <Medan label="Phone" untuk="e-telp"><input id="e-telp" v-model="edit.telepon" class="mn-isian" maxlength="30"></Medan>
        <Medan label="Emergency" untuk="e-telp2"><input id="e-telp2" v-model="edit.telepon_darurat" class="mn-isian" maxlength="30"></Medan>
        <Medan label="Ganti KTP" untuk="e-ktp" :galat="edit.errors.ktp">
          <input id="e-ktp" type="file" class="mn-isian" @change="edit.ktp = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Ganti Permohonan" untuk="e-mohon" :galat="edit.errors.permohonan">
          <input id="e-mohon" type="file" class="mn-isian" @change="edit.permohonan = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Catatan" untuk="e-catatan" penuh><textarea id="e-catatan" v-model="edit.catatan" class="mn-isian" rows="2" /></Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="editTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="edit.processing" @click="simpanEdit">Update</button>
      </template>
    </Modal>

    <Modal :terbuka="masaTerbuka" judul="Masa Berlaku Mine Permit" @tutup="masaTerbuka = false">
      <div class="mn-form">
        <Medan label="Sumber" untuk="pm-sumber" penuh>
          <select id="pm-sumber" v-model="masaForm.sumber" class="mn-isian" title="Sumber masa berlaku">
            <option value="mcu">Ikuti masa berlaku MCU ({{ tglId(p.mcuSampai) }})</option>
            <option value="manual">Kunci tanggal manual</option>
          </select>
        </Medan>
        <Medan v-if="masaForm.sumber === 'manual'" label="Berlaku sampai" untuk="pm-tgl" wajib penuh :galat="masaForm.errors.tanggal">
          <input id="pm-tgl" v-model="masaForm.tanggal" type="date" class="mn-isian">
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="masaTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="masaForm.processing" @click="simpanMasa">Simpan</button>
      </template>
    </Modal>

    <Modal :terbuka="statusTerbuka" :judul="statusForm.aktif ? 'Aktifkan kembali' : 'Nonaktifkan Mine Permit'" @tutup="statusTerbuka = false">
      <Medan v-if="!statusForm.aktif" label="Alasan" untuk="ps-alasan" :galat="statusForm.errors.alasan">
        <input id="ps-alasan" v-model="statusForm.alasan" class="mn-isian" maxlength="200" placeholder="mis. kartu hilang, mutasi">
      </Medan>
      <p v-else class="text-[13px]">Kartu kembali dihitung sebagai dokumen aktif.</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="statusTerbuka = false">Close</button>
        <button type="button" class="mn-btn" :class="statusForm.aktif ? 'mn-btn-sukses' : 'mn-btn-bahaya'" :disabled="statusForm.processing" @click="simpanStatus">
          {{ statusForm.aktif ? 'Aktifkan' : 'Nonaktifkan' }}
        </button>
      </template>
    </Modal>

    <Modal :terbuka="kirimTerbuka" judul="Alert Confirmation" @tutup="kirimTerbuka = false">
      <p class="text-[13px]">Are you sure sending Mine Permit? Permohonan dikirim ke HSE, lalu KTT.</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="kirimTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="kirimForm.processing" @click="kirim">Sending</button>
      </template>
    </Modal>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Reject Mine Permit' : 'Kembalikan untuk diperbaiki'" @tutup="alasanTerbuka = null">
      <Medan label="Remarks" untuk="pa-alasan" wajib :galat="tindakan.errors.keadaan">
        <textarea id="pa-alasan" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
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
