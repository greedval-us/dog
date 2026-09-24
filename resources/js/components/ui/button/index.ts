import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';
export { default as Button } from './Button.vue';
export const buttonVariants = cva('ui-button', {
    variants: {
        variant: {
            default: 'ui-button--primary', destructive: 'ui-button--destructive',
            outline: 'ui-button--outline', secondary: 'ui-button--secondary',
            ghost: 'ui-button--ghost', link: 'ui-button--link', plain: 'ui-button--plain',
        },
        size: { default: 'ui-button--md', sm: 'ui-button--sm', lg: 'ui-button--lg', icon: 'ui-button--icon', 'icon-sm': 'ui-button--icon-sm', 'icon-lg': 'ui-button--icon-lg' },
    },
    defaultVariants: { variant: 'default', size: 'default' },
});
export type ButtonVariants = VariantProps<typeof buttonVariants>;
