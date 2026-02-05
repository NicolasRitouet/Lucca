<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\MinuteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Lucca\Bundle\CoreBundle\Entity\TimestampableTrait;
use Lucca\Bundle\LogBundle\Entity\LoggableInterface;
use Lucca\Bundle\MinuteBundle\Repository\UpdatingRepository;
use Lucca\Bundle\DepartmentBundle\Entity\Department;

#[ORM\Table(name: 'lucca_minute_updating')]
#[ORM\Entity(repositoryClass: UpdatingRepository::class)]
class Updating implements LoggableInterface
{
    /** Traits */
    use TimestampableTrait;

    /** NATURE constants */
    const NATURE_AGGRAVATED = 'choice.nature.aggravated';
    const NATURE_UNCHANGED = 'choice.nature.unchanged';
    const NATURE_REGULARIZED = 'choice.nature.regularized';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    private string $num;

    #[ORM\ManyToOne(targetEntity: Minute::class, inversedBy: 'updatings')]
    #[ORM\JoinColumn(nullable: false)]
    private Minute $minute;

    /**
     * cascade: ['remove'] is used here because of a business rule:
     * each Control in this collection is strictly owned by this Updating.
     * Deleting the Updating must lead to the deletion of its associated Controls.
     */
    #[ORM\ManyToMany(targetEntity: Control::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'lucca_minute_updating_linked_control',
        joinColumns: [new ORM\JoinColumn(name: 'updating_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'control_id', referencedColumnName: 'id')]
    )]
    private Collection $controls;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    /** TODO: set nullable for migration */
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    private ?string $nature = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /************************************************************************ Custom functions ************************************************************************/

    public function __construct()
    {
        $this->controls = new ArrayCollection();
    }

    public function getControlsForFolder(): array
    {
        $result = [];

        foreach ($this->controls as $control) {
            if (($control instanceof Control && $control->getDateControl() && $control->getHourControl())
                && ($control->getAccepted() !== null && $control->getAccepted() === Control::ACCEPTED_NONE)
                or $control->getIsFenced() === false)
                $result[] = $control;
        }

        return $result;
    }

    public function getLogName(): string
    {
        return 'Actualisation';
    }

    /********************************************************************* Manual Getters & Setters *********************************************************************/

    public function setMinute(Minute $minute): self
    {
        $this->minute = $minute;
        $minute->addUpdating($this);

        return $this;
    }

    /********************************************************************* Automatic Getters & Setters *********************************************************************/

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setNum(string $num): self
    {
        $this->num = $num;

        return $this;
    }

    public function getNum(): string
    {
        return $this->num;
    }

    public function setDepartment(?Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setNature(?string $nature): self
    {
        $this->nature = $nature;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMinute(): Minute
    {
        return $this->minute;
    }

    public function addControl(Control $control): self
    {
        $this->controls[] = $control;

        return $this;
    }

    public function removeControl(Control $control): bool
    {
        return $this->controls->removeElement($control);
    }

    public function getControls(): Collection
    {
        return $this->controls;
    }
}
