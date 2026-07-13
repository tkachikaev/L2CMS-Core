@extends('admin.layouts.panel')

@section('title', 'Журнал действий')
@section('description', 'События администраторов, пользователей, почтовой системы и ядра CMS.')

@section('content')
<div class="audit-summary">
    <div>
        <span>Всего записей</span>
        <strong>{{ $totalCount }}</strong>
    </div>
    <p>Записи хранятся {{ $retentionDays }} дней. Пароли, токены и другие секреты в журнал не записываются.</p>
</div>

<nav class="audit-tabs" aria-label="Категории журнала">
    <a @class(['active' => $activeCategory === null]) href="{{ route('admin.logs.index') }}">
        Все <span>{{ $totalCount }}</span>
    </a>
    @foreach ($categories as $category)
        @php
            $categoryLabel = match ($category) {
                'admin' => 'Администраторы',
                'user' => 'Пользователи',
                'mail' => 'Почта',
                'system' => 'Система',
                default => str($category)->replace(['_', '-', '.'], ' ')->headline()->toString(),
            };
        @endphp
        <a @class(['active' => $activeCategory === $category]) href="{{ route('admin.logs.index', ['category' => $category]) }}">
            {{ $categoryLabel }} <span>{{ (int) ($counts[$category] ?? 0) }}</span>
        </a>
    @endforeach
</nav>

@if ($logs->isEmpty())
    <div class="empty-state">
        <div class="empty-state-mark" aria-hidden="true">J</div>
        <h2>Записей пока нет</h2>
        <p>Новые действия появятся здесь после входа, изменения контента или системных операций.</p>
    </div>
@else
    <div class="audit-table-wrap">
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Дата и время</th>
                    <th>Инициатор</th>
                    <th>Действие</th>
                    <th>Объект</th>
                    <th>Результат</th>
                    <th>IP-адрес</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="audit-date">
                            <strong>{{ $log->created_at?->format('d.m.Y') }}</strong>
                            <span>{{ $log->created_at?->format('H:i:s') }}</span>
                        </td>
                        <td>
                            <strong>{{ $log->actorLabel() }}</strong>
                            <span class="audit-muted">{{ $log->actorTypeLabel() }}</span>
                        </td>
                        <td>
                            <strong>{{ $log->actionLabel() }}</strong>
                            <code>{{ $log->action }}</code>
                        </td>
                        <td>{{ $log->targetLabel() }}</td>
                        <td>
                            <span @class([
                                'status-badge',
                                'status-badge-success' => $log->result === 'success',
                                'status-badge-danger' => $log->result === 'failed',
                            ])>{{ $log->resultLabel() }}</span>
                        </td>
                        <td class="audit-monospace">{{ $log->ip_address ?: '—' }}</td>
                        <td class="audit-details-link">
                            <a class="button button-secondary" href="{{ route('admin.logs.show', array_filter(['auditLog' => $log, 'category' => $activeCategory])) }}">Подробнее</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        @php
            $firstPage = max(1, $logs->currentPage() - 2);
            $lastPage = min($logs->lastPage(), $logs->currentPage() + 2);
        @endphp

        <nav class="simple-pagination" aria-label="Навигация по страницам журнала">
            @if ($logs->onFirstPage())
                <span class="button button-secondary disabled">← Назад</span>
            @else
                <a class="button button-secondary" href="{{ $logs->previousPageUrl() }}" rel="prev">← Назад</a>
            @endif

            <div class="pagination-pages" aria-label="Страницы">
                @foreach ($logs->getUrlRange($firstPage, $lastPage) as $page => $url)
                    @if ($page === $logs->currentPage())
                        <span class="pagination-page active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pagination-page" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            </div>

            @if ($logs->hasMorePages())
                <a class="button button-secondary" href="{{ $logs->nextPageUrl() }}" rel="next">Вперёд →</a>
            @else
                <span class="button button-secondary disabled">Вперёд →</span>
            @endif
        </nav>
    @endif
@endif
@endsection
