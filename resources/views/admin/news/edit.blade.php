@extends('admin.layouts.panel')

@section('title', 'Редактирование новости')
@section('description', 'Изменения появятся на публичном сайте после сохранения.')

@section('content')
<form method="POST" action="{{ route('admin.news.update', $newsItem) }}" class="content-editor">
    @include('admin.news._form')
</form>
@endsection
