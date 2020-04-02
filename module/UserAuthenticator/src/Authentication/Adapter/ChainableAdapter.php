<?php

namespace UserAuthenticator\Authentication\Adapter;

use Laminas\Authentication\Storage\StorageInterface;

interface ChainableAdapter
{
    /**
     * @param AdapterChainEvent $e
     * @return bool
     */
    public function authenticate(AdapterChainEvent $e);

    /**
     * @return StorageInterface
     */
    public function getStorage();
}
