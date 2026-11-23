<script setup lang="ts">
/*
  Rincian rencana pengangkatan: hasil simulasi, kajian teknis 6 pilar
  yang butir lapangannya dinilai di sini, alat yang dipakai beserta
  kelayakannya, dan alur persetujuan.

  Tombol "Ajukan" dan "Setujui" tidak disembunyikan ketika ada hambatan —
  hambatannya ditulis tepat di atas tombol, supaya yang membaca tahu apa
  yang harus dibereskan, bukan bertanya-tanya ke mana tombolnya.
*/
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import DiagramCrane from '../../Components/Angkat/DiagramCrane.vue';
import DiagramSling from '../../Components/Angkat/DiagramSling.vue';
import KurvaBeban from '../../Components/Angkat/KurvaBeban.vue';
import Radar from '../../Grafik/Radar.vue';
import { n } from '../../energi';
import { simulasi, klas, fase, kajian, kelayakan, status, BUTIR, waktu, tanggal } from '../../angkat';

const props = propHalaman();
const { dialog, tanya, minta, batal, lanjut } = useDialog();
const r = computed(() => props.rencana ?? {});
const h = computed(() => props.hasil ?? {});
const k = computed(() => props.kajian ?? {});
const galat = computed(() => (props.errors ?? {}) as Record<string, string>);

const tab = ref<'ringkasan' | 'kajian' | 'jejak'>('ringkasan');
const manual = ref<Record<string, string>>(Object.fromEntries((k.value.pilar ?? []).flatMap((p: any) => p.butir.filter((b: any) => !b.oto).map((b: any) => [b.kode, b.status]))));
const menyimpan = ref(false);
function simpanKajian() {
  menyimpan.value = true;
  router.post(`/pengangkatan/rencana/${r.value.id}/kajian`, { manual: manual.value }, { preserveScroll: true, onFinish: () => (menyimpan.value = false) });
}

async function aksi(a: string) {
  const kritis = r.value.klasifikasi === 'kritis';
  let catatan: string | null = '';
  if (a === 'tolak') {
    catatan = await minta({ judul: 'Tolak rencana?', pesan: 'Pembuat rencana menerima alasan ini sebagai daftar perbaikan.', label: 'Alasan penolakan', jenis: 'panjang', wajib: true, labelAksi: 'Tolak rencana', nada: 'bahaya' });
    if (catatan === null) return;
  } else if (a === 'setujui') {
    catatan = await minta({ judul: kritis ? 'Setujui sebagai KTT?' : 'Setujui rencana?', pesan: kritis ? 'Pengangkatan kritis — persetujuan Anda tercatat sebagai persetujuan Kepala Teknik Tambang.' : 'Persetujuan tercatat atas nama Anda.', label: 'Catatan persetujuan (opsional)', jenis: 'panjang', labelAksi: 'Setujui' });
    if (catatan === null) return;
  } else if (a === 'batal') {
    if (!(await tanya({ judul: 'Batalkan rencana?', pesan: `${r.value.kode} tidak dapat dilanjutkan lagi. Riwayatnya tetap tersimpan.`, labelAksi: 'Batalkan', nada: 'bahaya' }))) return;
  } else if (a === 'selesai') {
    if (!(await tanya({ judul: 'Catat pengangkatan selesai?', pesan: 'Tandai bahwa beban sudah ditempatkan dan area dibuka kembali.', labelAksi: 'Selesai' }))) return;
  }
  router.post(`/pengangkatan/rencana/${r.value.id}/status`, { aksi: a, catatan }, { preserveScroll: true });
}

function cetak() { window.print(); }

const sumbu = computed(() => (k.value.pilar ?? []).map((p: any) => ({ label: p.kode, sub: p.nama })));
const rig = computed(() => h.value.rigging ?? null);
const kendali = computed(() => props.beban?.kendali ?? []);
</script>

