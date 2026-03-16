@extends('errors::layout')

@section('code', '500')
@section('title', 'Server Error')

@section('icon')
<svg class="w-24 h-24 mx-auto text-error opacity-80 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
</svg>
@endsection

@section('message')
    Oops! Something went wrong on our end. We're working to fix it.
@endsection

