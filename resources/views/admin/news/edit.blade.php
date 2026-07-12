@extends('admin.layouts.panel')

@section('title', 'Редактирование новости')
@section('description', 'Изменения появятся на публичном сайте после сохранения.')

@section('content')
<form method="POST" action="{{ route('admin.news.update', $newsItem) }}" class="content-editor" enctype="multipart/form-data">
    @include('admin.news._form')
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/news-editor.js') }}" defer></script>
@endpush
