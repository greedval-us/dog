export function purchaseShortfall(price: number, balance: number): number {
    return Math.max(0, price - balance);
}
