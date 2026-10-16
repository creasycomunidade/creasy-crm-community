<?php
namespace App\Console\Commands;

use App\Services\Install\InstallerService;
use Illuminate\Console\Command;

class SaasInstallCommand extends Command
{
    protected $signature = 'saas:install';
    protected $description = 'Instalação inicial do CREASY CRM com administrador e white label (banco configurado no .env).';

    public function handle(InstallerService $installer): int
    {
        if ($installer->isInstalled()) {
            $this->error('Aplicação já instalada. Este comando não reinstala nem apaga tabelas.');
            return self::FAILURE;
        }
        $this->info('CREASY CRM — instalação inicial. Para logo e favicon, prefira o assistente /install.');
        if (!$installer->requirements(config('database.default'))['ok']) {
            $this->error('Requisitos não atendidos. Execute php installer/prepare.php e confira /install.');
            return self::FAILURE;
        }
        $data = ['app_name' => $this->ask('Nome da plataforma', 'CREASY CRM'),
            'app_tagline' => $this->ask('Slogan (opcional)', ''),
            'support_email' => $this->ask('E-mail de suporte'),
            'primary_color' => $this->ask('Cor principal (hexadecimal)', '#7c3aed')];
        $name = $this->ask('Nome do administrador');
        $email = $this->ask('E-mail do administrador');
        $password = $this->secret('Senha do administrador (mínimo 12 caracteres)');
        $confirmation = $this->secret('Confirme a senha');
        $validation = validator(array_merge($data, ['name'=>$name, 'email'=>$email, 'password'=>$password, 'password_confirmation'=>$confirmation]), [
            'app_name'=>'required|string|max:128', 'support_email'=>'required|email', 'primary_color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
            'name'=>'required|string', 'email'=>'required|email', 'password'=>'required|min:12|confirmed',
        ]);
        if ($validation->fails()) { foreach ($validation->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        try {
            $installer->beginInstallation();
            $installer->assertFreshDatabase();
            $installer->ensureAppKey();
            $installer->runMigrations();
            $installer->seedCore();
            $installer->applyBranding($data);
            $installer->writeEnv(['APP_NAME'=>$data['app_name'], 'APP_DEBUG'=>false]);
            $installer->createSuperAdmin($name, $email, $password);
            $installer->markInstalled();
            $installer->clearCaches();
        } catch (\Throwable $e) { report($e); $this->error($e->getMessage()); return self::FAILURE; }
        $this->info('Instalação concluída: '.config('app.url').'/admin');
        return self::SUCCESS;
    }
}
