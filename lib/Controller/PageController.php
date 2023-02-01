<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Controller;

use OCA\AutoCurrency\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\BackgroundJob\IJobList;
use OCP\Util;

class PageController extends Controller {
  private IJobList $jobList;

  public function __construct(IRequest $request, IJobList $jobList) {
    parent::__construct(Application::APP_ID, $request);
    $this->jobList = $jobList;
  }

  /**
   * @NoAdminRequired
   * @NoCSRFRequired
   */
  public function index(): TemplateResponse {
    Util::addScript(Application::APP_ID, 'autocurrency-main');
    // $this->jobList->add('OCA\AutoCurrency\BackgroundJob\FetchCurrenciesJob');
    return new TemplateResponse(Application::APP_ID, 'main');
  }
}
