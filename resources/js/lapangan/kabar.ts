import { reactive } from 'vue';

/** Satu kabar singkat di atas tab bawah — pesan kilat server maupun hasil sinkron. */
export const kabarKini = reactive({ pesan: '', galat: false, n: 0 });

let pewaktu: number | undefined;

export function kabar(pesan: string, galat = false, lama = 4200): void {
  kabarKini.pesan = pesan;
  kabarKini.galat = galat;
  kabarKini.n++;
  window.clearTimeout(pewaktu);
  pewaktu = window.setTimeout(() => { kabarKini.pesan = ''; }, lama);
}

export function tutupKabar(): void {
  kabarKini.pesan = '';
}
