<?php

namespace App\MoonShine\Traits;

use App\Models\AdminAuditLog;
use App\MoonShine\Support\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Support\Enums\Ability;

trait AuditsAdminChanges
{
    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        return DB::transaction(function () use ($item, $fields): DataWrapperContract {
            if ($item->getOriginal() instanceof MoonshineUser) {
                MoonshineUser::query()->where('moonshine_user_role_id', 1)->orderBy('id')->lockForUpdate()->get();
            }

            $actor = MoonshineUser::query()->findOrFail(MoonShineAuth::getGuard()->id());
            $model = $item->getOriginal();
            abort_unless(StaffAccess::allows($model::class, $model->exists ? Ability::UPDATE : Ability::CREATE, $actor), 403);

            if ($model->exists) {
                $model = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
                $item = $this->getCaster()->cast($model);
            }

            if ($model instanceof MoonshineUser && (int) $model->getKey() === (int) $actor->getKey()
                && (int) request('moonshine_user_role_id') !== $model->moonshine_user_role_id) {
                throw ValidationException::withMessages(['moonshine_user_role_id' => __('admin.self_role')]);
            }

            $before = $model->getAttributes();
            $created = ! $model->exists;
            $result = parent::save($item, $fields);
            $changes = [];

            foreach ($result->getOriginal()->getAttributes() as $key => $value) {
                if (in_array($key, ['id', 'created_at', 'updated_at', 'remember_token'], true) || ($before[$key] ?? null) === $value) {
                    continue;
                }

                $changes[$key] = $key === 'password'
                    ? ['before' => '[redacted]', 'after' => '[redacted]']
                    : ['before' => $before[$key] ?? null, 'after' => $value];
            }

            if ($changes !== []) {
                AdminAuditLog::query()->create([
                    'actor_id' => $actor->getKey(), 'actor_name' => $actor->name,
                    'target_type' => $model::class, 'target_id' => $result->getKey(),
                    'action' => $created ? 'created' : 'updated',
                    'reason' => request('moderation_reason'), 'changes' => $changes,
                ]);
            }

            return $result;
        }, 3);
    }
}
