<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\AutoCurrency\Db;

use OCA\Cospend\Db\Project;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Project>
 */
class CospendProjectMapper extends QBMapper {
  public function __construct(IDBConnection $db) {
    parent::__construct($db, 'cospend_projects', Project::class);
  }

  /**
   * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
   * @throws DoesNotExistException
   */
  public function find(int $id): Project {
    /* @var $qb IQueryBuilder */
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from('cospend_projects')
      ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
    return $this->findEntity($qb);
  }

  /**
   * @param string $projectId
   * @return array
   */
  public function findAll(): array {
    /* @var $qb IQueryBuilder */
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from('cospend_projects');
    return $this->findEntities($qb);
  }
}
