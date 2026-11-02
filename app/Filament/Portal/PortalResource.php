<?php

namespace App\Filament\Portal;

use App\Support\PortalAccess;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Base for client-portal resources. Staff policies don't apply to clients, so
 * authorisation here is: (1) the action must be in $allowedActions, and (2) the
 * record must be inside portalQuery() (which every list and route lookup uses,
 * so other clients' records 404 rather than 403).
 */
abstract class PortalResource extends Resource
{
    /** @var list<string> */
    protected static array $allowedActions = ['viewAny', 'view'];

    /** Records this portal user may see. */
    abstract public static function portalQuery(): Builder;

    public static function getEloquentQuery(): Builder
    {
        return static::portalQuery();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::portalQuery();
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        if (PortalAccess::clientIds(auth()->user()) === [] || ! in_array($action, static::$allowedActions, true)) {
            return Response::deny();
        }

        if ($record !== null && ! static::portalQuery()->whereKey($record->getKey())->exists()) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    public static function isGloballySearchable(): bool
    {
        return false;
    }
}
