<x-moonshine::layout.div class="admin-dashboard">
    <section class="admin-intro" aria-labelledby="admin-workspace-title">
        <div>
            <span class="admin-role">{{ $role->label() }}</span>
            <h2 id="admin-workspace-title">{{ __('admin.workspace') }}</h2>
            <p>{{ __('admin.intro_'.$role->value) }}</p>
        </div>
        <form method="GET" action="{{ route('moonshine.index') }}" class="admin-period">
            <label for="admin-period">{{ __('admin.period') }}
                <select id="admin-period" name="days" class="form-select">
                    @foreach ([7, 30, 90] as $option)
                        <option value="{{ $option }}" @selected($days === $option)>{{ __('admin.days', ['days' => $option]) }}</option>
                    @endforeach
                </select>
            </label>
            <x-moonshine::form.button type="submit" class="btn-primary">{{ __('admin.apply') }}</x-moonshine::form.button>
        </form>
    </section>

    <section aria-label="{{ __('admin.totals') }}">
        <dl class="admin-kpis">
            @foreach ($metrics['totals'] as $key => $value)
                <div class="admin-kpi"><dt>{{ __('admin.metrics.'.$key) }}</dt><dd>{{ number_format($value, 0, '.', ' ') }}</dd></div>
            @endforeach
        </dl>
    </section>

    <section class="admin-panel" aria-labelledby="admin-links-title">
        <h3 id="admin-links-title">{{ __('admin.quick_links') }}</h3>
        <nav class="admin-links" aria-label="{{ __('admin.quick_links') }}">
            @foreach ($links as $title => $url)
                <a href="{{ $url }}">{{ $title }} <span aria-hidden="true">↗</span></a>
            @endforeach
        </nav>
    </section>

    <section aria-label="{{ __('admin.in_period') }}">
        <h3 class="admin-section-title">{{ __('admin.in_period') }}</h3>
        <dl class="admin-kpis">
            @foreach ($metrics['period'] as $key => $value)
                <div class="admin-kpi"><dt>{{ __('admin.metrics.'.$key) }}</dt><dd>{{ number_format($value, 0, '.', ' ') }}</dd></div>
            @endforeach
        </dl>
        <p class="admin-muted">{{ __('admin.time_note', ['timezone' => config('app.timezone')]) }}</p>
        @if ($role !== \App\MoonShine\Enums\StaffRole::Moderator)
            <p class="admin-muted">{{ __('admin.activity_note') }}</p>
        @endif
    </section>

    @if ($role !== \App\MoonShine\Enums\StaffRole::Moderator)
        <section class="admin-panel">
            <h3>{{ __('admin.waiting_now') }}</h3>
            <dl class="admin-system">
                @foreach ($metrics['waiting'] as $key => $value)
                    <div><dt>{{ __('admin.metrics.'.$key) }}</dt><dd>{{ number_format($value, 0, '.', ' ') }}</dd></div>
                @endforeach
            </dl>
            <p class="admin-muted">{{ __('admin.waiting_note') }}</p>
        </section>
        <div class="admin-grid">
            <section class="admin-panel">
                <h3>{{ __('admin.registrations') }}</h3>
                <div class="admin-chart" role="img" aria-label="{{ __('admin.registrations') }}: {{ $metrics['period']['registrations'] }}">
                    @foreach ($metrics['registrations'] as $date => $count)
                        <div class="admin-bar" style="--bar-height: {{ $count === 0 ? 1 : max(3, (int) round($count / max(1, max($metrics['registrations'])) * 100)) }}%" title="{{ $date }}: {{ $count }}"></div>
                    @endforeach
                </div>
                <div class="admin-chart-axis"><span>{{ array_key_first($metrics['registrations']) }}</span><span>{{ array_key_last($metrics['registrations']) }}</span></div>
                <details>
                    <summary>{{ __('admin.chart_table') }}</summary>
                    <div class="admin-table-scroll">
                        <table><caption>{{ __('admin.registrations') }}</caption>
                            <thead><tr><th scope="col">{{ __('admin.date') }}</th><th scope="col">{{ __('admin.count') }}</th></tr></thead>
                            <tbody>@foreach ($metrics['registrations'] as $date => $count)<tr><td>{{ $date }}</td><td>{{ $count }}</td></tr>@endforeach</tbody>
                        </table>
                    </div>
                </details>
            </section>
            <section class="admin-panel">
                <h3>{{ __('admin.economy') }}</h3>
                <div class="admin-economy">
                    <div><span></span><strong>{{ __('admin.issued') }}</strong><strong>{{ __('admin.spent') }}</strong></div>
                    @foreach ($metrics['economy'] as $currency => $values)
                        <div><strong>{{ __('admin.fields.'.$currency) }}</strong><span>+{{ number_format($values['issued'], 0, '.', ' ') }}</span><span>−{{ number_format($values['spent'], 0, '.', ' ') }}</span></div>
                    @endforeach
                </div>
                <p class="admin-muted">{{ __('admin.economy_note') }}</p>
            </section>
            <section class="admin-panel">
                <h3>{{ __('admin.activities') }}</h3>
                <dl class="admin-system">
                    @forelse ($metrics['activities'] as $group => $count)
                        <div><dt>{{ $group }}</dt><dd>{{ $count }}</dd></div>
                    @empty
                        <p class="admin-muted">{{ __('admin.empty') }}</p>
                    @endforelse
                </dl>
            </section>
        </div>
    @endif

    @if ($role !== \App\MoonShine\Enums\StaffRole::Analyst)
        <section class="admin-panel">
            <h3>{{ __('admin.recent') }}</h3>
            <ul class="admin-log">
                @forelse ($metrics['recent'] as $entry)
                    <li><strong>{{ $entry->actor_name }}</strong> · {{ __('admin.actions.'.$entry->action) }} · {{ class_basename($entry->target_type) }} #{{ $entry->target_id }}
                        <small>{{ $entry->created_at?->format('d.m.Y H:i') }} @if ($entry->reason) · {{ $entry->reason }} @endif</small>
                    </li>
                @empty
                    <li class="admin-muted">{{ __('admin.empty') }}</li>
                @endforelse
            </ul>
        </section>
    @endif

    @if ($role === \App\MoonShine\Enums\StaffRole::Administrator)
        <section class="admin-panel">
            <h3>{{ __('admin.system') }}</h3>
            <dl class="admin-system">
                @foreach ($metrics['system'] as $key => $value)
                    <div><dt>{{ __('admin.system_fields.'.$key) }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif
</x-moonshine::layout.div>