<template>
  <Head :title="`${r.kode} · ${r.judul}`" />
  <div class="space-y-4">
    <section class="rc-kepala">
      <div class="min-w-0">
        <p class="text-[11.5px] font-bold text-stone-500">{{ r.kode }} · {{ r.beban_label }}</p>
        <h1 class="text-[20px] font-extrabold text-cam-ink leading-tight">{{ r.judul }}</h1>
        <p class="text-[12px] text-stone-500 mt-1">{{ r.lokasi }} · {{ r.area }} · {{ waktu(r.jadwal_at) }} · {{ r.alat_label }}</p>
        <div class="flex flex-wrap gap-1.5 mt-2">
          <span class="pr-pil" :class="status(r.status).kelas">{{ status(r.status).label }}</span>
          <span class="pr-pil" :class="klas(r.klasifikasi).kelas">{{ klas(r.klasifikasi).label }}</span>
          <span class="pr-pil" :class="simulasi(r.putusan).kelas">{{ simulasi(r.putusan).label }}</span>
          <span class="pr-pil" :class="kajian(k.putusan).kelas">Kajian {{ kajian(k.putusan).pendek.toLowerCase() }} · {{ n(k.skor, 1) }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 items-start rc-tanpa-cetak">
        <Link v-if="r.bisa_ubah" :href="`/pengangkatan/rencana/${r.id}/ubah`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="sliders-horizontal" :ukuran="15" />Ubah & simulasi ulang</Link>
        <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="cetak"><Ikon nama="printer" :ukuran="15" />Cetak</button>
      </div>
    </section>

    <!-- alur persetujuan -->
    <section class="rc-alur" :class="`rc-alur-${r.status}`">
      <div class="min-w-0 flex-1">
        <p class="text-[13px] font-extrabold text-cam-ink">
          <template v-if="r.status === 'draf' || r.status === 'ditolak'">Siap diajukan?</template>
          <template v-else-if="r.status === 'diajukan'">Menunggu persetujuan {{ props.klasifikasi?.[r.klasifikasi]?.persetujuan }}</template>
          <template v-else-if="r.status === 'disetujui'">Disetujui {{ r.disetujui_oleh }} · {{ waktu(r.disetujui_at) }}</template>
          <template v-else-if="r.status === 'selesai'">Selesai dilaksanakan {{ waktu(r.selesai_at) }}</template>
          <template v-else>Rencana dibatalkan</template>
        </p>
        <p v-if="r.catatan_persetujuan" class="text-[12px] text-stone-600 mt-1">“{{ r.catatan_persetujuan }}”</p>
        <ul v-if="props.hambatan?.length && ['draf', 'ditolak', 'diajukan'].includes(r.status)" class="mt-2 space-y-1 text-[12px] rc-hambat">
          <li v-for="(x, i) in props.hambatan" :key="i" class="flex gap-2"><Ikon nama="ban" :ukuran="14" class="shrink-0 mt-px" />{{ x }}</li>
        </ul>
        <p v-if="galat.aksi || galat.catatan" class="mt-2 text-[12px] rc-hambat">{{ galat.aksi || galat.catatan }}</p>
        <p v-if="r.status === 'diajukan' && props.pembuatSendiri" class="mt-2 text-[11.5px] text-stone-500">Anda pembuat rencana ini — persetujuan harus dari pengawas lain.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="['draf', 'ditolak'].includes(r.status)" class="eq-btn-utama" @click="aksi('ajukan')">Ajukan</button>
        <template v-if="r.status === 'diajukan'">
          <button class="eq-btn-utama" @click="aksi('setujui')">{{ r.klasifikasi === 'kritis' ? 'Setujui (KTT)' : 'Setujui' }}</button>
          <button class="eq-btn-lain" @click="aksi('tolak')">Tolak</button>
          <button class="eq-btn-lain" @click="aksi('tarik')">Tarik kembali</button>
        </template>
        <button v-if="r.status === 'disetujui'" class="eq-btn-utama" @click="aksi('selesai')">Catat selesai</button>
        <button v-if="['draf', 'diajukan', 'disetujui', 'ditolak'].includes(r.status)" class="eq-btn-lain" @click="aksi('batal')">Batalkan</button>
      </div>
    </section>

    <nav class="rc-tab">
      <button v-for="t in [['ringkasan', 'Simulasi'], ['kajian', 'Kajian 6 pilar'], ['jejak', 'Jejak']]" :key="t[0]" :class="{ aktif: tab === t[0] }" @click="tab = t[0] as any">{{ t[1] }}</button>
    </nav>

    <template v-if="tab === 'ringkasan'">
      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <Kartu judul="Tampak samping" :ket="`Boom ${n(h.boom_m, 1)} m · sudut ${n(h.sudut_boom, 1)}° · ujung ${n(h.tinggi_ujung_m, 1)} m`" ikon="construction" warna="biru">
          <DiagramCrane :h="h" />
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
            <div class="rc-angka"><span>Beban kotor</span><b class="num">{{ n(h.berat_kotor_t, 2) }} t</b></div>
            <div class="rc-angka"><span>Kapasitas</span><b class="num">{{ n(h.kapasitas_t, 1) }} t</b></div>
            <div class="rc-angka"><span>Utilisasi</span><b class="num">{{ n(h.utilisasi_pct, 1) }}%</b></div>
            <div class="rc-angka"><span>Tekanan tanah</span><b class="num">{{ n(h.tekanan_tanah, 0) }} kN/m²</b></div>
          </div>
        </Kartu>
        <Kartu judul="Kurva beban" :ket="`${h.alat?.label} · batas ${n(h.batas_util, 0)}%`" ikon="chart-line" warna="biru">
          <KurvaBeban :kurva="h.kurva ?? []" :radius="h.radius_m" :kotor="h.berat_kotor_t" :batas="h.batas_util" :radius-aman="h.radius_aman_m" />
        </Kartu>
      </section>

      <section class="grid gap-4 xl:grid-cols-3">
        <Kartu judul="Simulasi 4 fase" :ket="`Skor ${h.skor}/100`" ikon="play" warna="biru" rapat>
          <ol class="divide-y divide-stone-100">
            <li v-for="f in h.fase" :key="f.fase" class="px-5 py-3 flex items-start gap-3">
              <span class="rc-no" :style="{ background: fase(f.status).warna }"><Ikon :nama="f.ikon" :ukuran="15" /></span>
              <div class="min-w-0 flex-1"><p class="text-[12.5px] font-bold text-cam-ink">{{ f.nama }}</p><p class="text-[11.5px] text-stone-500">{{ f.rinci }}</p></div>
            </li>
          </ol>
        </Kartu>
        <Kartu v-if="rig" judul="Rigging" :ket="`${rig.kaki} kaki · sling ${n(rig.util_sling, 1)}% WLL`" ikon="link-2" :warna="rig.status === 'gagal' ? 'merah' : rig.status === 'waspada' ? 'amber' : 'hijau'">
          <DiagramSling :r="rig" :cog="h.masukan?.cog_offset_pct" :spreader="h.masukan?.spreader" />
        </Kartu>
        <Kartu judul="Kendali wajib" :ket="props.beban?.label" ikon="shield-check" warna="hijau">
          <ul class="space-y-2 text-[12px]">
            <li v-for="c in kendali" :key="c" class="flex gap-2"><Ikon nama="circle-check" :ukuran="15" class="shrink-0 mt-px rc-hijau" />{{ c }}</li>
          </ul>
          <p class="text-[11px] text-stone-500 mt-3">Acuan: {{ (props.beban?.regulasi ?? []).join(' · ') }}</p>
        </Kartu>
      </section>

      <section class="grid gap-4 xl:grid-cols-2">
        <Kartu judul="Crane & alat bantu" ket="Kelayakan dibaca ulang setiap halaman ini dibuka" ikon="link-2" warna="amber" rapat>
          <ul class="divide-y divide-stone-100">
            <li v-if="props.alat" class="px-5 py-3 flex items-center gap-3">
              <Ikon nama="construction" :ukuran="18" class="shrink-0 text-stone-500" />
              <div class="min-w-0 flex-1"><p class="text-[12.5px] font-bold text-cam-ink">{{ props.alat.kode }} · {{ props.alat.nama }}</p>
                <p class="text-[11.5px] text-stone-500">{{ props.alat.suket_nomor ?? 'Belum ber-SUKET' }} · berlaku s.d. {{ tanggal(props.alat.suket_berlaku) }}</p></div>
              <span class="pr-pil" :class="kelayakan(props.alat.status).kelas">{{ kelayakan(props.alat.status).pendek }}</span>
            </li>
            <li v-for="g in props.gear" :key="g.id" class="px-5 py-3 flex items-center gap-3">
              <Ikon nama="link-2" :ukuran="18" class="shrink-0 text-stone-500" />
              <div class="min-w-0 flex-1"><p class="text-[12.5px] font-bold text-cam-ink">{{ g.kode }} · {{ g.nama }}</p>
                <p class="text-[11.5px] text-stone-500">WLL {{ n(g.wll_t, 2) }} t · uji berikut {{ tanggal(g.uji_berikut) }}</p></div>
              <span class="pr-pil" :class="kelayakan(g.status).kelas">{{ kelayakan(g.status).pendek }}</span>
            </li>
            <li v-if="!props.alat && !props.gear?.length" class="px-5 py-6 text-center text-[12px] text-stone-400">Crane dan alat bantu belum dipilih dari register.</li>
          </ul>
        </Kartu>
        <Kartu judul="Personel" ikon="hard-hat" warna="ungu">
          <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-[12.5px]">
            <dt class="text-stone-500">Operator</dt><dd class="font-bold text-cam-ink">{{ r.operator_nama || '—' }} <span class="text-stone-500 font-semibold">· SIO {{ r.operator_sio || '—' }}</span></dd>
            <dt class="text-stone-500">Rigger</dt><dd class="font-bold text-cam-ink">{{ r.rigger_nama || '—' }} <span class="text-stone-500 font-semibold">· {{ r.rigger_sertifikat || 'tanpa sertifikat' }}</span></dd>
            <dt class="text-stone-500">Signalman</dt><dd class="font-bold text-cam-ink">{{ r.signalman_nama || '—' }}</dd>
            <dt class="text-stone-500">Pengawas</dt><dd class="font-bold text-cam-ink">{{ r.pengawas_nama || '—' }}</dd>
            <dt class="text-stone-500">Dibuat oleh</dt><dd class="font-bold text-cam-ink">{{ r.pembuat || '—' }}</dd>
          </dl>
          <p v-if="r.catatan" class="mt-3 text-[12px] text-stone-600">{{ r.catatan }}</p>
        </Kartu>
      </section>
    </template>

    <template v-else-if="tab === 'kajian'">
      <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] items-start">
        <Kartu :judul="`Kajian teknis ${k.kode}`" :ket="k.peninjau ? `Ditinjau ${k.peninjau} · ${waktu(k.ditinjau_at)}` : 'Belum ditinjau'" ikon="clipboard-check" :warna="k.putusan === 'memenuhi' ? 'hijau' : k.putusan === 'bersyarat' ? 'amber' : 'merah'">
          <Radar :sumbu="sumbu" :deret="[{ nama: 'Skor pilar', nilai: (k.pilar ?? []).map((p: any) => p.skor), teks: (k.pilar ?? []).map((p: any) => n(p.skor, 0)), warna: kajian(k.putusan).warna }]" :ukuran="300" />
          <p class="text-[12.5px] mt-2" :class="k.putusan === 'tidak_memenuhi' ? 'rc-hambat' : 'text-stone-600'">{{ k.kesimpulan }}</p>
        </Kartu>
        <Kartu judul="Butir penilaian" ket="Butir bertanda OTOMATIS dinilai sistem dari simulasi dan register; selebihnya dinilai peninjau di lapangan" ikon="list-checks" warna="biru">
          <div v-for="p in k.pilar" :key="p.kode" class="rc-pilar">
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <p class="text-[13px] font-extrabold text-cam-ink">{{ p.kode }} · {{ p.nama }}</p>
              <span class="num text-[12px] font-bold">{{ n(p.skor, 0) }}/100</span>
            </div>
            <ul class="space-y-1.5">
              <li v-for="b in p.butir" :key="b.kode" class="rc-butir">
                <div class="min-w-0 flex-1">
                  <p class="text-[12px] font-semibold text-cam-ink">{{ b.label }}</p>
                  <p class="text-[11px] text-stone-500">{{ b.oto ? 'Otomatis · ' : '' }}{{ b.bukti }}</p>
                </div>
                <span v-if="b.oto || !r.bisa_ubah && r.status !== 'diajukan'" class="pr-pil shrink-0" :class="BUTIR[b.status]?.kelas">{{ BUTIR[b.status]?.pendek }}</span>
                <select v-else v-model="manual[b.kode]" class="rc-pilih" :aria-label="b.label">
                  <option value="belum">Belum dinilai</option><option value="penuh">Terpenuhi</option><option value="sebagian">Sebagian</option><option value="tidak">Tidak terpenuhi</option>
                </select>
              </li>
            </ul>
          </div>
          <button v-if="r.bisa_ubah || r.status === 'diajukan'" class="eq-btn-utama mt-2" :disabled="menyimpan" @click="simpanKajian">Simpan penilaian lapangan</button>
        </Kartu>
      </section>
    </template>

    <Kartu v-else judul="Jejak rencana" ket="Setiap perubahan status tercatat dan tidak dapat diubah" ikon="history" warna="biru" rapat>
      <ol class="divide-y divide-stone-100">
        <li v-for="(j, i) in props.jejak" :key="i" class="px-5 py-3 flex gap-3">
          <span class="rc-titik" :style="{ background: status(j.aksi === 'setujui' ? 'disetujui' : j.aksi === 'tolak' ? 'ditolak' : j.aksi === 'selesai' ? 'selesai' : j.aksi === 'ajukan' ? 'diajukan' : 'draf').warna }" />
          <div class="min-w-0"><p class="text-[12.5px] font-semibold text-cam-ink">{{ j.ringkas }}</p><p class="text-[11px] text-stone-500">{{ j.oleh }} · {{ waktu(j.waktu) }}</p></div>
        </li>
      </ol>
    </Kartu>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.rc-kepala { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: flex-start; }
