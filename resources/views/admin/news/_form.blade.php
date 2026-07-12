@csrf
@if ($newsItem->exists)
    @method('PUT')
@endif

@php
    $editorBody = app(\App\Services\News\NewsHtmlSanitizer::class)
        ->sanitize((string) old('body', $newsItem->body));
@endphp

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
                <label for="body-editor">Текст новости</label>

                <div
                    class="rich-editor"
                    data-rich-editor
                    data-upload-url="{{ route('admin.news.images.store') }}"
                >
                    <div class="rich-editor-toolbar" role="toolbar" aria-label="Форматирование текста">
                        <select class="editor-select" data-editor-block aria-label="Стиль абзаца" title="Стиль абзаца">
                            <option value="p">Обычный текст</option>
                            <option value="h2">Заголовок 2</option>
                            <option value="h3">Заголовок 3</option>
                            <option value="h4">Заголовок 4</option>
                            <option value="blockquote">Цитата</option>
                            <option value="pre">Код</option>
                        </select>

                        <span class="editor-toolbar-group">
                            <button type="button" data-editor-command="bold" title="Жирный" aria-label="Жирный"><strong>Ж</strong></button>
                            <button type="button" data-editor-command="italic" title="Курсив" aria-label="Курсив"><em>К</em></button>
                            <button type="button" data-editor-command="underline" title="Подчёркнутый" aria-label="Подчёркнутый"><u>Ч</u></button>
                            <button type="button" data-editor-command="strikeThrough" title="Зачёркнутый" aria-label="Зачёркнутый"><s>А</s></button>
                        </span>

                        <span class="editor-toolbar-group">
                            <button type="button" data-editor-command="insertUnorderedList" title="Маркированный список" aria-label="Маркированный список">• Список</button>
                            <button type="button" data-editor-command="insertOrderedList" title="Нумерованный список" aria-label="Нумерованный список">1. Список</button>
                        </span>

                        <span class="editor-toolbar-group">
                            <button type="button" data-editor-align="left" title="По левому краю" aria-label="По левому краю">⇤</button>
                            <button type="button" data-editor-align="center" title="По центру" aria-label="По центру">↔</button>
                            <button type="button" data-editor-align="right" title="По правому краю" aria-label="По правому краю">⇥</button>
                        </span>

                        <select class="editor-select editor-color-select" data-editor-color aria-label="Цвет текста" title="Цвет текста">
                            <option value="default">Цвет текста</option>
                            <option value="gold">Золотой</option>
                            <option value="red">Красный</option>
                            <option value="green">Зелёный</option>
                            <option value="blue">Синий</option>
                            <option value="muted">Серый</option>
                        </select>

                        <span class="editor-toolbar-group">
                            <button type="button" data-editor-link title="Добавить ссылку">Ссылка</button>
                            <button type="button" data-editor-command="unlink" title="Удалить ссылку">Без ссылки</button>
                            <button type="button" data-editor-command="insertHorizontalRule" title="Разделитель">Линия</button>
                            <button type="button" data-editor-image title="Вставить изображение">Изображение</button>
                            <button type="button" data-editor-command="removeFormat" title="Очистить форматирование">Очистить</button>
                        </span>
                    </div>

                    <div
                        id="body-editor"
                        class="rich-editor-canvas"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                        data-placeholder="Начните писать новость..."
                    >{!! $editorBody !!}</div>

                    <textarea id="body" name="body" class="rich-editor-source" maxlength="200000" hidden>{{ old('body', $newsItem->body) }}</textarea>
                    <input type="file" data-editor-image-input accept="image/jpeg,image/png,image/webp" hidden>

                    <div class="rich-editor-footer">
                        <span>Разрешены безопасные заголовки, списки, ссылки, цвета и изображения.</span>
                        <span data-editor-status aria-live="polite"></span>
                    </div>
                </div>
                <small>HTML очищается на сервере. Скрипты, стили, iframe и опасные атрибуты удаляются.</small>
            </div>
        </section>
    </div>

    <aside class="editor-sidebar">
        <section class="form-card">
            <h2>Картинка-превью</h2>

            <div class="cover-upload" data-cover-upload>
                <div class="cover-preview {{ $newsItem->coverUrl() ? 'has-image' : '' }}" data-cover-preview>
                    @if ($newsItem->coverUrl())
                        <img src="{{ $newsItem->coverUrl() }}" alt="Текущая картинка-превью">
                    @else
                        <span>Изображение не выбрано</span>
                    @endif
                </div>

                <label class="button button-secondary cover-select" for="cover_image">Выбрать изображение</label>
                <input
                    id="cover_image"
                    name="cover_image"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    data-cover-input
                >

                <input type="hidden" name="remove_cover_image" value="0">
                @if ($newsItem->coverUrl())
                    <label class="remove-cover-row">
                        <input type="checkbox" name="remove_cover_image" value="1" data-cover-remove>
                        <span>Удалить текущую картинку</span>
                    </label>
                @endif

                <small>JPG, PNG или WebP, до 5 МБ. Рекомендуемое соотношение — 16:9.</small>
            </div>
        </section>

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
