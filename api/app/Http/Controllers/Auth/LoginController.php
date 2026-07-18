<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login único da plataforma (Escopo v2, seção 2.2): um só endereço de
 * login para todos os usuários operacionais de todas as empresas - o
 * e-mail cadastrado já identifica a empresa e o nível de acesso.
 */
class LoginController extends Controller
{
    public function mostrarFormulario()
    {
        if (Auth::check()) {
            return $this->redirecionarLogado();
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credenciais)) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->ativo) {
            Auth::logout();

            return back()->withErrors(['email' => 'Usuário inativo. Fale com o administrador da sua empresa.']);
        }

        if (! $user->isSuperAdmin() && $user->empresa?->status !== 'ativa') {
            Auth::logout();

            return back()->withErrors(['email' => 'Empresa inativa ou suspensa.']);
        }

        $request->session()->regenerate();

        return $this->redirecionarLogado();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function redirecionarLogado()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return redirect('/superadmin/painel');
        }

        return redirect("/fiscal/{$user->empresa->slug}/painel");
    }
}
