@extends('errors::layout')

@section('code', '429')
@section('title', 'Too Many Requests')

@section('icon')
<svg class="w-24 h-24 mx-auto text-warning opacity-80 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
</svg>
@endsection

@section('message')
    You've made too many requests. Please slow down and try again later.
@endsection

