<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Package, ShoppingBag } from '@lucide/vue';
import { useId } from 'vue';
import ItemBonuses from '@/components/ItemBonuses.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { careCategoryLabels as categories } from '@/lib/petCarePresentation';
import { index as shop } from '@/routes/shop';
import type {
    CareCategory,
    CareItem,
    CareItemSelection,
    CareOption,
} from '@/types/pet-care';

defineProps<{
    option: CareOption;
    selectedItems: CareItem[];
    itemsFor: (category: CareCategory) => CareItem[];
    cursors: Partial<Record<CareCategory, string | null>>;
    loading: boolean;
    failed: boolean;
    processing: boolean;
    missingItems: boolean;
}>();
const selection = defineModel<CareItemSelection>({ required: true });
const emit = defineEmits<{ reload: []; loadMore: [category: CareCategory] }>();
const { t } = useI18n();
const id = useId();
</script>

<template>
    <section
        v-if="option.requirements.length || option.optional.length"
        class="pet-care-supplies"
        :aria-label="t('Supplies')"
    >
        <p v-if="loading" class="dashboard-loading" role="status">
            {{ t('Loading...') }}
        </p>
        <div v-if="failed" role="alert">
            <p>
                {{ t('Could not load data. Please retry.') }}
            </p>
            <Button type="button" :disabled="loading" @click="emit('reload')">{{
                t('Retry')
            }}</Button>
        </div>
        <div
            v-for="category in [...option.requirements, ...option.optional]"
            :key="category"
            class="pet-care-item-field"
        >
            <div class="pet-care-item-heading">
                <label
                    :for="
                        itemsFor(category).length
                            ? id + '-' + category
                            : undefined
                    "
                    ><Package :size="15" aria-hidden="true" />{{
                        t(categories[category])
                    }}</label
                >
                <span>{{
                    t('Uses per action: {count}', {
                        count: option.uses[category] ?? 1,
                    })
                }}</span>
            </div>
            <select
                v-if="itemsFor(category).length"
                :id="id + '-' + category"
                v-model="selection[category]"
                :disabled="processing"
            >
                <option
                    v-if="option.optional.includes(category)"
                    :value="undefined"
                >
                    {{ t('Do not use (optional)') }}
                </option>
                <option
                    v-for="item in itemsFor(category)"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.name }} ·
                    {{
                        t('{count} uses left', {
                            count: item.remainingUses,
                        })
                    }}
                    ·
                    {{
                        t('Quality {value}', {
                            value: item.quality,
                        })
                    }}
                </option>
            </select>
            <p v-else-if="!loading && !failed" class="pet-care-missing">
                {{ t('No suitable item in your inventory.') }}
            </p>
            <Button
                v-if="cursors[category]"
                type="button"
                variant="outline"
                size="sm"
                :disabled="loading || processing"
                @click="emit('loadMore', category)"
                >{{ t('Load more') }}</Button
            >
        </div>
        <ItemBonuses
            v-for="item in selectedItems"
            :key="item.id"
            :bonuses="item.bonuses"
            :effects="[]"
        />
        <div
            v-if="missingItems && !loading && !failed"
            class="pet-care-supply-help"
        >
            <p>
                {{ t('Choose another option or get supplies.') }}
            </p>
            <Button as-child variant="outline" size="sm"
                ><Link :href="shop()"
                    ><ShoppingBag :size="15" aria-hidden="true" />{{
                        t('Go to shop')
                    }}</Link
                ></Button
            >
        </div>
    </section>
</template>
