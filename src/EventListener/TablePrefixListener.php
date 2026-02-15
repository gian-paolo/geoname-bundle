<?php

namespace Pallari\GeonameBundle\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::loadClassMetadata)]
class TablePrefixListener
{
    private array $entityToTableMap;

    public function __construct(array $entities, array $tables)
    {
        // Create a map of ClassName => TableName
        $this->entityToTableMap = [];
        foreach ($entities as $key => $className) {
            if (isset($tables[$key])) {
                $this->entityToTableMap[$className] = $tables[$key];
            }
        }
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $className = $classMetadata->getName();

        if (isset($this->entityToTableMap[$className])) {
            $classMetadata->setPrimaryTable([
                'name' => $this->entityToTableMap[$className]
            ]);
        }
    }
}
