import AuthenticateController from './AuthenticateController'
import ProfileController from './ProfileController'
import HomeController from './HomeController'
import UpdateFieldController from './UpdateFieldController'
import AsyncSearchController from './AsyncSearchController'
import NotificationController from './NotificationController'
import ComponentController from './ComponentController'
import MethodController from './MethodController'
import ReactiveController from './ReactiveController'
import HasManyController from './HasManyController'
import BelongsToManyPivotController from './BelongsToManyPivotController'
import PageController from './PageController'
import CrudController from './CrudController'
import HandlerController from './HandlerController'
const Controllers = {
    AuthenticateController: Object.assign(AuthenticateController, AuthenticateController),
ProfileController: Object.assign(ProfileController, ProfileController),
HomeController: Object.assign(HomeController, HomeController),
UpdateFieldController: Object.assign(UpdateFieldController, UpdateFieldController),
AsyncSearchController: Object.assign(AsyncSearchController, AsyncSearchController),
NotificationController: Object.assign(NotificationController, NotificationController),
ComponentController: Object.assign(ComponentController, ComponentController),
MethodController: Object.assign(MethodController, MethodController),
ReactiveController: Object.assign(ReactiveController, ReactiveController),
HasManyController: Object.assign(HasManyController, HasManyController),
BelongsToManyPivotController: Object.assign(BelongsToManyPivotController, BelongsToManyPivotController),
PageController: Object.assign(PageController, PageController),
CrudController: Object.assign(CrudController, CrudController),
HandlerController: Object.assign(HandlerController, HandlerController),
}

export default Controllers