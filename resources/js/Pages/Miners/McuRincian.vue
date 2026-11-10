<script setup lang="ts">
/**
 * Detail MCU — `detail_mcu_user` dan `detail_mcu_admin` Safe Track.
 *
 *   · Detail MCU — nomor, tanggal, judul, kepada, pembuat, perusahaan.
 *   · Manpower Table — "Add Manpower" langsung dengan NIK (pengaju, draf),
 *     hasil MCU per orang (HANYA dokter), "kembalikan untuk diperbaiki"
 *     per orang, surat rujukan, dan cetak sertifikat sesudah disetujui.
 *   · Panel langkah — draf yang belum dikirim mengatakan apa yang kurang.
 *   · Persetujuan — tabel alur; dokter menyetujui dengan memilih masa
 *     berlaku 3 / 6 / 12 bulan, dan tombolnya tertahan selama masih ada
 *     nama yang "Belum Dinilai" — alasannya dikatakan.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const m = computed<any>(() => props.mcu ?? {});
const orang = computed<any[]>(() => (props.orang ?? []) as any[]);
const pilihan = computed<any>(() => props.pilihan ?? {});
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);

const rujukanSemua = computed(() => orang.value.flatMap((o) => (o.rujukan ?? []).map((j: any) => ({ ...j, nama: o.nama }))));

const kelasHasil: Record<string, string> = {
  fit: 'mn-l-sukses', catatan: 'mn-l-kuning', tidak: 'mn-l-bahaya', rujukan: 'mn-l-info', belum: 'mn-l-abu',
};

const batasMb = computed(() => Math.round(Number(props.batasKb ?? 20480) / 1024));

/* ─────────── manpower ─────────── */

const orangTerbuka = ref(false);
const orangDiubah = ref<any>(null);

const kosong = {
  nama: '', nik: '', usia: '' as string | number, tanggal_periksa: '', jabatan: '', departemen_id: '' as string | number,
  jenis_mcu: 'Awal', level_risiko: 'Rendah', catatan_mitra: '', berlaku_sampai: '',
  berkas_hasil: null as File | null, berkas_rujukan: null as File | null,
};

const orangForm = useForm({ ...kosong });

function tambahOrang() {
  orangDiubah.value = null;
  orangForm.defaults({ ...kosong, tanggal_periksa: m.value.tanggal ?? '' });
  orangForm.reset();
  orangForm.clearErrors();
  orangTerbuka.value = true;
}

function ubahOrang(o: any) {
  orangDiubah.value = o;
  orangForm.defaults({
    ...kosong, nama: o.nama ?? '', nik: o.nik ?? '', usia: o.usia ?? '', tanggal_periksa: o.periksa ?? '',
    jabatan: o.jabatan ?? '', departemen_id: o.departemen_id ?? '', jenis_mcu: o.jenis ?? 'Awal',
    level_risiko: o.risiko ?? 'Rendah', catatan_mitra: o.catatanMitra ?? '', berlaku_sampai: o.sampai ?? '',
  });
  orangForm.reset();
  orangForm.clearErrors();
  orangTerbuka.value = true;
}

