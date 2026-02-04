<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\MinuteBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use Lucca\Bundle\CoreBundle\Entity\TimestampableTrait;
use Lucca\Bundle\LogBundle\Entity\LoggableInterface;
use Lucca\Bundle\ParameterBundle\Entity\Town;
use Lucca\Bundle\MinuteBundle\Repository\PlotRepository;
use Lucca\Bundle\DepartmentBundle\Entity\Department;

#[ORM\Table(name: 'lucca_minute_plot')]
#[ORM\Entity(repositoryClass: PlotRepository::class)]
class Plot implements LoggableInterface
{
    /** Traits */
    use TimestampableTrait;

    /** RISK constants */
    const RISK_FLOOD = 'choice.risk.flood';
    const RISK_FIRE = 'choice.risk.fire';
    const RISK_AVALANCHE = 'choice.risk.avalanche';
    const RISK_GROUND_MOVEMENT = 'choice.risk.groundMovement';
    const RISK_TECHNOLOGICAL = 'choice.risk.technological';
    const RISK_OTHER = 'choice.risk.other';

    /** LocationFrom constants */
    const LOCATION_FROM_ADDRESS = 'choice.locationFrom.address';
    const LOCATION_FROM_COORDINATES = 'choice.locationFrom.coordinates';
    const LOCATION_FROM_MANUAL = 'choice.locationFrom.manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Town::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Town $town;

    #[ORM\Column(nullable: true)]
    #[Assert\Length(min: 2, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    #[Assert\Regex(
        pattern: '/^(([0-9]+)?[A-Z]+[0-9]+)(, ?([0-9]+)?[A-Z]+[0-9]+)*$/',
        message: 'Ce champ doit contenir des séquences de lettres et/ou chiffres, séparées par des virgules, par exemple "A123, B456, 123ABC456".'
    )]
    private ?string $parcel = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Length(min: 2, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    #[Assert\Regex(
        pattern: '/^(([0-9]+)?[A-Z]+[0-9]+)(, ?([0-9]+)?[A-Z]+[0-9]+)*$/',
        message: 'Ce champ doit contenir des séquences de lettres et/ou chiffres, séparées par des virgules, par exemple "A123, B456, 123ABC456".'
    )]
    private ?string $parcelClean = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private ?string $address = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(type: 'bool', message: 'constraint.type')]
    private ?bool $isRiskZone = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private ?string $risk = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private ?string $place = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 40, scale: 30, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 40, scale: 30, nullable: true)]
    private ?string $longitude = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    /** TODO: set nullable for migration */
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\Column(length: 50)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Choice(choices: [
        self::LOCATION_FROM_ADDRESS,
        self::LOCATION_FROM_COORDINATES,
        self::LOCATION_FROM_MANUAL
    ], message: 'constraint.choice.status')]
    private string $locationFrom;

    /************************************************************************ Custom functions ************************************************************************/

    #[Assert\Callback]
    public function plotConstraint(ExecutionContextInterface $context): void
    {
        if (!$this->getAddress() && !$this->getPlace() && !$this->getLongitude() && !$this->getLatitude())
            $context->buildViolation('constraint.plot.address_or_parcel')
                ->atPath('address')
                ->addViolation();
        if (!$this->getAddress() && !$this->getPlace() && !$this->getLongitude() && !$this->getLatitude())
            $context->buildViolation('constraint.plot.address_or_parcel')
                ->atPath('place')
                ->addViolation();

        if (!$this->getAddress() && !$this->getPlace() && !$this->getLongitude() && !$this->getLatitude())
            $context->buildViolation('constraint.plot.locationNeeded')
                ->atPath('longitude')
                ->addViolation();

        if (!$this->getAddress() && !$this->getPlace() && !$this->getLongitude() && !$this->getLatitude())
            $context->buildViolation('constraint.plot.locationNeeded')
                ->atPath('latitude')
                ->addViolation();
    }

    public function getFullAddress(): string
    {
        $address = '';
        if ($this->getAddress())
            $address .= $this->getAddress() . ' ';
        if ($this->getPlace())
            $address .= $this->getPlace() . ' ';
        if ($this->getTown())
        $address .= $this->getTown()->getName() . ' - ' . $this->getTown()->getCode() . ' ';

        return $address;
    }

    /**
     * @inheritdoc
     */
    public function getLogName(): string
    {
        return 'Parcelle';
    }

    /********************************************************************* Automatic Getters & Setters *********************************************************************/

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setParcel(?string $parcel): self
    {
        $this->parcel = $parcel;

        return $this;
    }

    public function getParcel(): ?string
    {
        return $this->parcel;
    }

    public function setParcelClean(?string $parcelClean): self
    {
        $this->parcelClean = $parcelClean;

        return $this;
    }

    public function getParcelClean(): ?string
    {
        return $this->parcelClean;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setIsRiskZone(?bool $isRiskZone): self
    {
        $this->isRiskZone = $isRiskZone;

        return $this;
    }

    public function getIsRiskZone(): ?bool
    {
        return $this->isRiskZone;
    }

    public function setRisk(?string $risk): self
    {
        $this->risk = $risk;

        return $this;
    }

    public function getRisk(): ?string
    {
        return $this->risk;
    }

    public function setPlace(?string $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLocationFrom(string $locationFrom): self
    {
        $this->locationFrom = $locationFrom;

        return $this;
    }

    public function getLocationFrom(): string
    {
        return $this->locationFrom;
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

    public function setTown(Town $town): self
    {
        $this->town = $town;

        return $this;
    }

    public function getTown(): Town
    {
        return $this->town;
    }
}
