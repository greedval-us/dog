import LocaleController from './LocaleController'
import DashboardController from './DashboardController'
import PlayerProfileController from './PlayerProfileController'
import KennelController from './KennelController'
import GameImageController from './GameImageController'
import Settings from './Settings'
const Controllers = {
    LocaleController: Object.assign(LocaleController, LocaleController),
DashboardController: Object.assign(DashboardController, DashboardController),
PlayerProfileController: Object.assign(PlayerProfileController, PlayerProfileController),
KennelController: Object.assign(KennelController, KennelController),
GameImageController: Object.assign(GameImageController, GameImageController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers