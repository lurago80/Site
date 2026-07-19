<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "Esqueci minha senha" - usa o broker de senha nativo do Laravel
 * (Illuminate\Support\Facades\Password), que já sabe gerar/validar o
 * token contra `password_reset_tokens` e localizar o usuário via
 * App\Models\User::class (config/auth.php). Funciona sob RLS pelo
 * mesmo motivo do login: App\Http\Middleware\BootstrapAuthDatabaseContext
 * abre o bypass necessário para toda a rota 'web' antes do tenant
 * estar definido.
 */
class PasswordResetController extends Controller
{
    public function mostrarFormularioEsqueci()
    {
        return view('auth.esqueci-senha');
    }

    public function enviarLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // Mesma mensagem para e-mail existente ou não - não confirma se
        // um e-mail está cadastrado no sistema (evita enumeração de
        // usuários por quem não deveria saber quem usa a plataforma).
        return back()->with('status', 'Se o e-mail informado estiver cadastrado, enviamos um link de redefinição.');
    }

    public function mostrarFormularioRedefinir(Request $request, string $token)
    {
        return view('auth.redefinir-senha', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function redefinir(Request $request)
    {
        $dados = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($dados, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        return redirect('/login')->with('status', 'Senha redefinida com sucesso. Faça login com a nova senha.');
    }
}
