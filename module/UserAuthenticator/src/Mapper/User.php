<?php

namespace UserAuthenticator\Mapper;

use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Hydrator\HydratorInterface;
use UserAuthenticator\Model\UserInterface as UserModelInterface;

class User extends AbstractDbMapper implements UserInterface
{
    use EventManagerAwareTrait;

    protected $tableName  = 'user';

    public function findByEmail($email)
    {
        $select = $this->getSelect()
            ->where(['email' => $email]);
        $entity = $this->select($select)->current();

        $this->getEventManager()->trigger('find', $this, ['entity' => $entity]);

        return $entity;
    }

    public function findByUsername($username)
    {
        $select = $this->getSelect()
            ->where(['username' => $username]);
        $entity = $this->select($select)->current();

        $this->getEventManager()->trigger('find', $this, ['entity' => $entity]);

        return $entity;
    }

    public function findById($id)
    {
        $select = $this->getSelect()
            ->where(['user_id' => $id]);
        $entity = $this->select($select)->current();

        $this->getEventManager()->trigger('find', $this, ['entity' => $entity]);

        return $entity;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    public function insert(UserModelInterface $entity, $tableName = null, HydratorInterface $hydrator = null)
    {
        $result = parent::insert($entity, $tableName, $hydrator);

        $entity->setId($result->getGeneratedValue());

        return $result;
    }

    public function update(
        UserModelInterface $entity,
        $where = null,
        $tableName = null,
        HydratorInterface $hydrator = null
    ) {
        if (! $where) {
            $where = ['user_id' => $entity->getId()];
        }

        return parent::update($entity, $where, $tableName, $hydrator);
    }
}
