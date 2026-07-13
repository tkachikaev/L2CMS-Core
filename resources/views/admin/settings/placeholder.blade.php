@extends('admin.layouts.panel')
@section('title', __('Settings'))
@section('description', $description)
@section('content')
@include('admin.settings._tabs')
<div class="placeholder-box">
    <span>{{ __('Section prepared') }}</span>
    <h2>{{ $title }}</h2>
    <p>{{ __('The interface is reserved for a future CMS release. No settings are stored on this page yet.') }}</p>
</div>
@endsection
