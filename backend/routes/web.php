<?php

use App\Livewire\DidacticProgrammingList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/programaciones', DidacticProgrammingList::class)->name('programaciones.index');
