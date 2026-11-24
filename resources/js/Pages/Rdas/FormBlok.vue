<script setup lang="ts">
/*
  Pendaftaran lokasi Rehab DAS (blok) sesuai SK Penetapan Lokasi dan
  Rencana Kegiatan Penanaman: letak DAS & fungsi kawasan, luas, jarak
  tanam, komposisi jenis, kontraktor, dan anggaran.

  Komposisi diperiksa saat diketik: jenis lokal berdaur panjang minimal
  40%, cepat tumbuh maksimal 60%, jumlah 100%. Fase tidak diisi — fase
  dihitung dari tanggal SK, RKP, dan tanggal tanam P0.
*/
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import { n } from '../../energi';
import { KATEGORI_JENIS } from '../../rdas';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const sunting = computed(() => !!props.blok?.id);
const b = props.blok ?? {};
const form = useForm<Record<string, any>>({
  ppkh_id: b.ppkh_id ?? null, nama: b.nama ?? '', sk_penlok_nomor: b.sk_penlok_nomor ?? '', sk_penlok_tanggal: b.sk_penlok_tanggal ?? '',
  das: b.das ?? '', sub_das: b.sub_das ?? '', kabupaten: b.kabupaten ?? '', kecamatan: b.kecamatan ?? '', desa: b.desa ?? '',
  fungsi_kawasan: b.fungsi_kawasan ?? 'HL', pemangku: b.pemangku ?? '', ekosistem: b.ekosistem ?? 'daratan', luas_ha: b.luas_ha ?? 100,
  jarak_tanam: b.jarak_tanam ?? '4 × 4 m', kerapatan: b.kerapatan ?? 625, komposisi: (b.komposisi ?? []).map((k: any) => ({ ...k })),
  rkp_nomor: b.rkp_nomor ?? '', rkp_disahkan_at: b.rkp_disahkan_at ?? '', tanggal_tanam: b.tanggal_tanam ?? '', kontraktor: b.kontraktor ?? '',
  anggaran_rp: b.anggaran_rp ?? 0, lat: b.lat ?? null, lng: b.lng ?? null, catatan: b.catatan ?? '',
});
const jenisMap = computed(() => Object.fromEntries((pil.value.jenis ?? []).map((j: any) => [j.nilai, j])));
const cek = computed(() => {
  const per = { lokal: 0, cepat: 0, mpts: 0 } as Record<'lokal' | 'cepat' | 'mpts', number>;
  let total = 0;
  for (const k of form.komposisi as any[]) { const kat = (jenisMap.value[k.jenis]?.kategori ?? 'lokal') as 'lokal' | 'cepat' | 'mpts'; per[kat] += Number(k.persen) || 0; total += Number(k.persen) || 0; }
  return { lokal: per.lokal, cepat: per.cepat, mpts: per.mpts, total, lokalOk: per.lokal >= 40, cepatOk: per.cepat <= 60, genap: Math.abs(total - 100) < 0.6 };
});
const jarak = { '3 × 3 m': 1111, '4 × 4 m': 625, '5 × 5 m': 400, '3 × 4 m': 833, '5 × 2 m': 1000 } as Record<string, number>;
function pilihJarak(j: string) { form.jarak_tanam = j; if (jarak[j]) form.kerapatan = jarak[j]; }
function tambahJenis() { form.komposisi.push({ jenis: 'ulin', persen: 0 }); }
function hapusJenis(i: number) { form.komposisi.splice(i, 1); }
const ppkhTerpilih = computed(() => (props.ppkh ?? []).find((p: any) => p.id === form.ppkh_id));
function kirim() {
  if (sunting.value) form.put(`/rehab-das/blok/${props.blok.id}`);
  else form.post('/rehab-das/blok');
}
</script>