.rc-alur { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 14px 18px; border-radius: 18px; border: 1px solid #E7E5E4; background: #fff; border-left-width: 6px; }
.rc-alur-draf, .rc-alur-batal { border-left-color: #A8A29E; }
.rc-alur-diajukan { border-left-color: #D97706; background: #FFFBEB; }
.rc-alur-disetujui { border-left-color: #15803D; background: #F0FDF4; }
.rc-alur-ditolak { border-left-color: #DC2626; background: #FEF2F2; }
.rc-alur-selesai { border-left-color: #2A78D6; background: #EFF6FF; }
.rc-hambat { color: #B91C1C; }
.rc-hijau { color: #15803D; }
.rc-tab { display: flex; gap: 6px; border-bottom: 1px solid #E7E5E4; }
.rc-tab button { padding: 8px 14px; font-size: 12.5px; font-weight: 700; color: #78716C; border-bottom: 2.5px solid transparent; margin-bottom: -1px; }
.rc-tab button.aktif { color: #1D4ED8; border-bottom-color: #1D4ED8; }
.rc-angka { border-radius: 12px; padding: 8px 10px; border: 1px solid #E7E5E4; display: grid; }
.rc-angka span { font-size: 10.5px; font-weight: 700; color: #78716C; }
.rc-angka b { font-size: 16px; font-weight: 900; color: #1C1917; }
.rc-no { width: 30px; height: 30px; flex: none; border-radius: 10px; display: grid; place-items: center; color: #fff; }
.rc-pilar { padding: 12px 0; border-top: 1px solid #F0EFED; }
.rc-pilar:first-child { border-top: 0; padding-top: 0; }
.rc-butir { display: flex; align-items: center; gap: 10px; }
.rc-pilih { border-radius: 9px; border-color: #E7E5E4; font-size: 11.5px; padding: 4px 26px 4px 8px; }
.rc-titik { width: 10px; height: 10px; border-radius: 999px; margin-top: 5px; flex: none; }
@media (max-width: 640px) { .rc-alur { flex-direction: column; align-items: stretch; } .rc-alur > div:last-child > button { flex: 1 1 auto; } }
@media print { .rc-alur button, .rc-tab, .rc-tanpa-cetak { display: none !important; } }
:root[data-tema="gelap"] .rc-alur { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .rc-alur-diajukan { background: #2A2414; }
:root[data-tema="gelap"] .rc-alur-disetujui { background: #12271D; }
:root[data-tema="gelap"] .rc-alur-ditolak { background: #2A1616; }
:root[data-tema="gelap"] .rc-alur-selesai { background: #14233A; }
:root[data-tema="gelap"] .rc-hambat { color: #F5A9A9; }
:root[data-tema="gelap"] .rc-hijau { color: #8FE3BE; }
:root[data-tema="gelap"] .rc-tab { border-color: #2E373C; }
:root[data-tema="gelap"] .rc-tab button.aktif { color: #93C5FD; border-bottom-color: #93C5FD; }
:root[data-tema="gelap"] .rc-angka { border-color: #2E373C; }
:root[data-tema="gelap"] .rc-angka b { color: #E8EDEF; }
:root[data-tema="gelap"] .rc-pilar { border-color: #2E373C; }
:root[data-tema="gelap"] .rc-pilih { background: #20272B; border-color: #2E373C; color: #E8EDEF; }
</style>
