<script setup lang="ts">
/** Baris identitas formulir resmi: label tebal di kiri, kotak isian di kanan. */
defineProps<{ baris: Array<[string, string | null | undefined]> }>();
</script>

<template>
  <table class="w-full mb-5 border-separate border-spacing-y-1.5">
    <tbody>
      <tr v-for="b in baris" :key="b[0]">
        <td class="w-[28%] pr-3 font-bold text-slate-900 align-middle">{{ b[0] }}</td>
        <td><div class="kotak-isi">{{ b[1] || ' ' }}</div></td>
      </tr>
    </tbody>
  </table>
</template>
