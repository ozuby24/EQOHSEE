<script setup lang="ts">
/**
 * Pusat kendali keamanan.
 *
 * Aturan tata letak halaman ini: setiap angka yang ditampilkan harus
 * bersambung dengan sesuatu yang dapat dikerjakan. Angka yang hanya
 * dapat dilihat melatih orang untuk berhenti melihatnya, dan halaman
 * keamanan yang sudah berhenti dilihat lebih buruk daripada tidak ada
 * halaman keamanan — ia memberi rasa terawasi tanpa pengawasan.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HalamanKeamanan } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanKeamanan>();

const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});

const nadaKeadaan: Record<string, string> = {
  aman: 'bg-emerald-100 text-emerald-800',
  perhatian: 'bg-amber-100 text-amber-800',
  gawat: 'bg-red-100 text-red-800',
};

const kalimatTekanan = computed(() => {
  if (props.ringkas.keadaan === 'gawat')
    return `Di atas ambang gawat (${props.ringkas.ambangGawat}/jam). Periksa alamat di bawah.`;
  if (props.ringkas.keadaan === 'perhatian')
    return `Di atas ambang perhatian (${props.ringkas.ambang}/jam), belum gawat.`;
  return 'Pada tingkat yang wajar untuk salah ketik.';
});

async function putus(id: string, nama: string) {
  if (!await tanya(`Putus sesi ${nama}?\n\nPerangkat itu langsung kehilangan aksesnya dan harus masuk lagi. Pekerjaan yang belum tersimpan di sana akan hilang.`)) return;

  router.delete(props.tautan.putusSesi, { data: { id }, preserveScroll: true });
}

async function bersihSesi() {
  if (!await tanya(`Bersihkan ${props.ringkas.sesiBasi} sesi kedaluwarsa?\n\nHanya sesi yang masa berlakunya sudah lewat. Tidak ada yang sedang masuk yang terputus.`)) return;

  router.post(props.tautan.bersihSesi, {}, { preserveScroll: true });
}

async function pangkasJejak() {
  if (!await tanya('Hapus jejak keamanan yang lebih tua dari masa simpan?\n\nJejak yang dihapus tidak dapat dipulihkan. Insiden yang lebih lama dari itu tidak akan dapat ditelusuri lagi.')) return;

  router.post(props.tautan.pangkasJejak, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-6xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative flex items-start justify-between gap-4 flex-wrap">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Pusat Kendali
          </span>
          <h2 class="stat mt-1.5">{{ judul }}</h2>
          <p class="text-[12px] text-white/70 mt-1.5 max-w-xl leading-relaxed">{{ subjudul }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <Link :href="tautan.diagnosa"
                class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white hover:bg-white/20 transition">
            Diagnosa
          </Link>
          <Link :href="tautan.sistem"
                class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white hover:bg-white/20 transition">
            Pusat Kendali
          </Link>
        </div>
      </div>
    </section>

    <div v-if="galat.keamanan"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px] text-red-700 leading-relaxed">
      {{ galat.keamanan }}
    </div>

    <!-- Angka pokok -->
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 eq-kpi">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-stone-500">Gagal masuk · 1 jam</div>
        <div class="text-[26px] font-bold text-cam-ink leading-tight mt-1">{{ ringkas.gagal1 }}</div>
        <span class="inline-block rounded-lg px-2 py-0.5 text-[10.5px] font-bold mt-1.5"
              :class="nadaKeadaan[ringkas.keadaan]">{{ ringkas.keadaan }}</span>
      </div>

      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 eq-kpi">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-stone-500">Gagal masuk · 24 jam</div>
        <div class="text-[26px] font-bold text-cam-ink leading-tight mt-1">{{ ringkas.gagal24 }}</div>
        <div class="text-[11px] text-stone-600 mt-1.5">termasuk salah ketik biasa</div>
      </div>

      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 eq-kpi">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-stone-500">Sedang masuk</div>
        <div class="text-[26px] font-bold text-cam-ink leading-tight mt-1">{{ ringkas.sesiAktif }}</div>
        <div class="text-[11px] text-stone-600 mt-1.5">perangkat aktif</div>
      </div>

      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 eq-kpi">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-stone-500">Sesi kedaluwarsa</div>
        <div class="text-[26px] font-bold text-cam-ink leading-tight mt-1">{{ ringkas.sesiBasi }}</div>
        <button v-if="ringkas.sesiBasi > 0" type="button" @click="bersihSesi"
                class="text-[11px] font-semibold text-cam-lime-deep hover:underline mt-1.5">
          Bersihkan
        </button>
        <div v-else class="text-[11px] text-stone-600 mt-1.5">tidak ada yang perlu dibuang</div>
      </div>
    </div>

    <p class="text-[11.5px] text-stone-600 leading-relaxed -mt-1.5 px-1">{{ kalimatTekanan }}</p>

    <!-- Alamat yang menekan -->
    <section v-if="menekan.length" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Alamat dengan kegagalan terbanyak · 24 jam</h3>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Satu alamat yang mencoba banyak surel berbeda adalah pemindaian. Satu alamat yang
        menghantam satu surel adalah penebakan sandi orang itu — hubungi pemilik akunnya, dan
        minta ia mengganti sandinya sebelum tebakannya kena.
      </p>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead>
            <tr class="text-left text-[10.5px] uppercase tracking-wider text-stone-600 border-b border-stone-200">
              <th class="py-2 pr-3 font-bold">Alamat</th>
              <th class="py-2 pr-3 font-bold">Gagal</th>
              <th class="py-2 pr-3 font-bold">Surel disasar</th>
              <th class="py-2 font-bold">Terakhir</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in menekan" :key="a.ip" class="border-b border-stone-100 last:border-0">
              <td class="py-2 pr-3 font-mono text-[11.5px] text-cam-ink">{{ a.ip }}</td>
              <td class="py-2 pr-3 font-bold" :class="a.jumlah >= ringkas.ambangGawat ? 'text-red-700' : 'text-cam-ink'">
                {{ a.jumlah }}
              </td>
              <td class="py-2 pr-3 text-stone-700">
                {{ a.sasaran }}
                <span class="text-stone-600">{{ a.sasaran > 1 ? '· pemindaian' : '· satu sasaran' }}</span>
              </td>
              <td class="py-2 text-stone-600">{{ a.terakhir }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Perangkat aktif -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Perangkat yang sedang masuk</h3>
        <Link :href="tautan.perangkatSaya"
              class="text-[11.5px] font-semibold text-cam-lime-deep hover:underline">
          Perangkat saya
        </Link>
      </div>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Memutus sesi mencabut aksesnya saat itu juga. Perangkat itu tidak diberi tahu — ia hanya
        menemukan dirinya kembali di halaman masuk pada klik berikutnya.
      </p>

      <p v-if="!sesi.length" class="text-[12px] text-stone-600">Tidak ada sesi aktif.</p>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead>
            <tr class="text-left text-[10.5px] uppercase tracking-wider text-stone-600 border-b border-stone-200">
              <th class="py-2 pr-3 font-bold">Pengguna</th>
              <th class="py-2 pr-3 font-bold">Alamat</th>
              <th class="py-2 pr-3 font-bold">Perangkat</th>
              <th class="py-2 pr-3 font-bold">Aktivitas</th>
              <th class="py-2 font-bold"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sesi" :key="s.id" class="border-b border-stone-100 last:border-0">
              <td class="py-2 pr-3">
                <div class="font-semibold text-cam-ink">{{ s.nama }}</div>
                <div class="text-[11px] text-stone-600">{{ s.email ?? 'belum masuk' }}</div>
              </td>
              <td class="py-2 pr-3 font-mono text-[11.5px] text-stone-700">{{ s.ip }}</td>
              <td class="py-2 pr-3 text-stone-700">{{ s.perangkat }}</td>
              <td class="py-2 pr-3 text-stone-600">{{ s.terakhir }}</td>
              <td class="py-2 text-right">
                <span v-if="s.iniSaya"
                      class="inline-block rounded-lg bg-cam-lime-soft px-2 py-0.5 text-[10.5px] font-bold text-cam-lime-deep">
                  perangkat ini
                </span>
                <button v-else type="button" @click="putus(s.id, s.nama)"
                        class="text-[11.5px] font-semibold text-red-600 hover:underline">
                  Putus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Akun tidur -->
    <section v-if="tidurAn.length" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Akun aktif yang lama tidak dipakai</h3>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Akun tidur adalah pintu yang tidak dijaga siapa pun, sebab tidak ada lagi yang merasa
        memilikinya. Yang paling sering terlewat bukan akun mantan pegawai, melainkan akun yang
        dibuat untuk satu pelatihan lalu tidak pernah ditutup.
      </p>

      <div class="grid gap-2 sm:grid-cols-2">
        <Link v-for="u in tidurAn" :key="u.id" :href="u.url"
              class="rounded-xl border border-stone-200 px-3.5 py-2.5 hover:border-cam-lime hover:bg-cam-lime-soft transition">
          <div class="flex items-center gap-2">
            <span class="text-[12.5px] font-semibold text-cam-ink">{{ u.nama }}</span>
            <span v-if="u.admin"
                  class="rounded-md bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-800">admin</span>
          </div>
          <div class="text-[11px] text-stone-600 mt-0.5">{{ u.email }} · {{ u.terakhir }}</div>
        </Link>
      </div>
    </section>

    <!-- Tajuk keamanan -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Tajuk keamanan</h3>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Dipasang aplikasi pada setiap tanggapan, jadi ia tetap berlaku ketika permintaannya tidak
        lewat nginx — <code>artisan serve</code>, penerusan porta lewat SSH, atau konfigurasi nginx
        yang tertimpa pembaruan paket.
      </p>

      <div class="space-y-2">
        <div v-for="t in tajuk" :key="t.nama"
             class="rounded-xl border border-stone-200 px-3.5 py-2.5">
          <div class="flex items-center gap-2 flex-wrap">
            <code class="text-[11.5px] font-bold text-cam-ink">{{ t.nama }}</code>
            <!-- stone-100, bukan stone-200: hanya stone-100 yang punya
                 pasangan gelap di tema. Pada stone-200, latarnya tetap
                 terang sementara teksnya ikut dibalik menjadi terang —
                 terukur 1,09:1 di peramban, praktis tidak terbaca. -->
            <span class="rounded-lg px-2 py-0.5 text-[10.5px] font-bold"
                  :class="t.ada ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-800'">
              {{ t.ada ? 'aktif' : 'mati' }}
            </span>
            <span class="text-[11px] text-stone-600 font-mono">{{ t.nilai }}</span>
          </div>
          <p class="text-[11px] text-stone-600 leading-relaxed mt-1">{{ t.guna }}</p>
        </div>
      </div>
    </section>

    <!-- Riwayat -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Jejak akses terakhir</h3>
        <button type="button" @click="pangkasJejak"
                class="text-[11.5px] font-semibold text-stone-600 hover:text-red-600 hover:underline">
          Pangkas jejak lama
        </button>
      </div>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Sandi tidak pernah ikut tercatat — juga sandi yang salah, sebab orang memakai sandi
        yang mirip di banyak tempat.
      </p>

      <p v-if="!riwayat.length" class="text-[12px] text-stone-600">Belum ada peristiwa tercatat.</p>

      <ul v-else class="space-y-1.5">
        <li v-for="r in riwayat" :key="r.id"
            class="flex items-start gap-2.5 rounded-xl border border-stone-100 px-3 py-2">
          <span class="mt-0.5 inline-block rounded-lg px-2 py-0.5 text-[10.5px] font-bold shrink-0"
                :class="r.gagal ? 'bg-red-100 text-red-800' : 'bg-stone-100 text-stone-700'">
            {{ r.peristiwa }}
          </span>
          <div class="min-w-0">
            <div class="text-[12px] text-cam-ink font-semibold truncate">{{ r.siapa }}</div>
            <div class="text-[11px] text-stone-600">
              {{ r.ip }} · {{ r.perangkat }} · {{ r.kapan }}
            </div>
          </div>
        </li>
      </ul>
    </section>

  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
