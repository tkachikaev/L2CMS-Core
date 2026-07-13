@extends('admin.layouts.panel')

@section('title', 'Запись журнала #'.$auditLog->id)
@section('description', $auditLog->actionLabel())

@section('content')
<div class="audit-detail-toolbar">
    <a class="button button-secondary" href="{{ route('admin.logs.index', array_filter(['category' => request()->query('category')])) }}">← Вернуться к журналу</a>
    <span @class([
        'status-badge',
        'status-badge-success' => $auditLog->result === 'success',
        'status-badge-danger' => $auditLog->result === 'failed',
    ])>{{ $auditLog->resultLabel() }}</span>
</div>

<div class="audit-detail-grid">
    <section class="form-card audit-detail-card">
        <h2>Событие</h2>
        <dl class="audit-definition-list">
            <div><dt>Дата и время</dt><dd>{{ $auditLog->created_at?->format('d.m.Y H:i:s') }}</dd></div>
            <div><dt>Категория</dt><dd>{{ $auditLog->categoryLabel() }} <code>{{ $auditLog->category }}</code></dd></div>
            <div><dt>Действие</dt><dd>{{ $auditLog->actionLabel() }} <code>{{ $auditLog->action }}</code></dd></div>
            <div><dt>Результат</dt><dd>{{ $auditLog->resultLabel() }}</dd></div>
        </dl>
    </section>

    <section class="form-card audit-detail-card">
        <h2>Инициатор</h2>
        <dl class="audit-definition-list">
            <div><dt>Имя</dt><dd>{{ $auditLog->actorLabel() }}</dd></div>
            <div><dt>Тип</dt><dd>{{ $auditLog->actor_type ?: '—' }}</dd></div>
            <div><dt>Идентификатор</dt><dd>{{ $auditLog->actor_id ?: '—' }}</dd></div>
            <div><dt>IP-адрес</dt><dd class="audit-monospace">{{ $auditLog->ip_address ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="form-card audit-detail-card">
        <h2>Объект действия</h2>
        <dl class="audit-definition-list">
            <div><dt>Название</dt><dd>{{ $auditLog->targetLabel() }}</dd></div>
            <div><dt>Тип</dt><dd>{{ $auditLog->target_type ?: '—' }}</dd></div>
            <div><dt>Идентификатор</dt><dd>{{ $auditLog->target_id ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="form-card audit-detail-card">
        <h2>Клиент</h2>
        <dl class="audit-definition-list">
            <div class="audit-definition-full"><dt>User-Agent</dt><dd>{{ $auditLog->user_agent ?: '—' }}</dd></div>
        </dl>
    </section>
</div>

<section class="form-card audit-details-json">
    <div class="audit-details-heading">
        <h2>Подробности</h2>
        <span>Секретные поля автоматически скрываются</span>
    </div>

    @if (empty($auditLog->details))
        <p class="audit-empty-details">Дополнительные данные для этого события не записывались.</p>
    @else
        <pre>{{ json_encode($auditLog->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif
</section>
@endsection
