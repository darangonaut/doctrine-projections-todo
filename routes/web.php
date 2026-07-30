<?php

declare(strict_types=1);

use App\Http\Controllers\TaskController;
use App\Http\Controllers\TodoListController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TodoListController::class, 'index'])->name('lists.index');
Route::post('/zoznamy', [TodoListController::class, 'store'])->name('lists.store');
Route::get('/zoznamy/{slug}', [TodoListController::class, 'show'])->name('lists.show');
Route::patch('/zoznamy/{id}/archivovat', [TodoListController::class, 'archive'])->name('lists.archive');
Route::patch('/zoznamy/{id}/obnovit', [TodoListController::class, 'restore'])->name('lists.restore');
Route::delete('/zoznamy/{id}', [TodoListController::class, 'destroy'])->name('lists.destroy');

Route::post('/zoznamy/{listId}/ulohy', [TaskController::class, 'store'])->name('tasks.store');
Route::patch('/ulohy/{id}/{to}', [TaskController::class, 'transition'])
    ->whereIn('to', ['start', 'complete', 'reopen'])
    ->name('tasks.transition');
Route::post('/ulohy/{id}/stitok', [TaskController::class, 'tag'])->name('tasks.tag');
Route::delete('/ulohy/{id}/stitok/{tagId}', [TaskController::class, 'untag'])->name('tasks.untag');
Route::delete('/ulohy/{id}', [TaskController::class, 'destroy'])->name('tasks.destroy');