function pilihBerkas(medan: 'berkas_hasil' | 'berkas_rujukan', e: Event) {
  orangForm[medan] = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function simpanOrang() {
  /* forceFormData: tanpa itu objek File terkirim sebagai `{}` dan
     berkasnya hilang diam-diam. PUT dengan berkas lewat _method. */
  const opsi = { forceFormData: true, preserveScroll: true, onSuccess: () => { orangTerbuka.value = false; } };
  if (orangDiubah.value) {
    orangForm.transform((d) => ({ ...d, _method: 'put' })).post(`/miners/mcu/${m.value.id}/orang/${orangDiubah.value.id}`, opsi);
  } else {
    orangForm.transform((d) => d).post(`/miners/mcu/${m.value.id}/orang`, opsi);
  }
}

const hapusOrangForm = useForm({});
async function hapusOrang(o: any) {
  if (!await tanya({ judul: 'Hapus manpower', pesan: `Hapus ${o.nama} dari surat MCU ini?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusOrangForm.delete(`/miners/mcu/${m.value.id}/orang/${o.id}`, { preserveScroll: true });
}

/* ─────────── hasil (dokter) ─────────── */

const nilaiTerbuka = ref<any>(null);
const nilaiForm = useForm({ hasil_id: '' as string | number, catatan: '' });

function bukaNilai(o: any) {
  nilaiTerbuka.value = o;
  nilaiForm.hasil_id = o.hasil ? o.hasil_id : '';
  nilaiForm.catatan = o.catatan ?? '';
  nilaiForm.clearErrors();
}

function simpanNilai() {
  nilaiForm.post(`/miners/mcu/${m.value.id}/orang/${nilaiTerbuka.value.id}/hasil`, {
    preserveScroll: true, onSuccess: () => { nilaiTerbuka.value = null; },
  });
}

/* ─────────── kembalikan per orang ─────────── */

const perbaikiTerbuka = ref<any>(null);
const perbaikiForm = useForm({ catatan_perbaikan: '' });

function bukaPerbaiki(o: any) {
  perbaikiTerbuka.value = o;
  perbaikiForm.catatan_perbaikan = o.perbaikan ?? '';
  perbaikiForm.clearErrors();
}

function simpanPerbaiki() {
  perbaikiForm.post(`/miners/mcu/${m.value.id}/orang/${perbaikiTerbuka.value.id}/perbaiki`, {
    preserveScroll: true, onSuccess: () => { perbaikiTerbuka.value = null; },
  });
}

/* ─────────── rujukan ─────────── */

const rujukTerbuka = ref<any>(null);
const rujukForm = useForm({
  tanggal_surat: '', dokter: '', rumah_sakit: '', poliklinik: '', diagnosis_awal: '', keterangan: '',
  berkas: null as File | null,
});

function bukaRujuk(o: any) {
  rujukTerbuka.value = o;
  rujukForm.reset();
  rujukForm.tanggal_surat = new Date().toISOString().slice(0, 10);
  rujukForm.clearErrors();
}

function simpanRujuk() {
  rujukForm.post(`/miners/mcu/${m.value.id}/orang/${rujukTerbuka.value.id}/rujukan`, {
    forceFormData: true, preserveScroll: true, onSuccess: () => { rujukTerbuka.value = null; },
  });
}

const hapusRujukForm = useForm({});
async function hapusRujuk(j: any) {
  if (!await tanya({ judul: 'Hapus surat rujukan', pesan: `Hapus surat rujukan ${j.nama} tanggal ${tglId(j.tanggal)}?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusRujukForm.delete(`/miners/mcu/rujukan/${j.id}`, { preserveScroll: true });
}

/* ─────────── surat ─────────── */

const suratTerbuka = ref(false);
const suratForm = useForm({ tanggal: '', kepada: '', perihal: '', catatan: '', company_id: '' as string | number });

function ubahSurat() {
  suratForm.defaults({ tanggal: m.value.tanggal ?? '', kepada: m.value.kepada ?? '', perihal: m.value.perihal ?? '',
    catatan: m.value.catatan ?? '', company_id: m.value.company_id ?? '' });
  suratForm.reset();
  suratTerbuka.value = true;
}

function simpanSurat() {
  suratForm.put(`/miners/mcu/${m.value.id}`, { preserveScroll: true, onSuccess: () => { suratTerbuka.value = false; } });
}

/* ─────────── alur ─────────── */

const kirimForm = useForm({});
const kirimTerbuka = ref(false);

function kirim() {
  kirimForm.post(`/miners/mcu/${m.value.id}/kirim`, { preserveScroll: true, onFinish: () => { kirimTerbuka.value = false; } });
}

function ajukanUlang() {
  kirimForm.post(`/miners/mcu/${m.value.id}/ajukan-ulang`, { preserveScroll: true });
}

const tindakan = useForm({ keadaan: 'setuju', catatan: '', bulan_berlaku: 12 as number });
const setujuTerbuka = ref(false);
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);

function tindak(keadaan: string) {
  tindakan.keadaan = keadaan;
  tindakan.post(`/miners/mcu/${m.value.id}/tindak`, {
    preserveScroll: true,
    onSuccess: () => { setujuTerbuka.value = false; alasanTerbuka.value = null; tindakan.reset('catatan'); },
  });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Detail MCU" :remah="[['Home', '/miners/dasbor'], ['MCU', '/miners/mcu'], [m.nomor || 'Detail', null]]">
      <template #aksi>
        <Link href="/miners/mcu" class="mn-btn mn-btn-garis">← Kembali</Link>
      </template>
    </KepalaHalaman>

    <div v-for="k in ['hapus', 'kirim', 'ajukanUlang', 'keadaan']" :key="k">
      <div v-if="galat[k]" class="mn-alert mn-alert-bahaya" role="alert">{{ galat[k] }}</div>
    </div>

    <!-- ══════════ Detail MCU ══════════ -->
    <Kartu judul="Detail MCU">
      <template #kanan>
        <LencanaStatus :status="m.status" />
        <TombolIkon v-if="props.bolehUbahSurat" ikon="pensil" judul="Ubah surat MCU" @click="ubahSurat" />
      </template>

      <div v-if="m.alasanTolak && ['draf', 'ditolak'].includes(m.status)" class="mn-langkah mn-langkah-tolak">
        <div>
          <div class="mn-langkah-label">{{ m.status === 'ditolak' ? 'Ditolak' : 'Dikembalikan' }} — alasan</div>
          <div class="mn-langkah-kurang">{{ m.alasanTolak }}</div>
        </div>
      </div>

      <div class="mn-baca">
        <Medan label="No Register"><span class="mn-nilai-baca">{{ m.nomor || '—' }}</span></Medan>
        <Medan label="Date MCU"><span class="mn-nilai-baca">{{ tglId(m.tanggal) }}</span></Medan>
        <Medan label="Title"><span class="mn-nilai-baca">{{ m.perihal || '—' }}</span></Medan>
        <Medan label="Kepada"><span class="mn-nilai-baca">{{ m.kepada || '—' }}</span></Medan>
        <Medan label="Creator"><span class="mn-nilai-baca">{{ m.pengaju || '—' }}</span></Medan>
        <Medan label="Perusahaan"><span class="mn-nilai-baca">{{ m.perusahaan || '—' }}</span></Medan>
        <Medan label="IUP penerbit"><span class="mn-nilai-baca">{{ m.iup || '—' }}</span></Medan>
        <Medan label="Masa berlaku dari dokter">
          <span class="mn-nilai-baca">{{ m.bulan ? (m.bulan === 12 ? '1 Tahun' : `${m.bulan} Bulan`) : '—' }}</span>
        </Medan>
        <Medan label="Remarks" penuh><span class="mn-nilai-baca">{{ m.catatan || '' }}</span></Medan>
      </div>
    </Kartu>

    <!-- ══════════ Manpower ══════════ -->
    <Kartu judul="Manpower Table" :keterangan="`${orang.length} manpower`">
      <div v-if="props.bolehTambah" class="mb-3">
        <button type="button" class="mn-btn mn-btn-utama" @click="tambahOrang">+ Add Manpower</button>
      </div>

      <div class="mn-gulir">
        <table class="mn-tabel" style="font-size:12px">
          <thead>
            <tr>
              <th style="width:36px">No</th>
              <th>Date MCU</th>
              <th>Expired MCU</th>
              <th>Name</th>
              <th>Status MCU</th>
              <th>Hasil MCU</th>
              <th>Level Resiko</th>
              <th>MCU</th>
              <th>Rujukan</th>
              <th>Print</th>
              <th>#</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, i) in orang" :key="o.id">
              <td>{{ i + 1 }}</td>
              <td>{{ tglId(o.periksa) }}</td>
              <td>{{ tglId(o.sampai) }}</td>
              <td>
                <Link v-if="o.pekerja_id" :href="`/miners/${o.pekerja_id}`" class="mn-tautan">{{ o.nama }}</Link>
                <span v-else>{{ o.nama }}</span>
                <div class="mn-redup" style="font-size:11px">{{ o.nik }}</div>
                <span v-if="o.perbaikan" class="mn-lencana mn-l-kuning mt-1" :title="o.perbaikan">Perlu Diperbaiki</span>
                <div v-if="o.perbaikan" class="mn-petunjuk" style="color:#b45309">{{ o.perbaikan }}</div>
              </td>
              <td>{{ o.jenis || '—' }}</td>
              <td>
                <span class="mn-lencana" :class="kelasHasil[o.nilaiHasil]">{{ o.hasil || 'Belum dinilai' }}</span>
                <div v-if="o.catatan" class="mn-petunjuk">{{ o.catatan }}</div>
              </td>
              <td>{{ o.risiko || '—' }}</td>
              <td>
                <TombolIkon v-if="o.berkas.hasil.url" ikon="folder" :href="o.berkas.hasil.url" luar judul="Hasil MCU" />
                <span v-else-if="o.berkas.hasil.ada" class="mn-redup" title="Hanya dapat dibuka paramedis, tim OHSE, atau administrator.">terjaga</span>
                <span v-else class="mn-redup">—</span>
              </td>
              <td>
                <TombolIkon v-if="o.berkas.rujukan.url" ikon="folder" :href="o.berkas.rujukan.url" luar judul="Berkas rujukan" />
                <span v-else-if="o.berkas.rujukan.ada" class="mn-redup">terjaga</span>
                <span v-else class="mn-redup">—</span>
              </td>
              <td>
                <TombolIkon v-if="o.cetak" ikon="cetak" varian="bahaya" :href="o.cetak" luar judul="Cetak sertifikat MCU" />
                <span v-else class="mn-redup">—</span>
                <div v-if="o.cetak" class="mt-1"><span class="mn-stempel" :class="`mn-stempel-${o.stempel.nada}`">{{ o.stempel.judul }}</span></div>
              </td>
              <td>
                <div class="mn-aksi" style="justify-content:flex-start;flex-wrap:wrap">
                  <TombolIkon v-if="o.bolehNilai" ikon="cek" varian="sukses" judul="Tetapkan hasil MCU" @click="bukaNilai(o)" />
                  <TombolIkon v-if="o.bolehPerbaiki" ikon="kembali" varian="garis" judul="Kembalikan untuk diperbaiki (bukan hasil medis)" @click="bukaPerbaiki(o)" />
                  <TombolIkon v-if="o.bolehRujuk" ikon="plus" varian="bahaya" judul="Buat surat rujukan" @click="bukaRujuk(o)" />
                  <TombolIkon v-if="o.bolehUbah" ikon="pensil" judul="Ubah data manpower" @click="ubahOrang(o)" />
                  <TombolIkon v-if="o.bolehHapus" ikon="hapus" varian="bahaya" judul="Hapus dari surat" @click="hapusOrang(o)" />
                </div>
              </td>
            </tr>
            <tr v-if="!orang.length">
              <td colspan="11" class="mn-kosong">Belum ada manpower pada MCU ini.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <!-- ══════════ Panel langkah ══════════ -->
    <div v-if="m.status === 'draf' && (props.bolehKirim || props.bolehAjukanUlang)" class="mn-langkah">
      <div>
        <div class="mn-langkah-label">Status: draft — belum dikirim</div>
        <div v-if="!orang.length" class="mn-langkah-kurang">
          Daftar manpower masih kosong. Tambahkan sedikitnya satu karyawan lewat tombol <b>Add Manpower</b> di atas,
          barulah MCU ini bisa dikirim ke dokter.
        </div>
        <div v-else class="mn-langkah-teks">
          {{ orang.length }} manpower sudah terdaftar. Kirim sekarang supaya dokter menerima permohonan validasinya —
          selama belum dikirim, MCU ini tidak muncul di layar siapa pun selain Anda.
        </div>
      </div>
      <button v-if="props.bolehKirim" type="button" class="mn-btn mn-btn-utama" :disabled="!orang.length" @click="kirimTerbuka = true">Kirim ke dokter</button>
      <button v-else type="button" class="mn-btn mn-btn-utama" :disabled="kirimForm.processing" @click="ajukanUlang">Re-Submit MCU</button>
    </div>

    <!-- ══════════ Persetujuan ══════════ -->
    <Kartu v-if="m.status !== 'draf' || props.alur?.some((a: any) => a.keadaan !== 'menunggu')" judul="Persetujuan">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />

      <div v-if="props.bolehTindak && props.langkahDokter && props.belumDinilai > 0" class="mn-alert mn-alert-peringatan mt-3">
        <span><b>{{ props.belumDinilai }} manpower</b> belum ditetapkan hasil MCU-nya. Tetapkan lewat tombol pada kolom
          <b>#</b> di tabel di atas; sesudah semuanya terisi, tombol <b>approve</b> muncul di sini.</span>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <template v-if="props.bolehTindak">
          <button v-if="!props.langkahDokter || props.belumDinilai === 0" type="button" class="mn-btn mn-btn-sukses"
                  @click="props.langkahDokter ? (setujuTerbuka = true) : tindak('setuju')">approve</button>
          <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
          <button type="button" class="mn-btn mn-btn-garis-bahaya" @click="alasanTerbuka = 'tolak'">Reject</button>
        </template>
        <button v-if="props.bolehAjukanUlang && m.status === 'ditolak'" type="button" class="mn-btn mn-btn-utama"
                :disabled="kirimForm.processing" @click="ajukanUlang">Re-Submit MCU</button>
      </div>
    </Kartu>

    <!-- ══════════ Surat rujukan ══════════ -->
    <Kartu v-if="rujukanSemua.length" judul="Surat Rujukan" :keterangan="`${rujukanSemua.length} surat`">
      <div class="mn-gulir">
        <table class="mn-tabel" style="font-size:12px">
          <thead>
            <tr><th style="width:36px">No</th><th>Nama</th><th>Tanggal</th><th>Dokter Spesialis</th><th>Poliklinik</th>
              <th>Rumah Sakit</th><th>Download</th><th>Creator</th><th>#</th></tr>
          </thead>
          <tbody>
            <tr v-for="(j, i) in rujukanSemua" :key="j.id">
              <td>{{ i + 1 }}</td>
              <td>{{ j.nama }}</td>
              <td>{{ tglId(j.tanggal) }}</td>
              <td>{{ j.dokter || '—' }}</td>
              <td>{{ j.poliklinik || '—' }}</td>
              <td>{{ j.rumahSakit || '—' }}</td>
              <td>
                <a v-if="j.surat" :href="j.surat" target="_blank" rel="noopener" class="mn-btn mn-btn-garis">Surat Rujukan</a>
                <span v-else class="mn-redup">—</span>
              </td>
              <td>{{ j.oleh || '—' }}</td>
              <td><TombolIkon v-if="props.bolehUbahSurat" ikon="hapus" varian="bahaya" judul="Hapus surat rujukan" @click="hapusRujuk(j)" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <!-- ══════════ modal: manpower ══════════ -->
    <Modal :terbuka="orangTerbuka" :judul="orangDiubah ? 'Ubah Manpower' : 'MCU Manpower'" lebar @tutup="orangTerbuka = false">
      <form class="mn-form" @submit.prevent="simpanOrang">
        <Medan label="Nama Lengkap" untuk="o-nama" wajib :galat="orangForm.errors.nama">
          <input id="o-nama" v-model="orangForm.nama" class="mn-isian" maxlength="150" required>
        </Medan>
        <Medan label="NIK" untuk="o-nik" wajib :galat="orangForm.errors.nik"
               petunjuk="NIK yang sudah dikenal ditautkan ke manpower yang sama.">
          <input id="o-nik" v-model="orangForm.nik" class="mn-isian" maxlength="40" inputmode="numeric" required>
        </Medan>
        <Medan label="Umur" untuk="o-umur" wajib :galat="orangForm.errors.usia">
          <input id="o-umur" v-model="orangForm.usia" type="number" min="15" max="80" class="mn-isian" required>
        </Medan>
        <Medan label="Date MCU" untuk="o-tgl" wajib :galat="orangForm.errors.tanggal_periksa"
               petunjuk="Masa berlaku MCU dihitung satu tahun dari tanggal ini; dokter menetapkan masa sebenarnya saat menyetujui.">
          <input id="o-tgl" v-model="orangForm.tanggal_periksa" type="date" class="mn-isian" required>
        </Medan>
        <Medan label="Jabatan" untuk="o-jabatan" wajib :galat="orangForm.errors.jabatan">
          <input id="o-jabatan" v-model="orangForm.jabatan" class="mn-isian" maxlength="120" list="o-jabatan-daftar" required>
          <datalist id="o-jabatan-daftar"><option v-for="j in pilihan.jabatan" :key="j" :value="j" /></datalist>
        </Medan>
        <Medan label="Departemen" untuk="o-dept" wajib :galat="orangForm.errors.departemen_id">
          <select id="o-dept" v-model="orangForm.departemen_id" class="mn-isian" title="Departemen" required>
            <option value="">— pilih —</option>
            <option v-for="d in pilihan.departemen" :key="d.nilai" :value="d.nilai">{{ d.label }}</option>
          </select>
        </Medan>
        <Medan label="Status MCU" untuk="o-jenis" wajib :galat="orangForm.errors.jenis_mcu">
          <select id="o-jenis" v-model="orangForm.jenis_mcu" class="mn-isian" title="Status MCU">
            <option v-for="j in pilihan.jenis" :key="j" :value="j">{{ j }}</option>
          </select>
        </Medan>
        <Medan label="Level Resiko" untuk="o-risiko" wajib :galat="orangForm.errors.level_risiko">
          <select id="o-risiko" v-model="orangForm.level_risiko" class="mn-isian" title="Level Resiko">
            <option v-for="r in pilihan.risiko" :key="r" :value="r">{{ r }}</option>
          </select>
        </Medan>
        <Medan label="Hasil MCU" untuk="o-berkas" :wajib="!orangDiubah" :galat="orangForm.errors.berkas_hasil"
               :petunjuk="`JPG, PNG, PDF, DOC atau DOCX — maksimal ${batasMb} MB.${orangDiubah ? ' Kosongkan bila tidak diganti.' : ''}`">
          <input id="o-berkas" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="pilihBerkas('berkas_hasil', $event)">
        </Medan>
        <Medan label="Rujukan" untuk="o-rujukan" :galat="orangForm.errors.berkas_rujukan">
          <input id="o-rujukan" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="pilihBerkas('berkas_rujukan', $event)">
        </Medan>
        <Medan v-if="orangDiubah && props.bolehUbahSurat" label="Expired MCU" untuk="o-exp" :galat="orangForm.errors.berlaku_sampai"
               petunjuk="Khusus admin/HSE IUP: menetapkan masa berlaku tersendiri.">
          <input id="o-exp" v-model="orangForm.berlaku_sampai" type="date" class="mn-isian">
        </Medan>
        <Medan label="Catatan Dokter (jika ada)" untuk="o-catatan" :galat="orangForm.errors.catatan_mitra" penuh>
          <textarea id="o-catatan" v-model="orangForm.catatan_mitra" class="mn-isian" rows="2" />
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="orangTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="orangForm.processing" @click="simpanOrang">Save Manpower</button>
      </template>
    </Modal>

    <!-- ══════════ modal: hasil MCU (dokter) ══════════ -->
    <Modal :terbuka="nilaiTerbuka !== null" judul="Hasil MCU" @tutup="nilaiTerbuka = null">
      <p class="text-[13px] mb-3"><b>{{ nilaiTerbuka?.nama }}</b> — penilaian medis dokter pemeriksa.</p>
      <div class="mn-form">
        <Medan label="Hasil MCU" untuk="n-hasil" wajib :galat="nilaiForm.errors.hasil_id" penuh>
          <select id="n-hasil" v-model="nilaiForm.hasil_id" class="mn-isian" title="Hasil MCU">
            <option value="">— pilih —</option>
            <option v-for="h in pilihan.hasil" :key="h.nilai" :value="h.nilai">{{ h.label }}</option>
          </select>
        </Medan>
        <Medan label="Catatan MCU" untuk="n-catatan" :galat="nilaiForm.errors.catatan" penuh>
          <textarea id="n-catatan" v-model="nilaiForm.catatan" class="mn-isian" rows="2" />
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="nilaiTerbuka = null">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="nilaiForm.processing || !nilaiForm.hasil_id" @click="simpanNilai">Save MCU</button>
      </template>
    </Modal>

    <!-- ══════════ modal: kembalikan per orang ══════════ -->
    <Modal :terbuka="perbaikiTerbuka !== null" judul="Kembalikan untuk Diperbaiki" @tutup="perbaikiTerbuka = null">
      <p class="text-[13px] mb-3 mn-redup">
        Khusus untuk <b>{{ perbaikiTerbuka?.nama }}</b> — nama lain di pengajuan ini TIDAK ikut tertunda. Pakai ini untuk salah
        input/salah unggah berkas, BUKAN untuk menyatakan hasil medis (pakai Hasil MCU untuk itu).
      </p>
      <Medan label="Apa yang perlu diperbaiki" untuk="p-catatan" wajib :galat="perbaikiForm.errors.catatan_perbaikan">
        <textarea id="p-catatan" v-model="perbaikiForm.catatan_perbaikan" class="mn-isian" rows="3" required />
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="perbaikiTerbuka = null">Close</button>
        <button type="button" class="mn-btn mn-btn-peringatan" :disabled="perbaikiForm.processing || !perbaikiForm.catatan_perbaikan.trim()"
                @click="simpanPerbaiki">Kembalikan</button>
      </template>
    </Modal>

    <!-- ══════════ modal: surat rujukan ══════════ -->
    <Modal :terbuka="rujukTerbuka !== null" judul="Surat Rujukan" lebar @tutup="rujukTerbuka = null">
      <p class="text-[13px] mb-3"><b>{{ rujukTerbuka?.nama }}</b></p>
      <form class="mn-form" @submit.prevent="simpanRujuk">
        <Medan label="Tanggal Surat" untuk="r-tgl" wajib :galat="rujukForm.errors.tanggal_surat">
          <input id="r-tgl" v-model="rujukForm.tanggal_surat" type="date" class="mn-isian" required>
        </Medan>
        <Medan label="Dokter Spesialis" untuk="r-dokter" wajib :galat="rujukForm.errors.dokter">
          <input id="r-dokter" v-model="rujukForm.dokter" class="mn-isian" maxlength="150" required>
        </Medan>
        <Medan label="Rumah Sakit" untuk="r-rs" wajib :galat="rujukForm.errors.rumah_sakit">
          <input id="r-rs" v-model="rujukForm.rumah_sakit" class="mn-isian" maxlength="200" required>
        </Medan>
        <Medan label="Poliklinik" untuk="r-poli" wajib :galat="rujukForm.errors.poliklinik">
          <input id="r-poli" v-model="rujukForm.poliklinik" class="mn-isian" maxlength="150" required>
        </Medan>
        <Medan label="Diagnosis Awal" untuk="r-diag" wajib :galat="rujukForm.errors.diagnosis_awal">
          <textarea id="r-diag" v-model="rujukForm.diagnosis_awal" class="mn-isian" rows="2" required />
        </Medan>
        <Medan label="Keterangan" untuk="r-ket" wajib :galat="rujukForm.errors.keterangan">
          <textarea id="r-ket" v-model="rujukForm.keterangan" class="mn-isian" rows="2" required />
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="rujukTerbuka = null">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="rujukForm.processing" @click="simpanRujuk">Save Rujukan</button>
      </template>
    </Modal>

    <!-- ══════════ modal: ubah surat ══════════ -->
    <Modal :terbuka="suratTerbuka" judul="Edit MCU" @tutup="suratTerbuka = false">
      <form class="mn-form" @submit.prevent="simpanSurat">
        <Medan label="Tanggal MCU" untuk="s-tgl" wajib :galat="suratForm.errors.tanggal"
               petunjuk="Masa berlaku bawaan (tanggal + 1 tahun) ikut bergeser; yang sudah ditetapkan tersendiri tidak.">
          <input id="s-tgl" v-model="suratForm.tanggal" type="date" class="mn-isian" required>
        </Medan>
        <Medan label="Kepada" untuk="s-kepada" wajib :galat="suratForm.errors.kepada">
          <input id="s-kepada" v-model="suratForm.kepada" class="mn-isian" maxlength="200" required>
        </Medan>
        <Medan label="Title" untuk="s-title" :galat="suratForm.errors.perihal" penuh>
          <input id="s-title" v-model="suratForm.perihal" class="mn-isian" maxlength="500">
        </Medan>
        <Medan label="Perusahaan" untuk="s-pt" :galat="suratForm.errors.company_id" penuh>
          <select id="s-pt" v-model="suratForm.company_id" class="mn-isian" title="Perusahaan">
            <option v-for="p in pilihan.perusahaan" :key="p.nilai" :value="p.nilai">{{ p.label }}</option>
          </select>
        </Medan>
        <Medan label="Catatan" untuk="s-catatan" :galat="suratForm.errors.catatan" penuh>
          <textarea id="s-catatan" v-model="suratForm.catatan" class="mn-isian" rows="2" />
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="suratTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="suratForm.processing" @click="simpanSurat">Save</button>
      </template>
    </Modal>

    <!-- ══════════ modal: kirim ══════════ -->
    <Modal :terbuka="kirimTerbuka" judul="Alert Confirmation" @tutup="kirimTerbuka = false">
      <p class="text-[13px]">Are you sure sending MCU? Surat dikirim ke dokter pemeriksa untuk divalidasi.</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="kirimTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="kirimForm.processing" @click="kirim">Sending</button>
      </template>
    </Modal>

    <!-- ══════════ modal: persetujuan dokter ══════════ -->
    <Modal :terbuka="setujuTerbuka" judul="Persetujuan Hasil MCU" @tutup="setujuTerbuka = false">
      <div class="mn-form">
        <Medan label="Masa Berlaku MCU" untuk="t-bulan" wajib :galat="tindakan.errors.bulan_berlaku" penuh
               petunjuk="Dihitung dari tanggal pemeriksaan tiap manpower.">
          <select id="t-bulan" v-model="tindakan.bulan_berlaku" class="mn-isian" title="Masa berlaku MCU">
            <option v-for="b in pilihan.bulan" :key="b" :value="b">{{ b === 12 ? '1 Tahun' : `${b} Bulan` }}</option>
          </select>
        </Medan>
        <Medan label="Catatan" untuk="t-catatan" penuh>
          <textarea id="t-catatan" v-model="tindakan.catatan" class="mn-isian" rows="2" maxlength="500" />
        </Medan>
      </div>
      <p v-if="tindakan.errors.keadaan" class="mn-galat mt-2">{{ tindakan.errors.keadaan }}</p>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="setujuTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">Approve</button>
      </template>
    </Modal>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Alert Reject MCU' : 'Kembalikan untuk diperbaiki'"
           @tutup="alasanTerbuka = null">
      <p class="text-[13px] mb-3">
        <template v-if="alasanTerbuka === 'tolak'">Status menjadi <b>reject</b>. Pengaju dapat mengajukan ulang; seluruh hasil kembali "Belum Dinilai".</template>
        <template v-else>Surat kembali menjadi draf pengaju untuk dilengkapi, lalu dikirim ulang.</template>
      </p>
      <Medan label="Alasan" untuk="t-alasan" wajib :galat="tindakan.errors.keadaan">
        <textarea id="t-alasan" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="alasanTerbuka = null">Close</button>
        <button type="button" class="mn-btn" :class="alasanTerbuka === 'tolak' ? 'mn-btn-bahaya' : 'mn-btn-peringatan'"
                :disabled="tindakan.processing || !tindakan.catatan.trim()" @click="tindak(alasanTerbuka!)">
          {{ alasanTerbuka === 'tolak' ? 'Reject' : 'Kembalikan' }}
        </button>
      </template>
    </Modal>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
