<script setup>
const svgVersion = new Date().getTime();
</script>

<template>
    <img :src="`/images/website-logo.svg?v=${svgVersion}`" alt="Interior Design" class="ml-3" style="width: 250px; height: auto;">
</template>