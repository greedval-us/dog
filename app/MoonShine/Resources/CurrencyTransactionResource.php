<?php

namespace App\MoonShine\Resources;

use App\Models\CurrencyTransaction;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;

/** @extends AdminResource<CurrencyTransaction> */
#[Icon('banknotes')]
final class CurrencyTransactionResource extends AdminResource
{
    protected string $model = CurrencyTransaction::class;

    protected string $titleKey = 'transactions';

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('user_id'), $this->valueField('currency'), $this->valueField('amount'), $this->valueField('balance_before'), $this->valueField('balance_after'), $this->valueField('reason'), $this->valueField('created_at')];
    }

    protected function search(): array
    {
        return ['id', 'operation_key', 'reason'];
    }
}
