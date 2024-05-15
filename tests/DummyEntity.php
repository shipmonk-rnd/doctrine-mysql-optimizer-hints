<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class DummyEntity
{

    #[Id]
    #[Column(type: 'string', nullable: false)]
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

}
