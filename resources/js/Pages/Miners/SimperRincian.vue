<script setup lang="ts">
/**
 * Detail SIMPER — `detail_simper` Safe Track.
 *
 *   · Data pemegang dan SIM: nomor SIM/Simpol, jenis SIM (termasuk SIO),
 *     email atasan, masa berlaku = tanggal SIM (terbaca/manual/
 *     perpanjangan). Admin dapat mengubah tanggal dan menonaktifkan.
 *   · Berkas wajib: SIM, DDT, permohonan, pengalaman kerja.
 *   · Unit: Master Unit + banyak Type Unit, authority F/R/P/I/T, nilai
 *     P2H dan praktek, berkas teori/praktek/evaluasi. Pengaju mengubahnya
 *     selama draf; admin juga sesudah SIMPER disahkan.
 *   · Unit dari Penambahan Unit yang sudah disetujui (ikut tercetak).
 *   · Persetujuan pengaju → HSE → KTT (stepper), riwayat perpanjangan.
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
import KeadaanKosong from '../../Components/Miners/KeadaanKosong.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import PetunjukSim from '../../Components/Miners/PetunjukSim.vue';
import { tglId } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const s = computed<any>(() => props.simper ?? {});
const unit = computed<any[]>(() => (props.unit ?? []) as any[]);
const tambahan = computed<any[]>(() => (props.unitTambahan ?? []) as any[]);
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);
const authority = computed<Record<string, string>>(() => (props.authority ?? {}) as Record<string, string>);
const dasar = computed(() => `/miners/simper/${s.value.id}`);

/* ─────────── unit ─────────── */

const unitTerbuka = ref<null | 'baru' | number>(null);
const unitForm = useForm({
  kendaraan_id: '' as string | number, jenis_unit_ids: [] as number[], authority: 'F', nilai_p2h: '' as string | number,
  nilai_praktek: '' as string | number, berkas_teori: null as File | null, berkas_praktek: null as File | null, berkas_evaluasi: null as File | null,
});

function bukaUnit(u: any | null) {
  unitForm.reset();
  unitForm.clearErrors();
  if (u) {
    unitForm.kendaraan_id = u.kendaraan_id ?? '';
    unitForm.jenis_unit_ids = [...(u.jenis_ids ?? [])];
    unitForm.authority = u.authority ?? 'F';
    unitForm.nilai_p2h = u.p2h ?? '';
    unitForm.nilai_praktek = u.praktek ?? '';
  }
  unitTerbuka.value = u ? u.id : 'baru';
}

function simpanUnit() {
  const baru = unitTerbuka.value === 'baru';
  unitForm.transform((d) => (baru ? d : { ...d, _method: 'put' }))
    .post(baru ? `${dasar.value}/unit` : `${dasar.value}/unit/${unitTerbuka.value}`, {
      forceFormData: true, preserveScroll: true, onSuccess: () => { unitTerbuka.value = null; },
    });
}

