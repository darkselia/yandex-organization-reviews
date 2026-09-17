<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\ConnectOrganization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ParseRunResource;
use App\Services\YandexMaps\YandexUrlException;
use App\Services\YandexMaps\YandexUrlResolver;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function store(
        StoreOrganizationRequest $request,
        YandexUrlResolver $urlResolver,
        ConnectOrganization $connectOrganization,
    ): JsonResponse {
        $sourceUrl = $request->validated('url');

        try {
            $normalizedUrl = $urlResolver->resolve($sourceUrl);
        } catch (YandexUrlException $exception) {
            return response()->json([
                'message' => 'Не удалось обработать ссылку организации.',
                'errors' => [
                    'url' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $connection = $connectOrganization->execute($sourceUrl, $normalizedUrl);

        return response()->json([
            'data' => [
                'organization' => (new OrganizationResource($connection->organization))->resolve($request),
                'parse_run' => (new ParseRunResource($connection->parseRun))->resolve($request),
            ],
        ], 202);
    }
}
