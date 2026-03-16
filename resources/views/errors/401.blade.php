@extends('errors::layout')

@section('code', '401')
@section('title', 'Unauthorized')

@section('icon')
<svg class="w-24 h-24 mx-auto text-error opacity-80 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
</svg>
@endsection

@section('message')
    You need to authenticate to access this resource.
@endsection

