@csrf
@if ($newsItem->exists)
    @method('PUT')
@endif

<div class="editor-grid">
    <div class="editor-main">
        <section class="form-card">
            <div class="form-group">
                <label for="title">Заголовок</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    value="{{ old('title', $newsItem->title) }}"
                    maxlength="255"
                    required
                    autofocus
                >
                <small>Название новости в списке и на странице публикации.</small>
            </div>

            <div class="form-group">
                <label for="excerpt">Краткое описание</label>
                <textarea id="excerpt" name="excerpt" rows="4" maxlength="1000">{{ old('excerpt', $newsItem->excerpt) }}</textarea>
                <small>Показывается в общем списке. Если оставить пустым, описание сформируется из текста автоматически.</small>
            </div>

            <div class="form-group">
                <label for="body">Текст новости</label>
                <textarea id="body" name="body" rows="20" maxlength="200000" required>{{ old('body', $newsItem->bodyAsPlainText()) }}</textarea>
                <small>Используется обычный текст. Переносы строк сохраняются, HTML-код не выполняется.</small>
            </div>
        </section>
    </div>

    <aside class="editor-sidebar">
        <section class="form-card">
            <h2>Публикация</h2>

            <input type="hidden" name="is_published" value="0">
            <label class="switch-row" for="is_published">
                <input
                    id="is_published"
                    name="is_published"
                    type="checkbox"
                    value="1"
                    @checked((bool) old('is_published', $newsItem->is_published))
                >
                <span>
                    <strong>Опубликовать новость</strong>
                    <small>Без отметки новость сохранится как черновик.</small>
                </span>
            </label>

            <div class="form-group compact">
                <label for="published_at">Дата и время</label>
                <input
                    id="published_at"
                    name="published_at"
                    type="datetime-local"
                    value="{{ old('published_at', $newsItem->published_at?->format('Y-m-d\TH:i')) }}"
                >
                <small>Будущая дата создаёт запланированную публикацию.</small>
            </div>
        </section>

        @if ($newsItem->exists)
            <section class="form-card form-card-muted">
                <h2>Адрес новости</h2>
                <code>/news/{{ $newsItem->slug }}</code>
                <p>Адрес создаётся автоматически и не меняется при редактировании заголовка.</p>
            </section>
        @endif
    </aside>
</div>

<div class="editor-actions">
    <a class="button button-secondary" href="{{ route('admin.news.index') }}">Отмена</a>
    <button class="button button-primary" type="submit">{{ $newsItem->exists ? 'Сохранить изменения' : 'Создать новость' }}</button>
</div>
