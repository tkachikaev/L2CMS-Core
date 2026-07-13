@extends('admin.layouts.panel')

@section('title', 'Панель управления')
@section('description', 'Единая точка входа во все разделы CMS.')

@section('content')
<div class="admin-home-grid">
    <a class="admin-section-card available" href="{{ route('admin.news.index') }}">
        <div>
            <span class="section-status">Доступно</span>
            <h2>Новости</h2>
            <p>Создание, оформление изображениями и публикация новостей сайта.</p>
        </div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>

    <a class="admin-section-card available" href="{{ route('admin.themes.index') }}">
        <div>
            <span class="section-status">Доступно</span>
            <h2>Темы</h2>
            <p>Просмотр установленных тем и выбор оформления публичного сайта.</p>
        </div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>

    <a class="admin-section-card available" href="{{ route('admin.settings.general') }}">
        <div>
            <span class="section-status">Доступно</span>
            <h2>Настройки</h2>
            <p>Название сайта, описание, логотип, favicon, часовой пояс и контакты.</p>
        </div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>

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

    <a class="admin-section-card available" href="{{ route('admin.logs.index') }}">
        <div>
            <span class="section-status">Доступно</span>
            <h2>Журнал действий</h2>
            <p>Входы, изменения контента и настроек, события пользователей, почты и системы.</p>
        </div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
</div>
@endsection
