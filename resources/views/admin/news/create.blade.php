@extends('admin.layouts.panel')

@section('title', 'Новая новость')
@section('description', 'Заполните материал, добавьте изображения и выберите статус публикации.')

@section('content')
<form method="POST" action="{{ route('admin.news.store') }}" class="content-editor" enctype="multipart/form-data">
    @include('admin.news._form')
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/news-editor.js') }}" defer></script>
@endpush
