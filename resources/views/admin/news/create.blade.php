@extends('admin.layouts.panel')

@section('title', 'Новая новость')
@section('description', 'Заполните материал и выберите, публиковать его сразу или сохранить как черновик.')

@section('content')
<form method="POST" action="{{ route('admin.news.store') }}" class="content-editor">
    @include('admin.news._form')
</form>
@endsection
