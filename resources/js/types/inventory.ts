export type InventoryItem = {
    id: number;
    name: string;
    category: string;
    categoryCode: string;
    quality: number;
    usageLimit: number;
    remainingUses: number;
    characteristics: Record<string, number | string | boolean>;
    acquiredAt: string | null;
};
