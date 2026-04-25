<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (ContainerConfigurator $container): void {
    $telegramWebhookSecret = (string) ($_SERVER['TELEGRAM_WEBHOOK_SECRET'] ?? '');

    $container->parameters()
        ->set('app.telegram_bot_token', env('TELEGRAM_BOT_TOKEN'))
        ->set('app.telegram_webhook_base_url', env('TELEGRAM_WEBHOOK_BASE_URL'))
        ->set('app.telegram_webhook_secret', env('TELEGRAM_WEBHOOK_SECRET'))
        ->set('app.telegram_webhook_secret_path_segment', rawurlencode($telegramWebhookSecret))
        ->set('app.redis_dsn', env('MESSENGER_TRANSPORT_DSN'))
        ->set('app.user_job_lock_ttl', env('int:USER_JOB_LOCK_TTL'))
        ->set('app.ytdlp_timeout', env('int:YTDLP_TIMEOUT'))
        ->set('app.temp_file_ttl', env('int:TEMP_FILE_TTL'))
        ->set('app.subtitle_temp_dir', env('APP_SUBTITLE_TEMP_DIR'))
        ->set('app.ytdlp_bin', env('YTDLP_BIN'));

    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$telegramBotToken', '%app.telegram_bot_token%')
        ->bind('$telegramWebhookBaseUrl', '%app.telegram_webhook_base_url%')
        ->bind('$telegramWebhookSecret', '%app.telegram_webhook_secret%')
        ->bind('$telegramWebhookSecretPathSegment', '%app.telegram_webhook_secret_path_segment%')
        ->bind('$redisDsn', '%app.redis_dsn%')
        ->bind('$userJobLockTtl', '%app.user_job_lock_ttl%')
        ->bind('$subtitleTempDir', '%app.subtitle_temp_dir%')
        ->bind('$ytdlpBin', '%app.ytdlp_bin%')
        ->bind('$processTimeoutSeconds', '%app.ytdlp_timeout%')
        ->bind('$tempFileTtl', '%app.temp_file_ttl%');

    $services->load('App\\', '../src/')
        ->exclude('../src/{DependencyInjection,Entity,Tests,Kernel.php}');
};
