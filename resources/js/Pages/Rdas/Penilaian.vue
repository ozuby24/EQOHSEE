<script setup lang="ts">
/*
  Penilaian keberhasilan & serah terima (BAST).

  Alur BPDAS: pemegang PPKH mengajukan permohonan → Direktur TKTRH a.n.
  Dirjen PDASRH membentuk tim (ketua Kepala BPDAS, sekretaris Dinas
  Kehutanan, anggota pemangku kawasan) → tim menilai → berhasil diserah-
  terimakan, tidak berhasil kembali ke pemeliharaan lanjutan. Serah terima
  boleh bertahap. Halaman ini menampilkan setiap permohonan beserta berapa
  lama ia sudah menunggu — keterlambatan di tahap mana pun terlihat.
*/
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { n } from '../../energi';
import { fase, penilaian, warnaTumbuh, tanggal } from '../../rdas';

const props = propHalaman();
const { dialog, minta, batal, lanjut } = useDialog();
const galat = computed(() => (props.errors ?? {}) as Record<string, string>);
const daftar = computed(() => props.penilaian ?? []);
const kini = new Date().toISOString().slice(0, 10);

async function aksi(p: any, a: string) {
  const isi: Record<string, any> = { aksi: a };
  if (a === 'tim') {
    const no = await minta({ judul: 'SK tim penilai', label: 'Nomor SK tim penilai', wajib: true, labelAksi: 'Lanjut' }); if (no === null) return;
    const tgl = await minta({ judul: 'SK tim penilai', label: 'Tanggal SK', jenis: 'tanggal', nilai: kini, wajib: true, labelAksi: 'Lanjut' }); if (tgl === null) return;
    const ketua = await minta({ judul: 'SK tim penilai', label: 'Ketua tim (Kepala BPDAS)', wajib: true, labelAksi: 'Lanjut' }); if (ketua === null) return;
    const mulai = await minta({ judul: 'Jadwal penilaian lapangan', label: 'Tanggal mulai (boleh kosong)', jenis: 'tanggal', labelAksi: 'Simpan' }); if (mulai === null) return;
    Object.assign(isi, { sk_tim_nomor: no, sk_tim_at: tgl, ketua_tim: ketua, jadwal_mulai: mulai || null });
  } else if (a === 'hasil') {
    const t = await minta({ judul: `Hasil penilaian ${p.kode}`, label: 'Persen tumbuh hasil tim (%)', jenis: 'angka', wajib: true, labelAksi: 'Lanjut' }); if (t === null) return;
    const s = await minta({ judul: `Hasil penilaian ${p.kode}`, label: 'Batang sehat per hektar', jenis: 'angka', wajib: true, labelAksi: 'Lanjut' }); if (s === null) return;
    const l = await minta({ judul: `Hasil penilaian ${p.kode}`, label: `Luas dinilai berhasil (ha, maks ${n(p.luas_diajukan_ha, 2)})`, jenis: 'angka', nilai: String(p.luas_diajukan_ha), wajib: true, labelAksi: 'Simpan hasil' }); if (l === null) return;
    Object.assign(isi, { persen_tumbuh: Number(t), sehat_ha: Number(s), luas_berhasil_ha: Number(l) });
  } else if (a === 'tunda') {
    const c = await minta({ judul: 'Tunda penilaian?', label: 'Alasan penundaan', jenis: 'panjang', wajib: true, labelAksi: 'Tunda', nada: 'bahaya' }); if (c === null) return;
    isi.catatan = c;
  } else if (a === 'bast') {
    const no = await minta({ judul: 'Berita Acara Serah Terima', label: 'Nomor BAST', wajib: true, labelAksi: 'Lanjut' }); if (no === null) return;
    const tgl = await minta({ judul: 'Berita Acara Serah Terima', label: 'Tanggal BAST', jenis: 'tanggal', nilai: kini, wajib: true, labelAksi: 'Catat BAST' }); if (tgl === null) return;
    Object.assign(isi, { bast_nomor: no, bast_at: tgl });
  }
  router.post(`/rehab-das/penilaian/${p.id}`, isi, { preserveScroll: true });
}
const tombol: Record<string, [string, string][]> = {
  diajukan: [['tim', 'Catat SK tim'], ['tunda', 'Tunda']], tim_dibentuk: [['hasil', 'Catat hasil'], ['tunda', 'Tunda']], dinilai: [['hasil', 'Catat hasil'], ['tunda', 'Tunda']],
  ditunda: [['lanjut', 'Lanjutkan'], ['tim', 'Catat SK tim']], berhasil: [['bast', 'Catat BAST']],
};
</script>