<template>
  <Head :title="sunting ? 'Ubah lokasi Rehab DAS' : 'Daftarkan lokasi Rehab DAS'" />
  <div v-if="!(props.ppkh ?? []).length" class="fb-kosong">
    <Ikon nama="file-text" :ukuran="28" />
    <p class="text-[14px] font-bold text-cam-ink">Belum ada PPKH terdaftar</p>
    <p class="text-[12.5px] text-stone-500">Lokasi Rehab DAS selalu menginduk pada satu SK PPKH. Daftarkan PPKH-nya lebih dulu.</p>
    <Link href="/rehab-das/kewajiban" class="eq-btn-utama mt-2">Daftarkan PPKH</Link>
  </div>
  <form v-else class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] items-start" @submit.prevent="kirim">
    <div class="space-y-4">
      <Kartu :judul="sunting ? `Ubah ${props.blok.kode}` : 'Penetapan lokasi'" ket="Sesuai SK Penetapan Lokasi dari Menteri/Dirjen PDASRH" ikon="map-pin" warna="amber">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fb-l md:col-span-2">PPKH induk
            <select v-model="form.ppkh_id" :disabled="sunting" class="fb-i"><option v-for="p in props.ppkh" :key="p.id" :value="p.id">{{ p.kode }} · {{ p.nomor_sk }}</option></select>
          </label>
          <label class="fb-l md:col-span-2">Nama lokasi<input v-model="form.nama" required maxlength="150" placeholder="mis. Hulu Belayan — Desa Long Beleh" class="fb-i"></label>
          <label class="fb-l">Nomor SK Penetapan Lokasi<input v-model="form.sk_penlok_nomor" maxlength="150" class="fb-i"></label>
          <label class="fb-l">Tanggal SK Penlok<input v-model="form.sk_penlok_tanggal" type="date" class="fb-i"></label>
          <label class="fb-l">DAS<input v-model="form.das" required maxlength="80" placeholder="DAS Mahakam" class="fb-i"></label>
          <label class="fb-l">Sub-DAS<input v-model="form.sub_das" maxlength="80" class="fb-i"></label>
          <label class="fb-l">Kabupaten<input v-model="form.kabupaten" maxlength="80" :placeholder="ppkhTerpilih?.kabupaten" class="fb-i"></label>
          <label class="fb-l">Kecamatan / desa<input v-model="form.desa" maxlength="120" class="fb-i"></label>
          <label class="fb-l">Fungsi kawasan<select v-model="form.fungsi_kawasan" class="fb-i"><option v-for="f in pil.fungsi" :key="f.nilai" :value="f.nilai">{{ f.nilai }} — {{ f.label }}</option></select></label>
          <label class="fb-l">Pemangku / pengelola kawasan<input v-model="form.pemangku" maxlength="150" placeholder="mis. KPH Kendilo" class="fb-i"></label>
          <label class="fb-l">Tipe ekosistem<select v-model="form.ekosistem" class="fb-i"><option v-for="e in pil.ekosistem" :key="e.nilai" :value="e.nilai">{{ e.label }}</option></select></label>
          <label class="fb-l">Luas (ha)<input v-model.number="form.luas_ha" type="number" step="0.01" min="0.1" required class="fb-i num"></label>
          <label class="fb-l">Lintang pusat (lat)<input v-model.number="form.lat" type="number" step="0.0000001" class="fb-i num"></label>
          <label class="fb-l">Bujur pusat (lng)<input v-model.number="form.lng" type="number" step="0.0000001" class="fb-i num"></label>
        </div>
      </Kartu>

      <Kartu judul="Rencana Kegiatan Penanaman" ket="Disusun & disahkan PPKH, disupervisi BPDAS/pemangku" ikon="clipboard-check" warna="ungu">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fb-l">Nomor RKP<input v-model="form.rkp_nomor" maxlength="150" class="fb-i"></label>
          <label class="fb-l">Tanggal RKP disahkan<input v-model="form.rkp_disahkan_at" type="date" class="fb-i"></label>
          <label class="fb-l">Tanggal tanam P0<input v-model="form.tanggal_tanam" type="date" class="fb-i"></label>
          <label class="fb-l">Kontraktor pelaksana<input v-model="form.kontraktor" maxlength="150" class="fb-i"></label>
          <label class="fb-l">Anggaran RKP (Rp)<input v-model.number="form.anggaran_rp" type="number" step="1000000" min="0" class="fb-i num"></label>
          <div class="fb-l">Jarak tanam
            <div class="flex flex-wrap gap-1.5 mt-1">
              <button v-for="(k, j) in jarak" :key="j" type="button" class="fb-chip" :class="{ aktif: form.jarak_tanam === j }" @click="pilihJarak(String(j))">{{ j }} · {{ k }}/ha</button>
            </div>
          </div>
          <label class="fb-l md:col-span-2">Catatan<textarea v-model="form.catatan" rows="2" maxlength="2000" class="fb-i" /></label>
        </div>
        <p v-if="Object.keys(form.errors).length" class="mt-3 text-[12px] fb-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="flex flex-wrap gap-2 mt-4">
          <button class="eq-btn-utama" :disabled="form.processing">{{ sunting ? 'Simpan perubahan' : 'Simpan lokasi' }}</button>
          <Link :href="sunting ? `/rehab-das/blok/${props.blok.id}` : '/rehab-das/kewajiban'" class="eq-btn-lain">Batal</Link>
        </div>
      </Kartu>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-4">
      <Kartu judul="Komposisi jenis" :ket="`${n(form.luas_ha * form.kerapatan, 0)} batang rancangan · ${form.kerapatan} btg/ha`" ikon="trees" warna="hijau">
        <ul class="space-y-2">
          <li v-for="(k, i) in (form.komposisi as any[])" :key="i" class="grid grid-cols-[minmax(0,1fr)_76px_auto_auto] items-center gap-2">
            <select v-model="k.jenis" class="fb-i !mt-0 min-w-0" :aria-label="`Jenis ${Number(i) + 1}`">
              <optgroup v-for="(lbl, kat) in pil.kategoriJenis" :key="kat" :label="lbl">
                <option v-for="j in (pil.jenis ?? []).filter((x: any) => x.kategori === kat)" :key="j.nilai" :value="j.nilai">{{ j.nama }} ({{ j.latin }})</option>
              </optgroup>
            </select>
            <input v-model.number="k.persen" type="number" min="0" max="100" step="1" class="fb-i !mt-0 num" :aria-label="`Persen jenis ${Number(i) + 1}`">
            <span class="text-[11px] text-stone-500">%</span>
            <button type="button" class="fb-x" :aria-label="`Hapus jenis ${Number(i) + 1}`" @click="hapusJenis(Number(i))">×</button>
          </li>
        </ul>
        <button type="button" class="eq-btn-mini mt-2" @click="tambahJenis">+ Tambah jenis</button>
        <div class="fb-komposisi mt-4">
          <i v-for="(lbl, kat) in KATEGORI_JENIS" :key="kat" :style="{ width: Math.min(100, (cek as any)[kat]) + '%', background: lbl.warna }" :title="lbl.label" />
        </div>
        <ul class="mt-3 space-y-1 text-[12px]">
          <li class="flex justify-between"><span>Lokal berdaur panjang (min 40%)</span><b :class="cek.lokalOk ? 'fb-ok' : 'fb-galat'">{{ n(cek.lokal, 0) }}%</b></li>
          <li class="flex justify-between"><span>Cepat tumbuh (maks 60%)</span><b :class="cek.cepatOk ? 'fb-ok' : 'fb-galat'">{{ n(cek.cepat, 0) }}%</b></li>
          <li class="flex justify-between"><span>MPTS</span><b>{{ n(cek.mpts, 0) }}%</b></li>
          <li class="flex justify-between border-t border-stone-100 pt-1"><span>Jumlah</span><b :class="cek.genap ? 'fb-ok' : 'fb-galat'">{{ n(cek.total, 0) }}%</b></li>
        </ul>
        <p class="text-[11px] text-stone-500 mt-2">Pilih jenis asli lokal yang mengundang satwa pemencar biji; di hulu utamakan jenis yang efektif melindungi tata air.</p>
      </Kartu>
    </aside>
  </form>
