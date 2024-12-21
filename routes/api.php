<?php

use App\Http\Controllers\MBConsultaController;

Route::post('/MBConsulta', [MBConsultaController::class, 'validarUsuario']);
