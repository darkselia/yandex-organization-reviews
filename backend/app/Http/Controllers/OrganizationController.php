<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\ConnectOrganization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ParseRunResource;
use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use App\Services\YandexMaps\YandexUrlException;
use App\Services\YandexMaps\YandexUrlResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends Controller
{
    public function show(Organization $organization): OrganizationResource
    {
        $this->ensureConfirmed($organization);
        $organization->load('latestParseRun');

        return OrganizationResource::make($organization);
    }

    public function reviews(Organization $organization): AnonymousResourceCollection
    {
        $this->ensureConfirmed($organization);
        $reviews = $organization->reviews()
            ->where('last_seen_at', '>=', $organization->last_synced_at)
            ->latest('published_at')
            ->latest('id')
            ->paginate(perPage: 50)
            ->withQueryString();

        return ReviewResource::collection($reviews);
    }

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

        $parseRun = $connectOrganization->execute($sourceUrl, $normalizedUrl);

        return response()->json([
            'data' => [
                'parse_run' => (new ParseRunResource($parseRun))->resolve($request),
            ],
        ], 202);
    }

    private function ensureConfirmed(Organization $organization): void
    {
        if ($organization->last_synced_at !== null) {
            return;
        }

        throw (new ModelNotFoundException)->setModel(Organization::class, [$organization->getKey()]);
    }
}
