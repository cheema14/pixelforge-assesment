<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-rose-400">
            {{ message }}
        </p>
    </div>
</template>
