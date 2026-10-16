<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CreasyActivationController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user('admin')?->isSuperAdmin(), 403);
        if ($request->header('X-Inertia')) return \Inertia\Inertia::location(url('/admin/creasy-license'));
        require_once app_path('Support/Creasy/CreasyWizard.php');
        $wizard = new \Creasy\CreasyWizard('respondesk', '1.0.0', (string) config('app.url'), dirname((string) config('creasy.config_file')), app_path('Support/Creasy/public-key.pem'), (string) config('creasy.config_file'));
        $error = '';$domain = '';$licensed = false;
        try {
            $domain = $wizard->domain();
            if ($request->isMethod('post')) {
                // No request validation exception: it could flash the installation credential to a session.
                $wizard->activate(is_string($request->input('credential')) ? $request->input('credential') : '', $request->input('accepted') === '1');
            }
            $licensed = $wizard->status()['licensed'];
        } catch (\Throwable $e) { $error = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível ativar. Confira a configuração do servidor e tente novamente.'; }
        $productName = 'CREASY CRM';$backUrl = url('/admin');$actionUrl = url('/admin/creasy-license');$csrfHtml = csrf_field()->toHtml();
        ob_start();require app_path('Support/Creasy/wizard-view.php');$html = ob_get_clean();
        return response($html)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