<template>
  <Head title="Penilaian & BAST" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-[12.5px] text-stone-500 max-w-3xl">Syarat permohonan: rencana penanaman, laporan penanaman, geotagging tanaman, dan peta lokasi. Usia tanaman minimal 36 bulan dengan keberhasilan ≥ 75%.</p>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <section class="pn-corong">
      <div v-for="c in props.corong" :key="c.kode" class="pn-langkah" :style="{ borderTopColor: penilaian(c.kode).warna }">
        <p class="text-[11px] font-bold text-stone-500">{{ c.label }}</p>
        <p class="text-[22px] font-black text-cam-ink num">{{ c.jumlah }}</p>
        <p class="text-[11px] text-stone-500 num">{{ n(c.luas, 1) }} ha</p>
      </div>
    </section>

    <Kartu judul="Permohonan penilaian" :ket="`${daftar.length} permohonan`" ikon="clipboard-check" warna="biru" rapat>
      <p v-if="galat.aksi" class="px-5 pb-2 text-[12px] pn-galat">{{ galat.aksi }}</p>
      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead><tr class="pn-th"><th>Permohonan</th><th>Lokasi &amp; luas diajukan</th><th>Tim penilai</th><th class="text-right">Hasil tim</th><th>Status &amp; BAST</th><th /></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="p in daftar" :key="p.id" class="pn-tr">
              <td><p class="font-bold text-cam-ink whitespace-nowrap">{{ p.kode }} · tahap {{ p.tahap }}</p><p class="text-[10.5px] text-stone-500">{{ tanggal(p.diajukan_at) }}<template v-if="p.umur_hari !== null && !['serah_terima'].includes(p.status)"> · {{ p.umur_hari }} hari</template></p></td>
              <td><Link v-if="p.blok" :href="`/rehab-das/blok/${p.blok.id}`" class="font-bold text-cam-ink hover:underline">{{ p.blok.kode }}</Link> <span class="num font-bold">· {{ n(p.luas_diajukan_ha, 2) }} ha</span><p class="text-[10.5px] text-stone-500">{{ p.blok?.nama }}</p></td>
              <td><template v-if="p.sk_tim_nomor"><p>{{ p.ketua_tim }}</p><p class="text-[10.5px] text-stone-500"><span class="[overflow-wrap:anywhere]">{{ p.sk_tim_nomor }}</span> · <span class="whitespace-nowrap">{{ tanggal(p.sk_tim_at) }}</span></p></template><span v-else class="text-stone-400">—</span></td>
              <td class="text-right"><template v-if="p.luas_berhasil_ha !== null"><b class="num" :style="{ color: warnaTumbuh(p.persen_tumbuh) }">{{ n(p.persen_tumbuh, 1) }}%</b><p class="text-[10.5px] text-stone-500 num">{{ n(p.luas_berhasil_ha, 2) }} ha · {{ n(p.sehat_ha, 0) }}/ha</p></template><span v-else class="text-stone-400">—</span></td>
              <td>
                <span class="pr-pil" :class="penilaian(p.status).kelas">{{ penilaian(p.status).pendek }}</span>
                <p v-if="p.bast_nomor" class="text-[11px] font-bold mt-1"><span class="[overflow-wrap:anywhere]">{{ p.bast_nomor }}</span> <span class="font-normal text-stone-500 whitespace-nowrap">· {{ tanggal(p.bast_at) }}</span></p>
                <p v-if="p.catatan" class="text-[10.5px] text-stone-500 mt-0.5 max-w-[260px]">{{ p.catatan }}</p>
              </td>
              <td class="text-right"><div class="flex flex-wrap justify-end gap-1"><button v-for="t in (tombol[p.status] ?? [])" :key="t[0]" class="eq-btn-mini" @click="aksi(p, t[0])">{{ t[1] }}</button></div></td>
            </tr>
            <tr v-if="!daftar.length"><td colspan="6" class="px-5 py-6 text-center text-stone-400">Belum ada permohonan penilaian.</td></tr>
          </tbody>
        </table>
      </div>
    </Kartu>

    <Kartu judul="Lokasi P2 & siap dinilai" ket="Prasyarat dibaca dari data lapangan — lengkapi yang merah sebelum mengajukan" ikon="trees" warna="hijau" rapat>
      <ul class="divide-y divide-stone-100">
        <li v-for="b in props.kandidat" :key="b.id" class="px-5 py-3">
          <div class="flex flex-wrap items-center gap-3">
            <Link :href="`/rehab-das/blok/${b.id}`" class="min-w-0 grow basis-[240px]">
              <p class="text-[12.5px] font-bold text-cam-ink">{{ b.kode }} · {{ b.nama }}</p>
              <p class="text-[11px] text-stone-500">{{ n(b.luas_ha, 1) }} ha · usia {{ b.usia_bulan }} bulan · tumbuh {{ b.persen_tumbuh === null ? '—' : n(b.persen_tumbuh, 1) + '%' }}</p>
            </Link>
            <span class="pr-pil" :class="fase(b.fase).kelas">{{ fase(b.fase).pendek }}</span>
            <span class="pr-pil" :class="b.kelayakan.layak ? 'pr-hijau' : 'pr-kuning'">{{ b.kelayakan.terpenuhi }}/{{ b.kelayakan.jumlah }} prasyarat</span>
          </div>
          <div class="flex flex-wrap gap-1.5 mt-2">
            <span v-for="c in b.kelayakan.cek" :key="c.kode" class="pn-cek" :class="c.ok ? 'ok' : c.wajib ? 'gagal' : 'catat'" :title="c.bukti"><Ikon :nama="c.ok ? 'circle-check' : 'circle-x'" :ukuran="12" />{{ c.label }}</span>
          </div>
        </li>
        <li v-if="!(props.kandidat ?? []).length" class="px-5 py-6 text-center text-[12px] text-stone-400">Tidak ada lokasi pada P2 atau usia ≥ 36 bulan.</li>
      </ul>
    </Kartu>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.pn-corong { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
.pn-langkah { border-radius: 14px; background: #fff; border: 1px solid #E7E5E4; border-top-width: 4px; padding: 10px 12px; }
.pn-galat { color: #B91C1C; }
.pn-th th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; white-space: nowrap; }
.pn-th th:first-child, .pn-tr td:first-child { padding-left: 20px; }
.pn-tr td { padding: 9px 12px; vertical-align: top; }
.pn-cek { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
.pn-cek.ok { background: #DCFCE7; color: #166534; } .pn-cek.gagal { background: #FEE2E2; color: #991B1B; } .pn-cek.catat { background: #F5F5F4; color: #57534E; }
:root[data-tema="gelap"] .pn-langkah { background: #1B2124; border-color: #2E373C; }
:root[data-tema="gelap"] .pn-galat { color: #F5A9A9; }
:root[data-tema="gelap"] .pn-th th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .pn-cek.ok { background: #143A2C; color: #8FE3BE; } :root[data-tema="gelap"] .pn-cek.gagal { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .pn-cek.catat { background: #2A3034; color: #B4BEC3; }
</style>
