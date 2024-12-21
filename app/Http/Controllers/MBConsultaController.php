<!-- <?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class MBConsultaController extends Controller
{
    public function validarUsuario(Request $request)
    {
        // Validar datos del request
        $validated = $request->validate([
            'IdCliente' => 'required|string|size:8',
            'Monto' => 'nullable|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'TelefonoComercio' => 'required|string|size:11',
        ]);

        // Lógica para validar al usuario
        $idCliente = $validated['IdCliente'];
        $usuarioValido = $this->verificarCliente($idCliente);

        // Respuesta al banco
        if ($usuarioValido) {
            return response()->json(['status' => true], 200);
        }

        return response()->json(['status' => false], 200);
    }

    private function verificarCliente($idCliente)
    {
        // Buscar al cliente en la base de datos usando el campo uid
        return User::where('uid', $idCliente)->exists();
    }
}
