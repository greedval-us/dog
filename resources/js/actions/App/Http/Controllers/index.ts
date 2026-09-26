import LocaleController from './LocaleController'
import PlayerAvatarController from './PlayerAvatarController'
import DashboardController from './DashboardController'
import ShopController from './ShopController'
import InventoryController from './InventoryController'
import PetSlotController from './PetSlotController'
import PetAppearanceController from './PetAppearanceController'
import AssetPurchaseController from './AssetPurchaseController'
import AssetImageController from './AssetImageController'
import PlayerProfileController from './PlayerProfileController'
import KennelController from './KennelController'
import GameImageController from './GameImageController'
import Settings from './Settings'
const Controllers = {
    LocaleController: Object.assign(LocaleController, LocaleController),
PlayerAvatarController: Object.assign(PlayerAvatarController, PlayerAvatarController),
DashboardController: Object.assign(DashboardController, DashboardController),
ShopController: Object.assign(ShopController, ShopController),
InventoryController: Object.assign(InventoryController, InventoryController),
PetSlotController: Object.assign(PetSlotController, PetSlotController),
PetAppearanceController: Object.assign(PetAppearanceController, PetAppearanceController),
AssetPurchaseController: Object.assign(AssetPurchaseController, AssetPurchaseController),
AssetImageController: Object.assign(AssetImageController, AssetImageController),
PlayerProfileController: Object.assign(PlayerProfileController, PlayerProfileController),
KennelController: Object.assign(KennelController, KennelController),
GameImageController: Object.assign(GameImageController, GameImageController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers