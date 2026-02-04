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
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use Lucca\Bundle\AdherentBundle\Entity\{Adherent, Agent};
use Lucca\Bundle\CoreBundle\Entity\TimestampableTrait;
use Lucca\Bundle\DecisionBundle\Entity\Decision;
use Lucca\Bundle\DepartmentBundle\Entity\Department;
use Lucca\Bundle\LogBundle\Entity\LoggableInterface;
use Lucca\Bundle\MinuteBundle\Repository\MinuteRepository;
use Lucca\Bundle\ParameterBundle\Entity\Tribunal;

#[ORM\Table(name: 'lucca_minute')]
#[ORM\Entity(repositoryClass: MinuteRepository::class)]
class Minute implements LoggableInterface
{
    /** Traits */
    use TimestampableTrait;

    /** ORIGIN constants */
    const ORIGIN_COURIER = 'choice.origin.courier';
    const ORIGIN_PHONE = 'choice.origin.phone';
    const ORIGIN_EAGLE = 'choice.origin.eagle';
    const ORIGIN_AGENT = 'choice.origin.agent';
    const ORIGIN_OTHER = 'choice.origin.other';

    /** STATUS constants */
    const STATUS_OPEN = 'choice.statusMinute.open';
    const STATUS_CONTROL = 'choice.statusMinute.control';
    const STATUS_FOLDER = 'choice.statusMinute.folder';
    const STATUS_COURIER = 'choice.statusMinute.courier';
    const STATUS_AIT = 'choice.statusMinute.ait';
    const STATUS_UPDATING = 'choice.statusMinute.updating';
    const STATUS_DECISION = 'choice.statusMinute.decision';
    const STATUS_CLOSURE = 'choice.statusMinute.closure';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    private string $num;

    /** This attr is on nullable=true because it is set automatically */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Choice(choices: [
        self::STATUS_OPEN,
        self::STATUS_CONTROL,
        self::STATUS_FOLDER,
        self::STATUS_COURIER,
        self::STATUS_AIT,
        self::STATUS_UPDATING,
        self::STATUS_DECISION,
        self::STATUS_CLOSURE,
    ], message: 'constraint.closure.initiatingStructure')]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: Adherent::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Adherent $adherent;

    #[ORM\OneToOne(targetEntity: Plot::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    #[ORM\JoinColumn(nullable: false)]
    private Plot $plot;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    /** TODO: set nullable for migration */
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\ManyToOne(targetEntity: Tribunal::class)]
    private ?Tribunal $tribunal = null;

    #[ORM\ManyToOne(targetEntity: Tribunal::class)]
    private ?Tribunal $tribunalCompetent = null;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    private ?Agent $agent = null;

    #[ORM\ManyToMany(targetEntity: Human::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'lucca_minute_linked_human',
        joinColumns: [new ORM\JoinColumn(name: 'minute_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'human_id', referencedColumnName: 'id')]
    )]
    private Collection $humans;

    #[ORM\OneToMany(targetEntity: Control::class, mappedBy: 'minute', cascade: ['remove'], orphanRemoval: true)]
    private Collection $controls;

    #[ORM\OneToMany(targetEntity: Updating::class, mappedBy: 'minute', cascade: ['remove'], orphanRemoval: true)]
    private Collection $updatings;

    #[ORM\OneToMany(targetEntity: Decision::class, mappedBy: 'minute', cascade: ['remove'], orphanRemoval: true)]
    private Collection $decisions;

    #[ORM\Column]
    #[Assert\NotNull(message: 'constraint.not_null')]
    private \DateTime $dateOpening;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateLastUpdate = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        notInRangeMessage: 'constraint.date.range.notInRange',
        min: '2000-01-01',
        max: 'last day of December',
    )]
    private ?\DateTime $dateComplaint = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(
        min: 2, max: 60,
        minMessage: 'constraint.length.min',
        maxMessage: 'constraint.length.max',
    )]
    private ?string $nameComplaint = null;

    #[ORM\Column]
    #[Assert\Type(type: 'bool', message: 'constraint.type')]
    private bool $isClosed = false;

    #[ORM\OneToOne(targetEntity: Closure::class)]
    #[ORM\JoinColumn]
    private ?Closure $closure = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reporting = null;

    #[ORM\Column(name: 'origin', type: 'string', length: 30, nullable: true)]
    #[Assert\Choice(
        choices: [
            self::ORIGIN_COURIER,
            self::ORIGIN_PHONE,
            self::ORIGIN_EAGLE,
            self::ORIGIN_AGENT,
            self::ORIGIN_OTHER
        ], message: 'constraint.choice.origin'
    )]
    private ?string $origin = null;

    #[ORM\OneToMany(targetEntity: MinuteStory::class, mappedBy: 'minute', cascade: ['remove'], orphanRemoval: true)]
    private Collection $historic;

    /************************************************************************ Custom functions ************************************************************************/

    public function __construct()
    {
        $this->historic = new ArrayCollection();
        $this->humans = new ArrayCollection();
        $this->controls = new ArrayCollection();
        $this->updatings = new ArrayCollection();
        $this->decisions = new ArrayCollection();

        $this->dateOpening = new \DateTime('now');
        $this->origin = self::ORIGIN_AGENT;
        $this->status = self::STATUS_OPEN;
    }

    /**
     * Get Control with date and hour set for create a new folder
     */
    public function getControlsForFolder(): array
    {
        $result = array();

        foreach ($this->getControls() as $control) {
            /** Available to Frame 3 */
            if ($control->getType() === Control::TYPE_FOLDER) {
                /** Condition - Control have a date and hour + control is accepted + control is not already used */
                if (($control instanceof Control && $control->getDateControl() && $control->getHourControl())
                    && ($control->getAccepted() !== null && $control->getAccepted() === Control::ACCEPTED_NONE)
                    or $control->getIsFenced() === false && $control->getFolder() === null) {
                    $result[] = $control;
                }
            }
        }

        // Sort by createdAt method
        usort($result, function ($a, $b) {
            return $a->getCreatedAt() < $b->getCreatedAt();
        });

        return $result;
    }

    #[Assert\Callback]
    public function humanConstraint(ExecutionContextInterface $context): void
    {
        if (!$this->getHumans()) {
            $context->buildViolation('constraint.minute.humans')
                ->atPath('humans')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function plotConstraint(ExecutionContextInterface $context): void
    {
        if (!$this->getPlot()->getAddress() && !$this->getPlot()->getPlace() && !$this->getPlot()->getLatitude() && !$this->getPlot()->getLongitude())
            $context->buildViolation('constraint.plot.address_or_parcel')
                ->atPath('plot.address')
                ->addViolation();
        if (!$this->getPlot()->getAddress() && !$this->getPlot()->getPlace() && !$this->getPlot()->getLatitude() && !$this->getPlot()->getLongitude())
            $context->buildViolation('constraint.plot.address_or_parcel')
                ->atPath('plot.place')
                ->addViolation();

        if (!$this->getPlot()->getAddress() && !$this->getPlot()->getPlace() && !$this->getPlot()->getLatitude() && !$this->getPlot()->getLongitude())
            $context->buildViolation('constraint.plot.locationNeeded')
                ->atPath('plot.longitude')
                ->addViolation();
        if (!$this->getPlot()->getAddress() && !$this->getPlot()->getPlace() && !$this->getPlot()->getLatitude() && !$this->getPlot()->getLongitude())
            $context->buildViolation('constraint.plot.locationNeeded')
                ->atPath('plot.latitude')
                ->addViolation();
    }

    /**
     * @inheritdoc
     */
    public function getLogName(): string
    {
        return 'Dossier';
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

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setDateOpening(\DateTime $dateOpening): self
    {
        $this->dateOpening = $dateOpening;

        return $this;
    }

    public function getDateOpening(): \DateTime
    {
        return $this->dateOpening;
    }

    public function setDateComplaint(?\DateTime $dateComplaint): self
    {
        $this->dateComplaint = $dateComplaint;

        return $this;
    }

    public function getDateComplaint(): ?\DateTime
    {
        return $this->dateComplaint;
    }

    public function setNameComplaint(?string $nameComplaint): self
    {
        $this->nameComplaint = $nameComplaint;

        return $this;
    }

    public function getNameComplaint(): ?string
    {
        return $this->nameComplaint;
    }

    public function setIsClosed(bool $isClosed): self
    {
        $this->isClosed = $isClosed;

        return $this;
    }

    public function getIsClosed(): bool
    {
        return $this->isClosed;
    }

    public function setReporting(?string $reporting): self
    {
        $this->reporting = $reporting;

        return $this;
    }

    public function getReporting(): ?string
    {
        return $this->reporting;
    }

    public function setOrigin(?string $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setAdherent(Adherent $adherent): self
    {
        $this->adherent = $adherent;

        return $this;
    }

    public function getAdherent(): Adherent
    {
        return $this->adherent;
    }

    public function setPlot(Plot $plot): self
    {
        $this->plot = $plot;

        return $this;
    }

    public function getPlot(): Plot
    {
        return $this->plot;
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

    public function setTribunal(?Tribunal $tribunal): self
    {
        $this->tribunal = $tribunal;

        return $this;
    }

    public function getTribunal(): ?Tribunal
    {
        return $this->tribunal;
    }

    public function setTribunalCompetent(?Tribunal $tribunalCompetent): self
    {
        $this->tribunalCompetent = $tribunalCompetent;

        return $this;
    }

    public function getTribunalCompetent(): ?Tribunal
    {
        return $this->tribunalCompetent;
    }

    public function setAgent(?Agent $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function addHuman(Human $human): self
    {
        $this->humans[] = $human;

        return $this;
    }

    public function removeHuman(Human $human): bool
    {
        return $this->humans->removeElement($human);
    }

    public function getHumans(): Collection
    {
        return $this->humans;
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

    public function addUpdating(Updating $updating): self
    {
        $this->updatings[] = $updating;

        return $this;
    }

    public function removeUpdating(Updating $updating): bool
    {
        return $this->updatings->removeElement($updating);
    }

    public function getUpdatings(): Collection
    {
        return $this->updatings;
    }

    public function addDecision(Decision $decision): self
    {
        $this->decisions[] = $decision;

        return $this;
    }

    public function removeDecision(Decision $decision): bool
    {
        return $this->decisions->removeElement($decision);
    }

    public function getDecisions(): Collection
    {
        return $this->decisions;
    }

    public function setClosure(?Closure $closure): self
    {
        $this->closure = $closure;

        return $this;
    }

    public function getClosure(): ?Closure
    {
        return $this->closure;
    }

    public function addHistoric(MinuteStory $historic): self
    {
        $this->historic[] = $historic;

        return $this;
    }

    public function removeHistoric(MinuteStory $historic): bool
    {
        return $this->historic->removeElement($historic);
    }

    public function getHistoric(): Collection
    {
        return $this->historic;
    }

    public function setDateLastUpdate(?\DateTime $dateLastUpdate): self
    {
        $this->dateLastUpdate = $dateLastUpdate;

        return $this;
    }

    public function getDateLastUpdate(): ?\DateTime
    {
        return $this->dateLastUpdate;
    }
}
