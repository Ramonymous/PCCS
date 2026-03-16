@extends('errors::layout')

@section('code', '419')
@section('title', 'Session Expired')

@section('icon')
<svg class="w-24 h-24 mx-auto text-warning opacity-80 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
</svg>
@endsection

@section('message')
    Your session has expired. Please refresh the page and try again.
@endsection

