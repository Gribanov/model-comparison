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
        ->set('app.ytdlp_sleep_min_seconds', env('int:YTDLP_SLEEP_MIN_SECONDS'))
        ->set('app.ytdlp_sleep_max_seconds', env('int:YTDLP_SLEEP_MAX_SECONDS'))
        ->set('app.ytdlp_http429_retries', env('int:YTDLP_HTTP429_RETRIES'))
        ->set('app.ytdlp_http429_initial_delay_seconds', env('int:YTDLP_HTTP429_INITIAL_DELAY_SECONDS'))
        ->set('app.ytdlp_http429_max_delay_seconds', env('int:YTDLP_HTTP429_MAX_DELAY_SECONDS'))
        ->set('app.temp_file_ttl', env('int:TEMP_FILE_TTL'))
        ->set('app.subtitle_temp_dir', env('APP_SUBTITLE_TEMP_DIR'))
        ->set('app.ytdlp_bin', env('YTDLP_BIN'))
        ->set('app.bot_diagnostics_check_remote_webhook', env('bool:BOT_DIAGNOSTICS_CHECK_REMOTE_WEBHOOK'));

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
        ->bind('$sleepMinSeconds', '%app.ytdlp_sleep_min_seconds%')
        ->bind('$sleepMaxSeconds', '%app.ytdlp_sleep_max_seconds%')
        ->bind('$http429Retries', '%app.ytdlp_http429_retries%')
        ->bind('$http429InitialDelaySeconds', '%app.ytdlp_http429_initial_delay_seconds%')
        ->bind('$http429MaxDelaySeconds', '%app.ytdlp_http429_max_delay_seconds%')
        ->bind('$tempFileTtl', '%app.temp_file_ttl%')
        ->bind('$checkRemoteWebhook', '%app.bot_diagnostics_check_remote_webhook%');

    $services->set('app.monolog.formatter.stderr', \Monolog\Formatter\LineFormatter::class)
        ->args(["[%%datetime%%] %%level_name%%: %%message%%\n", null, true, true]);

    $services->set('app.monolog.formatter.file', \Monolog\Formatter\LineFormatter::class)
        ->args(["[%%datetime%%] %%channel%%.%%level_name%%: %%message%% %%context%% %%extra%%\n", null, true, true]);

    // Ensure controllers are registered as services and can be fetched by the controller resolver.
    $services->load('App\\Controller\\', '../src/Controller/')
        ->tag('controller.service_arguments');

    $services->load('App\\', '../src/')
        ->exclude('../src/{Controller,DependencyInjection,Entity,Tests,Kernel.php}');
};
