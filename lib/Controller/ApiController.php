<?php

declare(strict_types=1);

namespace OCA\AutoCurrency\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	/**
	 * An example API endpoint
	 *
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>
	 *
	 * 200: Data returned
	 */
	// #[NoAdminRequired]
	// #[ApiRoute(verb: 'GET', url: '/api')]
	// public function index(): DataResponse {
	// 	return new DataResponse(
	// 		['message' => 'Hello world!']
	// 	);
	// }

	/**
	 * Get current cron information
	 *
	 * @return DataResponse<Http::STATUS_OK, array{last_update:string,interval:int}, array{}>
	 *
	 * 200: Data returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/cron')]
	public function getCronInfo(): DataResponse {
		return new DataResponse(
			['last_update' => '2021-09-01 00:00:00', 'interval' => 24]
		);
	}
}
