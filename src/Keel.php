<?php

declare(strict_types=1);

namespace Noria\Keel;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Noria\Keel\Audit\AuditLog;
use Noria\Keel\Auth\OtpChallenge;
use Noria\Keel\Invitations\Invitation;

final class Keel
{
    /** @var class-string<AuditLog> */
    private static string $auditLogModel = AuditLog::class;

    /** @var class-string<OtpChallenge> */
    private static string $otpChallengeModel = OtpChallenge::class;

    /** @var class-string<Invitation> */
    private static string $invitationModel = Invitation::class;

    public static function useAuditLogModel(string $model): void
    {
        if (! is_a($model, AuditLog::class, allow_string: true)) {
            throw new InvalidArgumentException($model.' must extend '.AuditLog::class.'.');
        }

        self::$auditLogModel = $model;
    }

    public static function useOtpChallengeModel(string $model): void
    {
        if (! is_a($model, OtpChallenge::class, allow_string: true)) {
            throw new InvalidArgumentException($model.' must extend '.OtpChallenge::class.'.');
        }

        self::$otpChallengeModel = $model;
    }

    /** @return class-string<AuditLog> */
    public static function auditLogModel(): string
    {
        return self::$auditLogModel;
    }

    public static function useInvitationModel(string $model): void
    {
        if (! is_a($model, Invitation::class, allow_string: true)) {
            throw new InvalidArgumentException($model.' must extend '.Invitation::class.'.');
        }

        self::$invitationModel = $model;
    }

    /** @return class-string<OtpChallenge> */
    public static function otpChallengeModel(): string
    {
        return self::$otpChallengeModel;
    }

    /** @return class-string<Invitation> */
    public static function invitationModel(): string
    {
        return self::$invitationModel;
    }

    public static function table(string $name): string
    {
        $configured = Config::get('keel.tables.'.$name);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return Config::string('keel.table_prefix', '').$name;
    }

    public static function connection(): ?string
    {
        $connection = Config::get('keel.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public static function forgetModels(): void
    {
        self::$auditLogModel = AuditLog::class;
        self::$otpChallengeModel = OtpChallenge::class;
        self::$invitationModel = Invitation::class;
    }
}
