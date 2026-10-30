<?php

use Illuminate\Support\Facades\Route;
use Ultrasimplified\Gallery\Http\Controllers\UploadController;

Route::post('/upload', UploadController::class)->name('nova.gallery.upload');