const hapusForm = useForm({});
async function hapusUnit(u: any) {
  if (!await tanya({ judul: 'Hapus unit', pesan: `Hapus unit ${u.kendaraan} dari SIMPER ini?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusForm.delete(`${dasar.value}/unit/${u.id}`, { preserveScroll: true });
}

/* ─────────── ubah SIMPER ─────────── */

const editTerbuka = ref(false);
const edit = useForm({
  tanggal: '', email_atasan: '', no_simpol: '', jenis_simpol: '', simpol_berlaku_sampai: '', simpol_sumber: 'manual',
  berkas_simpol: null as File | null, berkas_ddt: null as File | null, berkas_permohonan: null as File | null, berkas_pengalaman: null as File | null,
});
function bukaEdit() {
  edit.defaults({
    tanggal: s.value.tanggal ?? '', email_atasan: s.value.emailAtasan ?? '', no_simpol: s.value.noSim ?? '',
    jenis_simpol: s.value.jenisSim ?? '', simpol_berlaku_sampai: '', simpol_sumber: 'manual',
    berkas_simpol: null, berkas_ddt: null, berkas_permohonan: null, berkas_pengalaman: null,
  });
  edit.reset();
  edit.clearErrors();
  editTerbuka.value = true;
}
function simpanEdit() {
  edit.transform((d) => ({ ...d, _method: 'put' })).post(dasar.value, {
    forceFormData: true, preserveScroll: true, onSuccess: () => { editTerbuka.value = false; },
  });
}

/* ─────────── masa berlaku & status ─────────── */

const masaTerbuka = ref(false);
const masaForm = useForm({ sumber: 'manual', tanggal: '' });
function bukaMasa(usulan?: string) {
  masaForm.sumber = 'manual';
  masaForm.tanggal = (typeof usulan === 'string' ? usulan : null) ?? s.value.berlaku ?? '';
  masaTerbuka.value = true;
}
function simpanMasa() {
  masaForm.post(`${dasar.value}/masa-berlaku`, { preserveScroll: true, onSuccess: () => { masaTerbuka.value = false; } });
}

const statusTerbuka = ref(false);
const statusForm = useForm({ aktif: false as boolean, alasan: '' });
function bukaStatus() {
  statusForm.aktif = !s.value.aktif;
  statusForm.alasan = '';
  statusTerbuka.value = true;
}
function simpanStatus() {
  statusForm.post(`${dasar.value}/status`, { preserveScroll: true, onSuccess: () => { statusTerbuka.value = false; } });
}

/* ─────────── alur ─────────── */

const kirimForm = useForm({});
const kirimTerbuka = ref(false);
function kirim() {
  kirimForm.post(`${dasar.value}/kirim`, { preserveScroll: true, onFinish: () => { kirimTerbuka.value = false; } });
}
function ajukanUlang() {
  kirimForm.post(`${dasar.value}/ajukan-ulang`, { preserveScroll: true });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);
async function tindak(keadaan: string) {
  if (keadaan === 'setuju' && !await tanya({ judul: 'Confirm Approved?', labelAksi: 'Approve' })) return;
  tindakan.keadaan = keadaan;
  tindakan.post(`${dasar.value}/tindak`, { preserveScroll: true, onSuccess: () => { alasanTerbuka.value = null; tindakan.reset('catatan'); } });
}

const jenisTeks = (u: any) => (u.jenis ?? []).join(', ') || '—';
</script>

<template>
  <div>
    <KepalaHalaman judul="Detail SIMPER" :remah="[['Home', '/miners/dasbor'], ['SIMPER', '/miners/simper'], [s.nomor || 'Detail', null]]">
      <template #aksi><Link href="/miners/simper" class="mn-btn mn-btn-garis">← Kembali</Link></template>
    </KepalaHalaman>

    <div v-for="k in ['kirim', 'ajukanUlang', 'keadaan', 'sumber', 'alasan']" :key="k">
      <div v-if="galat[k]" class="mn-alert mn-alert-bahaya" role="alert">{{ galat[k] }}</div>
    </div>

    <KepalaProfil :judul="s.nama || '—'" :eyebrow="`SIMPER · ${s.nomor || 'tanpa nomor'}`" :foto="s.foto?.url ?? null"
                  :keterangan="[['NIK', s.nik], ['No ID', s.noid], ['Jabatan', s.jabatan], ['Perusahaan', s.perusahaan]]">
      <template #lencana>
        <LencanaStatus :status="s.status" />
        <span v-if="!s.aktif" class="mn-lencana mn-l-abu mn-titik" :title="s.alasanNonaktif || ''">non-aktif</span>
        <LencanaMasaBerlaku :tanggal="s.berlaku" :sisa="s.sisa" :sumber="s.sumber" />
      </template>
      <template #aksi>
        <TombolIkon v-if="props.bolehUbah" ikon="pensil" judul="Edit SIMPER" @click="bukaEdit" />
        <TombolIkon v-if="props.kelola" ikon="kalender" varian="garis" judul="Masa berlaku SIM" @click="bukaMasa()" />
        <TombolIkon v-if="props.kelola" ikon="orang" varian="garis" :judul="s.aktif ? 'Nonaktifkan SIMPER' : 'Aktifkan kembali'" @click="bukaStatus" />
      </template>
    </KepalaProfil>

    <div v-if="!s.aktif" class="mn-langkah mn-langkah-tolak">
      <div>
        <div class="mn-langkah-label">Tidak aktif sejak {{ tglId(s.nonaktifSejak) }}</div>
        <div class="mn-langkah-kurang">{{ s.alasanNonaktif || 'Tanpa keterangan.' }}</div>
      </div>
    </div>

    <div v-if="s.usulanSim" class="mn-alert mn-alert-peringatan" role="status">
      <b>Usulan masa berlaku SIM dari berkas: {{ tglId(s.usulanSim.tanggal) }}.</b>
      {{ s.usulanSim.pesan }} Belum diterapkan — tanggal SIM baru berlaku setelah disimpan.
      <button v-if="props.kelola" type="button" class="mn-btn mn-btn-garis mn-btn-kecil ml-2" @click="bukaMasa(s.usulanSim.tanggal)">Terapkan usulan</button>
    </div>

    <div v-if="props.bolehTindak" class="mn-bilah-aksi">
      <div class="mn-bilah-aksi-teks"><b>Menunggu persetujuan Anda</b>Periksa data, berkas, dan unit di bawah, lalu approve, kembalikan, atau reject.</div>
      <div class="mn-bilah-aksi-tombol">
        <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">approve</button>
        <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
        <button type="button" class="mn-btn mn-btn-garis-bahaya" @click="alasanTerbuka = 'tolak'">reject</button>
      </div>
    </div>

    <Kartu judul="Detail SIMPER">
      <h3 class="mn-sub-bagian">Data pemegang</h3>
      <DaftarDefinisi>
        <Butir label="Tanggal" :nilai="tglId(s.tanggal)" />
        <Butir label="No SIMPER" :nilai="s.nomor" />
        <Butir label="Nama Employee">
          <Link v-if="s.pekerja_id" :href="`/miners/${s.pekerja_id}`" class="mn-tautan">{{ s.nama }}</Link><template v-else>{{ s.nama || '—' }}</template>
        </Butir>
        <Butir label="Noid" :nilai="s.noid" />
        <Butir label="Jabatan" :nilai="s.jabatan" />
        <Butir label="Departemen" :nilai="s.departemen" />
        <Butir label="Mine Permit">
          <Link v-if="s.permit_id" :href="`/miners/permit/${s.permit_id}`" class="mn-tautan">{{ s.permit }}</Link><template v-else>—</template>
        </Butir>
        <Butir label="Email Atasan" :nilai="s.emailAtasan" />
      </DaftarDefinisi>

      <h3 class="mn-sub-bagian">SIM</h3>
      <DaftarDefinisi>
        <Butir label="Simpol" :nilai="s.noSim" />
        <Butir label="Jenis SIM" :nilai="s.jenisSim" />
        <Butir label="Masa Berlaku SIM"><LencanaMasaBerlaku :tanggal="s.berlaku" :sisa="s.sisa" :sumber="s.sumber" /></Butir>
        <Butir v-if="s.kelasLama" label="Kelas (data lama)" :nilai="s.kelasLama" />
      </DaftarDefinisi>
    </Kartu>

    <Kartu judul="Berkas SIMPER" keterangan="Seluruhnya wajib sebelum dikirim">
      <ul class="mn-periksa">
        <ButirLampiran v-for="b in s.berkas" :key="b.kunci" :label="b.label" :wajib="b.wajib" :ada="!!(b.url || b.ada)">
          <ChipBerkas v-if="b.url" :href="b.url" :nama="b.label" />
          <span v-else-if="b.ada" class="mn-lencana mn-l-terang" title="Hanya dapat dibuka admin atau tim OHSE.">terjaga</span>
        </ButirLampiran>
      </ul>
    </Kartu>

    <Kartu judul="Unit SIMPER" :keterangan="`${unit.length} unit`">
      <template #kanan>
        <button v-if="props.bolehUbah" type="button" class="mn-btn mn-btn-garis" @click="bukaUnit(null)">+ add Unit</button>
      </template>
      <KeadaanKosong v-if="!unit.length" judul="Belum ada unit" teks="Tambahkan sedikitnya satu unit sebelum SIMPER dikirim." />
      <table v-else class="mn-tabel">
        <thead><tr><th>Master Unit</th><th>Type Unit</th><th>Authority</th><th>Nilai P2H</th><th>Nilai Praktek</th><th>Berkas</th><th v-if="props.bolehUbah">#</th></tr></thead>
        <tbody>
          <tr v-for="u in unit" :key="u.id">
            <td>{{ u.kendaraan }}<span v-if="u.kode" class="mn-redup"> · {{ u.kode }}</span>
              <div v-if="u.peringatan" class="mn-petunjuk">⚠ {{ u.peringatan }}</div></td>
            <td>{{ jenisTeks(u) }}</td>
            <td><b>{{ u.authority || '—' }}</b></td>
            <td>{{ u.p2h ?? '—' }}</td>
            <td>{{ u.praktek ?? '—' }}</td>
            <td>
              <span class="flex flex-wrap gap-1">
                <ChipBerkas v-if="u.teori_b?.url" :href="u.teori_b.url" nama="Teori" />
                <ChipBerkas v-if="u.praktek_b?.url" :href="u.praktek_b.url" nama="Praktek" />
                <ChipBerkas v-if="u.evaluasi_b?.url" :href="u.evaluasi_b.url" nama="Evaluasi" />
              </span>
            </td>
            <td v-if="props.bolehUbah">
              <div class="mn-aksi mn-aksi-kiri">
                <TombolIkon ikon="pensil" judul="Edit unit" @click="bukaUnit(u)" />
                <TombolIkon ikon="hapus" varian="bahaya" judul="Hapus unit" @click="hapusUnit(u)" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="mn-petunjuk">Authority: F Full · R Restricted · P Probation · I Instructor · T.</p>
    </Kartu>

    <Kartu v-if="tambahan.length" judul="Unit dari Penambahan" keterangan="Disetujui KTT — ikut tercetak pada kartu">
      <table class="mn-tabel">
        <thead><tr><th>Master Unit</th><th>Type Unit</th><th>Authority</th><th>Nilai P2H</th><th>Nilai Praktek</th></tr></thead>
        <tbody>
          <tr v-for="u in tambahan" :key="u.id">
            <td>{{ u.kendaraan }}</td><td>{{ jenisTeks(u) }}</td><td><b>{{ u.authority || '—' }}</b></td><td>{{ u.p2h ?? '—' }}</td><td>{{ u.praktek ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <div v-if="s.status === 'draf' && props.bolehKirim" class="mn-langkah">
      <div>
        <div class="mn-langkah-label">Status: draft — belum dikirim</div>
        <div v-if="props.kurang?.length" class="mn-langkah-kurang">Belum dapat dikirim: {{ props.kurang.join(', ') }}.</div>
        <div v-else class="mn-langkah-teks">Berkas dan unit lengkap. Kirim supaya HSE dan KTT menerima permohonannya.</div>
      </div>
      <button type="button" class="mn-btn mn-btn-utama" :disabled="props.kurang?.length > 0" @click="kirimTerbuka = true">Sending Simper</button>
    </div>

    <Kartu v-if="s.status !== 'draf' || props.bolehAjukanUlang" judul="Persetujuan" keterangan="Pengaju → HSE → KTT">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />
      <div v-if="props.bolehAjukanUlang" class="mt-4">
        <button type="button" class="mn-btn mn-btn-peringatan" :disabled="kirimForm.processing" @click="ajukanUlang">Re-Submit</button>
      </div>
    </Kartu>

    <Kartu v-if="props.ajuan?.length" judul="Pengajuan Lanjutan" :keterangan="`${props.ajuan.length} pengajuan`">
      <table class="mn-tabel">
        <thead><tr><th>No</th><th>Jenis</th><th>Tanggal</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="a in props.ajuan" :key="a.id">
            <td><Link :href="`/miners/simper-lanjutan/${a.id}`" class="mn-tautan">{{ a.nomor || `#${a.id}` }}</Link></td>
            <td>{{ a.jenis }}</td><td>{{ tglId(a.tanggal) }}</td><td><LencanaStatus :status="a.status" /></td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <Kartu v-if="props.riwayat?.length" judul="Riwayat Perpanjangan" :keterangan="`${props.riwayat.length} kali`">
      <table class="mn-tabel">
        <thead><tr><th>Ke-</th><th>Berlaku lama</th><th>Berlaku baru</th><th>SIM lama → baru</th><th>Disetujui</th><th>Oleh</th></tr></thead>
        <tbody>
          <tr v-for="h in props.riwayat" :key="h.urutan">
            <td>{{ h.urutan }}</td><td>{{ tglId(h.lama) }}</td><td>{{ tglId(h.baru) }}</td>
            <td>{{ h.simLama || '—' }} → {{ h.simBaru || '—' }}</td><td>{{ h.pada }}</td><td>{{ h.oleh || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <!-- ══════════ modal ══════════ -->
    <Modal :terbuka="unitTerbuka !== null" :judul="unitTerbuka === 'baru' ? 'Add Unit Simper' : 'Edit Unit Simper'" lebar @tutup="unitTerbuka = null">
      <form class="mn-form" @submit.prevent="simpanUnit">
        <Medan label="Master Unit" untuk="u-kend" wajib :galat="unitForm.errors.kendaraan_id">
          <select id="u-kend" v-model="unitForm.kendaraan_id" class="mn-isian" title="Master Unit">
            <option value="">— pilih —</option>
            <option v-for="k in props.kendaraan" :key="k.id" :value="k.id">{{ k.nama }}{{ k.kode ? ` (${k.kode})` : '' }}</option>
          </select>
        </Medan>
        <Medan label="Authority" untuk="u-auth" wajib :galat="unitForm.errors.authority">
          <select id="u-auth" v-model="unitForm.authority" class="mn-isian" title="Authority">
            <option v-for="(l, k) in authority" :key="k" :value="k">{{ l }}</option>
          </select>
        </Medan>
        <Medan label="Type Unit" wajib penuh :galat="unitForm.errors.jenis_unit_ids" petunjuk="Boleh lebih dari satu.">
          <div class="mn-pilih-banyak" role="group" aria-label="Type Unit">
            <label v-for="t in props.typeUnit" :key="t.id" class="mn-cek">
              <input v-model="unitForm.jenis_unit_ids" type="checkbox" :value="t.id"> {{ t.nama }}
            </label>
          </div>
        </Medan>
        <Medan label="Nilai P2H" untuk="u-p2h" :galat="unitForm.errors.nilai_p2h">
          <input id="u-p2h" v-model="unitForm.nilai_p2h" type="number" min="0" max="100" class="mn-isian">
        </Medan>
        <Medan label="Nilai Praktek" untuk="u-prak" :galat="unitForm.errors.nilai_praktek">
          <input id="u-prak" v-model="unitForm.nilai_praktek" type="number" min="0" max="100" class="mn-isian">
        </Medan>
        <Medan label="Berkas Teori" untuk="u-teori" :wajib="unitTerbuka === 'baru'" :galat="unitForm.errors.berkas_teori">
          <input id="u-teori" type="file" class="mn-isian" @change="unitForm.berkas_teori = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Hasil Praktek" untuk="u-praktek" :wajib="unitTerbuka === 'baru'" :galat="unitForm.errors.berkas_praktek">
          <input id="u-praktek" type="file" class="mn-isian" @change="unitForm.berkas_praktek = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Evaluasi Simper (opsional)" untuk="u-eval" :galat="unitForm.errors.berkas_evaluasi">
          <input id="u-eval" type="file" class="mn-isian" @change="unitForm.berkas_evaluasi = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="unitTerbuka = null">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="unitForm.processing" @click="simpanUnit">Save Data</button>
      </template>
    </Modal>

    <Modal :terbuka="editTerbuka" judul="Edit SIMPER" lebar @tutup="editTerbuka = false">
      <form class="mn-form" @submit.prevent="simpanEdit">
        <Medan label="Tanggal" untuk="e-tgl"><input id="e-tgl" v-model="edit.tanggal" type="date" class="mn-isian"></Medan>
        <Medan label="Email Atasan" untuk="e-email" :galat="edit.errors.email_atasan">
          <input id="e-email" v-model="edit.email_atasan" type="email" class="mn-isian" maxlength="150">
        </Medan>
        <Medan label="Simpol" untuk="e-simpol" :galat="edit.errors.no_simpol"><input id="e-simpol" v-model="edit.no_simpol" class="mn-isian" maxlength="40"></Medan>
        <Medan label="Jenis SIM" untuk="e-jenis" :galat="edit.errors.jenis_simpol">
          <select id="e-jenis" v-model="edit.jenis_simpol" class="mn-isian" title="Jenis SIM">
            <option v-for="j in props.jenisSim" :key="j" :value="j">{{ j }}</option>
          </select>
        </Medan>
        <PetunjukSim v-model:tanggal="edit.simpol_berlaku_sampai" v-model:sumber="edit.simpol_sumber" label="Ganti Berkas SIM" :wajib="false"
                     :galat-berkas="edit.errors.berkas_simpol" :galat-tanggal="edit.errors.simpol_berlaku_sampai" @berkas="edit.berkas_simpol = $event" />
        <Medan label="Ganti File DDT" untuk="e-ddt"><input id="e-ddt" type="file" class="mn-isian" @change="edit.berkas_ddt = ($event.target as HTMLInputElement).files?.[0] ?? null"></Medan>
        <Medan label="Ganti Permohonan" untuk="e-mohon"><input id="e-mohon" type="file" class="mn-isian" @change="edit.berkas_permohonan = ($event.target as HTMLInputElement).files?.[0] ?? null"></Medan>
        <Medan label="Ganti Pengalaman Kerja" untuk="e-alaman"><input id="e-alaman" type="file" class="mn-isian" @change="edit.berkas_pengalaman = ($event.target as HTMLInputElement).files?.[0] ?? null"></Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="editTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="edit.processing" @click="simpanEdit">Update</button>
      </template>
    </Modal>

    <Modal :terbuka="masaTerbuka" judul="Masa Berlaku SIMPER" @tutup="masaTerbuka = false">
      <div class="mn-form">
        <Medan label="Sumber" untuk="sm-sumber" penuh>
          <select id="sm-sumber" v-model="masaForm.sumber" class="mn-isian" title="Sumber">
            <option value="manual">Isi tanggal SIM manual</option>
            <option value="auto">Baca ulang dari berkas SIM</option>
          </select>
        </Medan>
        <Medan v-if="masaForm.sumber === 'manual'" label="Berlaku sampai" untuk="sm-tgl" wajib penuh :galat="masaForm.errors.tanggal">
          <input id="sm-tgl" v-model="masaForm.tanggal" type="date" class="mn-isian">
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="masaTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="masaForm.processing" @click="simpanMasa">Simpan</button>
      </template>
    </Modal>

    <Modal :terbuka="statusTerbuka" :judul="statusForm.aktif ? 'Aktifkan kembali' : 'Nonaktifkan SIMPER'" @tutup="statusTerbuka = false">
      <Medan v-if="!statusForm.aktif" label="Alasan" untuk="ss-alasan" wajib :galat="statusForm.errors.alasan">
        <input id="ss-alasan" v-model="statusForm.alasan" class="mn-isian" maxlength="200" placeholder="mis. mutasi, SIM dicabut">
      </Medan>
      <p v-else class="text-[13px]">SIMPER kembali dihitung sebagai dokumen aktif.</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="statusTerbuka = false">Close</button>
        <button type="button" class="mn-btn" :class="statusForm.aktif ? 'mn-btn-sukses' : 'mn-btn-bahaya'" :disabled="statusForm.processing" @click="simpanStatus">
          {{ statusForm.aktif ? 'Aktifkan' : 'Nonaktifkan' }}
        </button>
      </template>
    </Modal>

    <Modal :terbuka="kirimTerbuka" judul="Alert Confirmation" @tutup="kirimTerbuka = false">
      <p class="text-[13px]">Are you sure sending SIMPER? Permohonan dikirim ke HSE, lalu KTT.</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="kirimTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="kirimForm.processing" @click="kirim">Sending</button>
      </template>
    </Modal>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Reject SIMPER' : 'Kembalikan untuk diperbaiki'" @tutup="alasanTerbuka = null">
      <Medan label="Remarks" untuk="sa-alasan" wajib :galat="tindakan.errors.keadaan">
        <textarea id="sa-alasan" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
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
