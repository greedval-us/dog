<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

defineProps<{
    previousCursor: string | null;
    nextCursor: string | null;
    pageLink: (cursor: string) => string;
    label: string;
    disabled: boolean;
}>();
const emit = defineEmits<{ start: []; finish: [] }>();
const { t } = useI18n();
</script>

<template>
    <nav
        v-if="previousCursor || nextCursor"
        class="shop-pagination"
        :aria-label="label"
        :inert="disabled"
    >
        <Button v-if="previousCursor" as-child variant="outline">
            <Link
                :href="pageLink(previousCursor)"
                preserve-scroll
                @start="emit('start')"
                @finish="emit('finish')"
                ><ArrowLeft :size="17" aria-hidden="true" />{{
                    t('Previous')
                }}</Link
            >
        </Button>
        <Button v-if="nextCursor" as-child variant="outline">
            <Link
                :href="pageLink(nextCursor)"
                preserve-scroll
                @start="emit('start')"
                @finish="emit('finish')"
                >{{ t('Next') }}<ArrowRight :size="17" aria-hidden="true"
            /></Link>
        </Button>
    </nav>
</template>
