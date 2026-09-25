export type PetSlot = {
    number: number;
    unlocked: boolean;
    purchasable: boolean;
    coins: number;
    gems: number;
    pet: { id: number; name: string } | null;
};
