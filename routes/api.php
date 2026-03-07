<?php

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::post('/ingest/batch', [IngestController::class, 'batch']);
