@extends('errors::layout')

@section('code', '403')
@section('title', 'Access Forbidden')

@section('icon')
<svg class="w-24 h-24 mx-auto text-error opacity-80 animate-shake" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
</svg>
@endsection

@section('message')
    You don't have permission to access this resource.
@endsection

