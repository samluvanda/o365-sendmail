<?php

namespace O365Sendmail\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class PutEnv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'o365-sendmail:env';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sets the o365-sendmail environment variables';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $env = file_get_contents(base_path('.env'));

        if (Str::contains($env, 'O365SENDMAIL_TENANT') || Str::contains($env, 'O365SENDMAIL_CLIENT_ID') || Str::contains($env, 'O365SENDMAIL_CLIENT_SECRET')) {
            $this->error('Env file already contains o365-sendmail environment variables.');

            return;
        }

        file_put_contents(base_path('.env'), [
            $env,
            PHP_EOL,
            file_get_contents(__DIR__ . '/../../config/.env.o365-sendmail')
        ]);
        Artisan::call('config:clear');
        $this->info('O365-sendmail environment variables set successfully.');
    }
}