</template>

<style scoped>
.fb-kosong { max-width: 460px; margin: 40px auto; text-align: center; display: grid; justify-items: center; gap: 6px; color: #78716C; }
.fb-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.fb-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.fb-chip { padding: 5px 9px; border-radius: 9px; border: 1px solid #E7E5E4; background: #fff; font-size: 11px; font-weight: 700; color: #57534E; }
.fb-chip.aktif { border-color: #15803D; background: #F0FDF4; color: #14532D; }
.fb-x { width: 26px; height: 26px; border-radius: 8px; border: 1px solid #E7E5E4; color: #A8A29E; font-weight: 800; }
.fb-komposisi { display: flex; height: 12px; border-radius: 999px; overflow: hidden; background: #F0EFED; }
.fb-komposisi i { display: block; height: 100%; }
.fb-ok { color: #15803D; } .fb-galat { color: #B91C1C; }
:root[data-tema="gelap"] .fb-l { color: #93A1A8; }
:root[data-tema="gelap"] .fb-chip, :root[data-tema="gelap"] .fb-x { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .fb-chip.aktif { background: #12271D; border-color: #22C55E; color: #8FE3BE; }
:root[data-tema="gelap"] .fb-komposisi { background: #2E373C; }
:root[data-tema="gelap"] .fb-ok { color: #8FE3BE; } :root[data-tema="gelap"] .fb-galat { color: #F5A9A9; }
</style>
