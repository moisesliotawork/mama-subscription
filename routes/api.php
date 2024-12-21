<?php

use App\Http\Controllers\MBConsultaController;

Route::post('/MBconsulta', [MBConsultaController::class, 'validarUsuario'])
    ->middleware(['verify.token', 'verify.ip']);
