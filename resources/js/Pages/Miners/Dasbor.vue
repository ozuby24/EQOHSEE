<script setup lang="ts">
/**
 * Ringkasan Miners.
 *
 * Menjawab satu pertanyaan lebih dahulu: berapa orang yang hari ini
 * TIDAK boleh masuk, dan karena apa. Ubin yang menyebut total pekerja
 * tanpa itu hanya memberi tahu ukuran perusahaan — yang sudah diketahui
 * setiap orang yang membukanya.
 *
 * Satu bahasa visual dengan layar Miners lain (miners.css): ubin KPI,
 * donat zona masa berlaku MCU dan Mine Permit, berkas yang perlu
 * diurus, antrean Outstanding, paling mendesak, dan Tinjauan Harian MCU.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import Batang from '../../Grafik/Batang.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import UbinKpi from '../../Components/Miners/UbinKpi.vue';
import DonatZona from '../../Components/Miners/DonatZona.vue';
import SelOrang from '../../Components/Miners/SelOrang.vue';
import KeadaanKosong from '../../Components/Miners/KeadaanKosong.vue';
import { IKON } from '../../Components/Miners/ikon';

const props = propHalaman();

const JENIS: [string, string][] = [
  ['mcu', 'MCU'],
  ['induksi', 'Induksi'],
  ['permit', 'Mine Permit'],
  ['simper', 'SIMPER'],
];

const mendesak = computed<any[]>(() => (props.mendesak ?? []) as any[]);

/* Tinjauan Harian MCU — saring tanggal pengajuan (Safe Track b947f60). */
const tinjau = computed<any>(() => props.tinjauan ?? null);
const tglTinjau = ref(String(props.tinjauan?.tanggal ?? ''));
function gantiTinjau() {
  router.get('/miners/dasbor', { tgl_tinjau: tglTinjau.value }, { preserveScroll: true, preserveState: true, only: ['tinjauan'] });
}
const kompetensi = computed<any[]>(() => (props.kompetensiHabis ?? []) as any[]);

/**
 * Satu batang per jenis berkas: berapa orang yang TIDAK lengkap.
 *
 * Yang digambar bukan seluruh sebarannya melainkan sisi yang menuntut
 * pekerjaan — habis ditambah belum ada. Menggambar keempat keadaan
 * berdampingan membuat batang "berlaku" menguasai skalanya, dan yang
 * harus diurus menyusut menjadi garis setipis rambut di ujungnya.
 * Sebaran lengkapnya tetap ada, di tabel yang menyertai grafik ini.
 */
const perluDiurus = computed(() =>
  JENIS.map(([kode, nama]) => {
    const r = props.ringkas?.[kode] ?? {};
    const n = Number(r.habis ?? 0) + Number(r.belum ?? 0);

    return {
      label: nama,
      nilai: n,
      keadaan: (n === 0 ? 'baik' : Number(r.habis ?? 0) > 0 ? 'gawat' : 'ingat') as
        'baik' | 'gawat' | 'ingat',
    };
  }));

/**
 * Berapa orang yang hari ini tertahan.
 *
 * Yang TERBANYAK di antara keempat jenis, bukan jumlahnya. Satu orang
 * yang MCU dan permitnya sama-sama habis akan terhitung dua kali oleh
 * penjumlahan — dan angka yang lebih besar daripada jumlah pekerja
 * adalah angka yang membuat seluruh ubin berhenti dipercaya.
 */
const tertahan = computed(() =>
  Math.max(0, ...perluDiurus.value.map((b) => b.nilai)));

const antrean = computed(() => props.antrean ?? {});
const zonaMcu = computed<any>(() => props.zonaMcu ?? {});
const zonaPermit = computed<any>(() => props.zonaPermit ?? {});

