<script setup lang="ts">
/**
 * Detail Penambahan Unit / Upgrade SIMPER / Perpanjangan SIMPER.
 *
 *   · Penambahan — salinan unit lama (redup) + unit baru; HSE → KTT.
 *   · Upgrade — per unit: authority lama → baru + Simper Upgrade, DDT
 *     Upgrade, Pemenuhan Jam; HSE.
 *   · Perpanjangan — SIM lama → baru, tanggal MCU, sertifikat MCU, form;
 *     dokter → HSE → KTT. Tanggal SIMPER berpindah hanya sesudah KTT.
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
import ButirLampiran from '../../Components/Miners/ButirLampiran.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import KeadaanKosong from '../../Components/Miners/KeadaanKosong.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const a = computed<any>(() => props.ajuan ?? {});
const unit = computed<any[]>(() => (props.unit ?? []) as any[]);
const galat = computed<Record<string, string>>(() => (props.errors ?? {}) as Record<string, string>);
const pilihanAuthority = computed(() => Object.entries((props.authority ?? {}) as Record<string, string>).map(([nilai, label]) => ({ nilai, label })));
const dasar = computed(() => `/miners/simper-lanjutan/${a.value.id}`);
const rantai = computed(() => ({ penambahan: 'Pengaju → HSE → KTT', upgrade: 'Pengaju → HSE', perpanjangan: 'Pengaju → Dokter → HSE → KTT' } as Record<string, string>)[a.value.jenis] ?? '');

const unitTerbuka = ref(false);
const unitForm = useForm({
  kendaraan_id: '' as string | number, jenis_unit_ids: [] as number[], authority: 'F', nilai_p2h: '' as string | number, nilai_praktek: '' as string | number,
  simper_unit_id: '' as string | number,
  berkas_teori: null as File | null, berkas_praktek: null as File | null, berkas_evaluasi: null as File | null,
  berkas_upgrade: null as File | null, berkas_ddt: null as File | null, berkas_jam: null as File | null,
});
type B = 'berkas_teori' | 'berkas_praktek' | 'berkas_evaluasi' | 'berkas_upgrade' | 'berkas_ddt' | 'berkas_jam';
function berkas(m: B, e: Event) { unitForm[m] = (e.target as HTMLInputElement).files?.[0] ?? null; }
function bukaUnit() { unitForm.reset(); unitForm.clearErrors(); unitTerbuka.value = true; }
function simpanUnit() {
  unitForm.post(`${dasar.value}/unit`, { forceFormData: true, preserveScroll: true, onSuccess: () => { unitTerbuka.value = false; } });
}

const hapusForm = useForm({});
async function hapusUnit(u: any) {
  if (!await tanya({ judul: 'Hapus baris', pesan: `Hapus ${u.kendaraan} dari pengajuan ini?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusForm.delete(`${dasar.value}/unit/${u.id}`, { preserveScroll: true });
}

const kirimForm = useForm({});
function kirim() { kirimForm.post(`${dasar.value}/kirim`, { preserveScroll: true }); }
function ajukanUlang() { kirimForm.post(`${dasar.value}/ajukan-ulang`, { preserveScroll: true }); }

const tindakan = useForm({ keadaan: 'setuju', catatan: '' });
const alasanTerbuka = ref<null | 'tolak' | 'dikembalikan'>(null);
async function tindak(keadaan: string) {
  if (keadaan === 'setuju' && !await tanya({ judul: 'Confirm Approved?', labelAksi: 'Approve' })) return;
  tindakan.keadaan = keadaan;
  tindakan.post(`${dasar.value}/tindak`, { preserveScroll: true, onSuccess: () => { alasanTerbuka.value = null; tindakan.reset('catatan'); } });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], [a.label, a.daftar], [a.nomor || 'Detail', null]]">
      <template #aksi><Link :href="a.daftar" class="mn-btn mn-btn-garis">← Kembali</Link></template>
    </KepalaHalaman>

    <div v-for="k in ['kirim', 'ajukanUlang', 'keadaan', 'unit', 'authority']" :key="k">
      <div v-if="galat[k]" class="mn-alert mn-alert-bahaya" role="alert">{{ galat[k] }}</div>
    </div>

    <KepalaProfil :judul="a.nama || '—'" :eyebrow="`${a.label} · ${a.nomor || ''}`"
                  :keterangan="[['Noid', a.noid], ['Jabatan', a.jabatan], ['Departemen', a.departemen], ['SIMPER', a.simper]]">
      <template #lencana><LencanaStatus :status="a.status" /></template>
    </KepalaProfil>

    <div v-if="props.bolehTindak" class="mn-bilah-aksi">
      <div class="mn-bilah-aksi-teks"><b>Menunggu persetujuan Anda</b>Periksa unit dan berkas di bawah, lalu approve, kembalikan, atau reject.</div>
      <div class="mn-bilah-aksi-tombol">
        <button type="button" class="mn-btn mn-btn-sukses" :disabled="tindakan.processing" @click="tindak('setuju')">approve</button>
        <button type="button" class="mn-btn mn-btn-peringatan" @click="alasanTerbuka = 'dikembalikan'">Kembalikan</button>
        <button type="button" class="mn-btn mn-btn-garis-bahaya" @click="alasanTerbuka = 'tolak'">reject</button>
      </div>
    </div>

    <Kartu :judul="`Detail ${a.label}`">
      <DaftarDefinisi>
        <Butir label="Tanggal" :nilai="tglId(a.tanggal)" />
        <Butir label="No Register" :nilai="a.nomor" />
        <Butir label="SIMPER"><Link v-if="a.simper_id" :href="`/miners/simper/${a.simper_id}`" class="mn-tautan">{{ a.simper }}</Link></Butir>
        <Butir label="Jenis SIM" :nilai="a.jenisSim" />
        <template v-if="a.jenis === 'perpanjangan'">
          <Butir label="SIM Pol lama" :nilai="`${a.simLama || '—'} · s/d ${tglId(a.berlakuLama)}`" />
          <Butir label="SIM Pol baru" :nilai="`${a.simBaru || '—'} · s/d ${tglId(a.berlakuBaru)}`" />
          <Butir label="Tanggal MCU" :nilai="tglId(a.tanggalMcu)" />
          <Butir label="Catatan" penuh>
            <span class="mn-redup">Masa berlaku SIMPER tetap {{ tglId(a.berlakuLama) }} sampai KTT menyetujui; dokter menulis balik MCU saat menyetujui.</span>
          </Butir>
        </template>
        <Butir label="Pengaju" :nilai="a.pengaju" />
      </DaftarDefinisi>
    </Kartu>

    <Kartu v-if="a.berkas?.length" judul="Berkas" keterangan="Seluruhnya wajib">
      <ul class="mn-periksa">
        <ButirLampiran v-for="b in a.berkas" :key="b.label" :label="b.label" :wajib="b.wajib" :ada="!!(b.url || b.ada)">
          <ChipBerkas v-if="b.url" :href="b.url" :nama="b.label" />
          <span v-else-if="b.ada" class="mn-lencana mn-l-terang">terjaga</span>
        </ButirLampiran>
      </ul>
    </Kartu>

    <Kartu v-if="a.jenis !== 'perpanjangan'" :judul="a.jenis === 'upgrade' ? 'Unit yang di-upgrade' : 'Unit'" :keterangan="`${unit.length} baris`">
      <template #kanan>
        <button v-if="props.bolehUbah && ['draf', 'ditolak'].includes(a.status)" type="button" class="mn-btn mn-btn-garis" @click="bukaUnit">
          {{ a.jenis === 'upgrade' ? '+ Upgrade Unit' : '+ add Unit' }}
        </button>
      </template>
      <KeadaanKosong v-if="!unit.length" judul="Belum ada baris" :teks="a.jenis === 'upgrade' ? 'Pilih unit SIMPER yang authority-nya dinaikkan.' : 'Tambahkan unit baru.'" />
      <table v-else class="mn-tabel">
        <thead><tr><th>Master Unit</th><th>Type Unit</th><th>Authority</th><th v-if="a.jenis === 'penambahan'">P2H</th><th v-if="a.jenis === 'penambahan'">Praktek</th><th>Berkas</th><th>#</th></tr></thead>
        <tbody>
          <tr v-for="u in unit" :key="u.id" :class="{ 'mn-redup': a.jenis === 'penambahan' && u.lama }">
            <td>{{ u.kendaraan }} <span v-if="a.jenis === 'penambahan'" class="mn-lencana" :class="u.lama ? 'mn-l-abu' : 'mn-l-terang'">{{ u.lama ? 'lama' : 'baru' }}</span></td>
            <td>{{ (u.jenis ?? []).join(', ') || '—' }}</td>
            <td><template v-if="a.jenis === 'upgrade'">{{ u.authorityLama || '—' }} → </template><b>{{ u.authority }}</b></td>
            <td v-if="a.jenis === 'penambahan'">{{ u.p2h ?? '—' }}</td>
            <td v-if="a.jenis === 'penambahan'">{{ u.praktek ?? '—' }}</td>
            <td><span class="flex flex-wrap gap-1"><template v-for="b in u.berkas" :key="b.nama"><ChipBerkas v-if="b.url" :href="b.url" :nama="b.nama" /></template></span></td>
            <td>
              <TombolIkon v-if="props.bolehUbah && ['draf', 'ditolak'].includes(a.status) && !(a.jenis === 'penambahan' && u.lama)"
                          ikon="hapus" varian="bahaya" judul="Hapus baris" @click="hapusUnit(u)" />
            </td>
          </tr>
        </tbody>
      </table>
    </Kartu>

    <div v-if="a.status === 'draf' && props.bolehKirim" class="mn-langkah">
      <div>
        <div class="mn-langkah-label">Status: draft — belum dikirim</div>
        <div v-if="props.kurang?.length" class="mn-langkah-kurang">Belum dapat dikirim: {{ props.kurang.join(', ') }}.</div>
        <div v-else class="mn-langkah-teks">Lengkap. Kirim ke penyetuju ({{ rantai }}).</div>
      </div>
      <button type="button" class="mn-btn mn-btn-utama" :disabled="props.kurang?.length > 0 || kirimForm.processing" @click="kirim">Sending</button>
    </div>

    <Kartu v-if="a.status !== 'draf' || props.bolehAjukanUlang" judul="Persetujuan" :keterangan="rantai">
      <TabelPersetujuan :alur="props.alur ?? []" :posisi="props.posisi" />
      <div v-if="props.bolehAjukanUlang" class="mt-4">
        <button type="button" class="mn-btn mn-btn-peringatan" :disabled="kirimForm.processing" @click="ajukanUlang">Re-Submit</button>
      </div>
    </Kartu>

    <Modal :terbuka="unitTerbuka" :judul="a.jenis === 'upgrade' ? 'Upgrade Authority' : 'Add Unit'" lebar @tutup="unitTerbuka = false">
      <form class="mn-form" @submit.prevent="simpanUnit">
        <template v-if="a.jenis === 'upgrade'">
          <Medan label="Unit SIMPER" untuk="w-unit" wajib penuh :galat="unitForm.errors.simper_unit_id">
            <select id="w-unit" v-model="unitForm.simper_unit_id" class="mn-isian" title="Unit SIMPER">
              <option value="">— pilih —</option>
              <option v-for="x in props.unitSimper" :key="x.id" :value="x.id">{{ x.label }}</option>
            </select>
          </Medan>
          <Medan label="Authority baru" untuk="w-auth" wajib :galat="unitForm.errors.authority">
            <select id="w-auth" v-model="unitForm.authority" class="mn-isian" title="Authority">
              <option v-for="o in pilihanAuthority" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
          </Medan>
          <Medan label="Form Simper Upgrade" untuk="w-up" wajib :galat="unitForm.errors.berkas_upgrade">
            <input id="w-up" type="file" class="mn-isian" @change="berkas('berkas_upgrade', $event)">
          </Medan>
          <Medan label="DDT Upgrade" untuk="w-ddt" wajib :galat="unitForm.errors.berkas_ddt">
            <input id="w-ddt" type="file" class="mn-isian" @change="berkas('berkas_ddt', $event)">
          </Medan>
          <Medan label="Pemenuhan Jam" untuk="w-jam" wajib :galat="unitForm.errors.berkas_jam">
            <input id="w-jam" type="file" class="mn-isian" @change="berkas('berkas_jam', $event)">
          </Medan>
        </template>
        <template v-else>
          <Medan label="Master Unit" untuk="w-kend" wajib :galat="unitForm.errors.kendaraan_id">
            <select id="w-kend" v-model="unitForm.kendaraan_id" class="mn-isian" title="Master Unit">
              <option value="">— pilih —</option>
              <option v-for="k in props.kendaraan" :key="k.id" :value="k.id">{{ k.nama }}{{ k.kode ? ` (${k.kode})` : '' }}</option>
            </select>
          </Medan>
          <Medan label="Authority" untuk="w-auth2" wajib :galat="unitForm.errors.authority">
            <select id="w-auth2" v-model="unitForm.authority" class="mn-isian" title="Authority">
              <option v-for="o in pilihanAuthority" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
            </select>
          </Medan>
          <Medan label="Type Unit" wajib penuh :galat="unitForm.errors.jenis_unit_ids">
            <div class="mn-pilih-banyak" role="group" aria-label="Type Unit">
              <label v-for="t in props.typeUnit" :key="t.id" class="mn-cek"><input v-model="unitForm.jenis_unit_ids" type="checkbox" :value="t.id"> {{ t.nama }}</label>
            </div>
          </Medan>
          <Medan label="Nilai P2H" untuk="w-p2h"><input id="w-p2h" v-model="unitForm.nilai_p2h" type="number" min="0" max="100" class="mn-isian"></Medan>
          <Medan label="Nilai Praktek" untuk="w-prak"><input id="w-prak" v-model="unitForm.nilai_praktek" type="number" min="0" max="100" class="mn-isian"></Medan>
          <Medan label="Berkas Teori" untuk="w-teori" wajib :galat="unitForm.errors.berkas_teori">
            <input id="w-teori" type="file" class="mn-isian" @change="berkas('berkas_teori', $event)">
          </Medan>
          <Medan label="Hasil Praktek" untuk="w-praktek" wajib :galat="unitForm.errors.berkas_praktek">
            <input id="w-praktek" type="file" class="mn-isian" @change="berkas('berkas_praktek', $event)">
          </Medan>
          <Medan label="Evaluasi (opsional)" untuk="w-eval"><input id="w-eval" type="file" class="mn-isian" @change="berkas('berkas_evaluasi', $event)"></Medan>
        </template>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="unitTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="unitForm.processing" @click="simpanUnit">Save Data</button>
      </template>
    </Modal>

    <Modal :terbuka="alasanTerbuka !== null" :judul="alasanTerbuka === 'tolak' ? 'Reject' : 'Kembalikan untuk diperbaiki'" @tutup="alasanTerbuka = null">
      <Medan label="Remarks" untuk="la-alasan" wajib :galat="tindakan.errors.keadaan">
        <textarea id="la-alasan" v-model="tindakan.catatan" class="mn-isian" rows="3" maxlength="500" />
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
