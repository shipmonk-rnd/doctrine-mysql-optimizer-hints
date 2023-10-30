<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class DummyEntity
{

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(nullable=false)
     */
    private $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

}
