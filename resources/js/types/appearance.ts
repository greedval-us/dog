export type AssetKind = 'portrait' | 'background';
export type AssetCurrency = 'coins' | 'gems';

export type AssetPrice = {
    currency: AssetCurrency;
    amount: number;
};

export type AppearanceAsset = {
    id: number;
    kind: AssetKind;
    name: string;
    prices: AssetPrice[];
    unlocked: boolean;
};

export type PetAppearance = {
    assets: AppearanceAsset[];
    portraitId: number | null;
    backgroundId: number | null;
};
