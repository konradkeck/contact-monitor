<?php

namespace App\Integrations;

/**
 * Single source of truth for all integration metadata (icons, labels, colors).
 *
 * Maps every known system_type / channel_type alias to an integration class.
 * Add new integrations here + create the corresponding class.
 */
class IntegrationRegistry
{
    private static array $map = [
        // Email integrations
        'email'        => EmailIntegration::class,
        'imap'         => EmailIntegration::class,
        'gmail'        => GmailIntegration::class,

        // Ticket channel type (generic, used in timeline)
        'ticket'       => TicketIntegration::class,

        // WHMCS billing system (has own "W" brand icon + services widget)
        'whmcs'        => WhmcsIntegration::class,

        // Discord
        'discord'      => DiscordIntegration::class,
        'discord_user' => DiscordIntegration::class,

        // Slack
        'slack'        => SlackIntegration::class,
        'slack_user'   => SlackIntegration::class,

        // MetricsCube analytics
        'metricscube'  => MetricscubeIntegration::class,
    ];

    public static function get(string $type): BaseIntegration
    {
        $class = self::$map[strtolower($type)] ?? null;

        return $class ? new $class() : new GenericIntegration($type);
    }

    /** Register additional integrations (e.g. from a service provider). */
    public static function register(string $type, string $class): void
    {
        self::$map[strtolower($type)] = $class;
    }
}