/* Keadaan berkas → pil lembut yang sama dengan zona masa berlaku. */
const KELAS_KEADAAN: Record<string, string> = {
  habis: 'mn-l-bahaya', belum: 'mn-l-abu', mendekati: 'mn-l-oranye', berlaku: 'mn-l-sukses',
};
const jumlah  = computed(() => props.jumlah ?? {});
</script>

<template>
  <Head :title="props.judul" />

  <div class="tema-miners">
    <!-- ══════════ ubin KPI ══════════ -->
    <section class="mn-kpi-grid" aria-label="Ringkasan">
      <UbinKpi label="Perlu diurus" :angka="tertahan" sub="berkas habis atau belum ada" :ikon="IKON.awas"
               :nada="tertahan > 0 ? 'bahaya' : 'aman'" href="/miners/kedaluwarsa" />
      <UbinKpi label="Pekerja aktif" :angka="jumlah.pekerjaAktif ?? 0" :sub="`dari ${jumlah.pekerja ?? 0} terdaftar`" :ikon="IKON.orang"
               nada="info" href="/miners" />
      <UbinKpi label="Mine Permit terbit" :angka="jumlah.permitTerbit ?? 0" sub="kartu masuk tambang" :ikon="IKON.kartu"
               nada="aman" href="/miners/permit" />
      <UbinKpi label="SIMPER terbit" :angka="jumlah.simperTerbit ?? 0" sub="izin mengemudikan unit" :ikon="IKON.kartu"
               nada="aksen" href="/miners/simper" />
    </section>

    <!-- ══════════ donat zona ══════════ -->
    <section class="mn-dua">
      <Kartu judul="Masa Berlaku MCU" :keterangan="`${(zonaMcu.total ?? 0).toLocaleString('id-ID')} pekerja · MCU terakhir`">
        <template #kanan><Link href="/miners" class="mn-btn mn-btn-hantu mn-btn-kecil">Manpower MCU →</Link></template>
        <DonatZona :n="zonaMcu" />
      </Kartu>
      <Kartu judul="Masa Berlaku Mine Permit" :keterangan="`${(zonaPermit.total ?? 0).toLocaleString('id-ID')} pekerja`">
        <template #kanan><Link href="/miners/permit" class="mn-btn mn-btn-hantu mn-btn-kecil">Mine Permit →</Link></template>
        <DonatZona :n="zonaPermit" />
      </Kartu>
    </section>

    <!-- ══════════ berkas perlu diurus ══════════ -->
    <Kartu judul="Berkas yang perlu diurus" :keterangan="`Habis atau belum ada · ${tertahan} orang terbanyak`">
      <div class="mn-dua mn-dua-rapat">
        <div class="min-w-0"><Batang :baris="perluDiurus" satuan=" orang" apa-adanya /></div>
        <div class="mn-gulir">
          <table class="mn-tabel">
            <thead>
              <tr>
                <th>Berkas</th>
                <th v-for="(label, kode) in (props.KEADAAN ?? {})" :key="kode" class="mn-angka">{{ label }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="[kode, nama] in JENIS" :key="kode">
                <td class="mn-rekap-nama">{{ nama }}</td>
                <td v-for="(label, k) in (props.KEADAAN ?? {})" :key="k" class="mn-angka" :class="{ 'mn-nol': !props.ringkas?.[kode]?.[k] }">
                  {{ props.ringkas?.[kode]?.[k] ?? 0 }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p class="mn-petunjuk">Dihitung atas seluruh pekerja, bukan atas yang tersaring — ringkasan yang mengikuti penyaring akan menyebut 100% habis begitu seseorang memilih "habis".</p>
    </Kartu>

    <!-- ══════════ antrean & mendesak ══════════ -->
    <section class="mn-dua">
      <Kartu judul="Menunggu tindakan" keterangan="Dokumen yang berhenti pada salah satu langkah persetujuan">
        <ul class="mn-daftar">
          <li v-for="[kode, nama] in JENIS" :key="kode">
            <Link :href="`/miners/daftar/outstanding-${kode}`" class="mn-daftar-baris">
              <span>Outstanding {{ nama }}</span>
              <span class="mn-daftar-angka" :class="{ 'mn-nol': !Number(antrean[kode] ?? 0) }">{{ antrean[kode] ?? 0 }}</span>
            </Link>
          </li>
        </ul>
      </Kartu>

      <Kartu judul="Paling mendesak" keterangan="Orang yang salah satu berkasnya habis atau belum ada">
        <ul v-if="mendesak.length" class="mn-daftar">
          <li v-for="m in mendesak" :key="m.id" class="mn-daftar-baris">
            <SelOrang :nama="m.nama" :sub="[m.jabatan, m.perusahaan].filter(Boolean).join(' · ') || null" :href="`/miners/${m.id}`" />
            <span class="mn-lencana mn-titik" :class="KELAS_KEADAAN[m.terburuk] ?? 'mn-l-abu'">{{ props.KEADAAN?.[m.terburuk] ?? m.terburuk }}</span>
          </li>
        </ul>
        <KeadaanKosong v-else :ikon="IKON.centang" judul="Seluruh pekerja lengkap hari ini" teks="Tidak ada berkas yang habis atau belum ada." />
      </Kartu>
    </section>

    <!-- ══════════ kompetensi ══════════ -->
    <Kartu v-if="kompetensi.length" judul="Sertifikat kompetensi akan habis" :keterangan="`${kompetensi.length} sertifikat`">
      <div class="mn-gulir">
        <table class="mn-tabel">
          <thead><tr><th>Pemegang</th><th>Sertifikat</th><th>Berlaku sampai</th><th class="mn-angka">Sisa</th></tr></thead>
          <tbody>
            <tr v-for="k in kompetensi" :key="k.id">
              <td><SelOrang :nama="k.pekerja" /></td>
              <td>{{ k.nama }}</td>
              <td><LencanaMasaBerlaku :tanggal="k.sampai" :sisa="k.sisa" /></td>
              <td class="mn-angka">{{ k.sisa }} hari</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <!-- ══════════ tinjauan harian MCU ══════════ -->
    <Kartu v-if="tinjau" judul="Tinjauan Harian MCU" keterangan="Pengajuan per tanggal">
      <template #kanan>
        <label class="sr-only" for="tgl-tinjau">Tanggal pengajuan</label>
        <input id="tgl-tinjau" v-model="tglTinjau" type="date" class="mn-isian mn-isian-pas" @change="gantiTinjau">
      </template>
      <div class="mn-kpi-grid mn-kpi-kecil">
        <UbinKpi label="Diajukan" :angka="tinjau.diajukan" :ikon="IKON.dokumen" nada="info" />
        <UbinKpi label="Menunggu dokter" :angka="tinjau.menunggu" :ikon="IKON.jam" nada="kritis" />
        <UbinKpi label="Disetujui" :angka="tinjau.disetujui" :ikon="IKON.centang" nada="aman" />
        <UbinKpi label="Ditolak" :angka="tinjau.ditolak" :ikon="IKON.silang" nada="bahaya" />
      </div>
      <div class="mn-gulir">
        <table class="mn-tabel">
          <thead><tr><th class="mn-kolom-sempit">No</th><th>Nama</th><th>Perusahaan</th><th>Jam</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="(o, i) in (tinjau.orang as any[])" :key="o.id">
              <td class="mn-no">{{ Number(i) + 1 }}</td>
              <td><SelOrang :nama="o.nama" :href="`/miners/mcu/${o.mcu_id}`" /></td>
              <td>{{ o.perusahaan || '—' }}</td>
              <td class="mn-num">{{ o.jam || '—' }}</td>
              <td><LencanaStatus :status="o.status" /></td>
            </tr>
            <tr v-if="!tinjau.orang.length">
              <td colspan="5" class="mn-kosong"><KeadaanKosong teks="Tidak ada pengajuan MCU pada tanggal ini." /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </Kartu>
  </div>
</template>
