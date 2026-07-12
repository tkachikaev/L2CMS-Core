@extends('admin.layouts.panel')

@section('title', 'Панель управления')
@section('description', 'Единая точка входа во все разделы CMS.')

@section('content')
<div class="admin-home-grid">
    <a class="admin-section-card available" href="{{ route('admin.themes.index') }}">
        <div>
            <span class="section-status">Доступно</span>
            <h2>Темы</h2>
            <p>Просмотр установленных тем и выбор оформления публичного сайта.</p>
        </div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>

    <article class="admin-section-card planned">
        <div>
            <span class="section-status">В разработке</span>
            <h2>Новости</h2>
            <p>Создание, редактирование и публикация новостей.</p>
        </div>
    </article>

    <article class="admin-section-card planned">
        <div>
            <span class="section-status">В разработке</span>
            <h2>Настройки</h2>
            <p>Основные параметры сайта, сервера и подключений.</p>
        </div>
    </article>

    <article class="admin-section-card planned">
        <div>
            <span class="section-status">В разработке</span>
            <h2>Модули</h2>
            <p>Управление функциональными модулями CMS.</p>
        </div>
    </article>

    <article class="admin-section-card planned">
        <div>
            <span class="section-status">В разработке</span>
            <h2>Администраторы</h2>
            <p>Учётные записи, роли и права доступа.</p>
        </div>
    </article>

    <article class="admin-section-card planned">
        <div>
            <span class="section-status">В разработке</span>
            <h2>Журнал действий</h2>
            <p>История входов и изменений в административной панели.</p>
        </div>
    </article>
</div>
@endsection
