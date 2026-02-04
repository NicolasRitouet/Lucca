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

use Lucca\Bundle\AdherentBundle\Entity\Agent;
use Lucca\Bundle\FolderBundle\Entity\Folder;
use Lucca\Bundle\MinuteBundle\Repository\ControlRepository;
use Lucca\Bundle\CoreBundle\Entity\TimestampableTrait;
use Lucca\Bundle\LogBundle\Entity\LoggableInterface;
use Lucca\Bundle\DepartmentBundle\Entity\Department;

#[ORM\Table(name: 'lucca_minute_control')]
#[ORM\Entity(repositoryClass: ControlRepository::class)]
class Control implements LoggableInterface
{
    /** Traits */
    use TimestampableTrait;

    /** TYPE constants */
    const TYPE_FOLDER = 'choice.type.folder';
    const TYPE_REFRESH = 'choice.type.refresh';
    /** STATE constants */
    const STATE_INSIDE = 'choice.state.inside';
    const STATE_INSIDE_WITHOUT_CONVOCATION = 'choice.state.inside_without_convocation';
    const STATE_OUTSIDE = 'choice.state.outside';
    const STATE_NEIGHBOUR = 'choice.state.neighbour';
    /** REASON constants */
    const REASON_ERROR_ADRESS = 'choice.reason.error_adress';
    const REASON_UNKNOW_ADRESS = 'choice.reason.unknown_adress';
    const REASON_REFUSED_LETTER = 'choice.reason.refused_letter';
    const REASON_UNCLAIMED_LETTER = 'choice.reason.unclaimed_letter';
    /** ACCEPTED constants */
    const ACCEPTED_OK = 'choice.accepted.ok';
    const ACCEPTED_NOK = 'choice.accepted.nok';
    const ACCEPTED_NONE = 'choice.accepted.none';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Minute::class, inversedBy: 'controls')]
    #[ORM\JoinColumn(nullable: false)]
    private Minute $minute;

    #[ORM\ManyToMany(targetEntity: Human::class)]
    #[ORM\JoinTable(name: 'lucca_minute_control_linked_human_minute',
        joinColumns: [new ORM\JoinColumn(name: 'control_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'human_id', referencedColumnName: 'id')]
    )]
    private Collection $humansByMinute;

    #[ORM\ManyToMany(targetEntity: Human::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'lucca_minute_control_linked_human_control',
        joinColumns: [new ORM\JoinColumn(name: 'control_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'human_id', referencedColumnName: 'id')]
    )]
    private Collection $humansByControl;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Agent $agent;

    #[ORM\ManyToMany(targetEntity: AgentAttendant::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'lucca_minute_control_linked_agent_attendant',
        joinColumns: [new ORM\JoinColumn(name: 'control_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'agent_attendant_id', referencedColumnName: 'id')]
    )]
    private Collection $agentAttendants;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    /** TODO: set nullable for migration */
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\Column(length: 25)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 25, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private string $type;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $datePostal = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateSended = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateNotified = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateReturned = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 60, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private ?string $reason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateContact = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 40, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private ?string $accepted = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateControl = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Assert\Type('\DateTimeInterface')]
    private ?\DateTime $hourControl = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotNull(message: 'constraint.not_null')]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    private string $stateControl;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(type: 'bool', message: 'constraint.type')]
    private ?bool $summoned = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Type(type: 'string', message: 'constraint.type')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'constraint.length.min', maxMessage: 'constraint.length.max')]
    private ?string $courierDelivery = null;

    #[ORM\Column]
    #[Assert\Type(type: 'bool', message: 'constraint.type')]
    private bool $isFenced = false;

    #[ORM\OneToMany(targetEntity: ControlEdition::class, mappedBy: 'control', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $editions;

    #[ORM\OneToOne(targetEntity: Folder::class, mappedBy: 'control', cascade: ['remove'])]
    private ?Folder $folder = null;

    /************************************************************************ Custom functions ************************************************************************/

    public function __construct($type)
    {
        $this->humansByMinute = new ArrayCollection();
        $this->humansByControl = new ArrayCollection();
        $this->agentAttendants = new ArrayCollection();
        $this->editions = new ArrayCollection();

        $this->setType($type);
    }

    public function getFormLabel(): string
    {
        $result = '';
        if ($this->getDateControl() ) {
            $result .= $this->getDateControl()->format('d/m/Y');
        }

        if ($this->getHourControl()) {
            $result .= $this->getHourControl()->format('H:i');
        }

        if (!$result) {
            return 'Contrôle non défini';
        }

        return $result;
    }

    public function getLogName(): string
    {
        return 'Contrôle';
    }

    /************************************************************************ Custom constraints ************************************************************************/

    #[Assert\Callback]
    public function dateSendedConstraint(ExecutionContextInterface $context): void
    {
        if ($this->getDateSended()) {
            if ($this->getDatePostal() && !($this->getDateSended() >= $this->getDatePostal())) {
                $context->buildViolation('constraint.control.send_greater_equal_postal')
                    ->atPath('dateSended')
                    ->addViolation();
            }
            if ($this->getDateNotified() && !($this->getDateSended() < $this->getDateNotified())) {
                $context->buildViolation('constraint.control.send_less_notified')
                    ->atPath('dateSended')
                    ->addViolation();
            }
            if ($this->getDateReturned() && !($this->getDateSended() < $this->getDateReturned())) {
                $context->buildViolation('constraint.control.send_less_returned')
                    ->atPath('dateSended')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    public function dateNotifiedConstraint(ExecutionContextInterface $context): void
    {
        if ($this->getDateNotified()) {
            if ($this->getDatePostal() && !($this->getDateNotified() > $this->getDatePostal())) {
                $context->buildViolation('constraint.control.notified_greater_postal')
                    ->atPath('dateNotified')
                    ->addViolation();
            }
            if ($this->getDateSended() && !($this->getDateNotified() > $this->getDateSended())) {
                $context->buildViolation('constraint.control.notified_greater_sended')
                    ->atPath('dateNotified')
                    ->addViolation();
            }
            if ($this->getDateReturned() && !($this->getDateNotified() < $this->getDateReturned())) {
                $context->buildViolation('constraint.control.notified_less_returned')
                    ->atPath('dateNotified')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    public function dateControlConstraint(ExecutionContextInterface $context): void
    {
        if ($this->getDateReturned()) {
            if ($this->getDatePostal() && !($this->getDateReturned() > $this->getDatePostal())) {
                $context->buildViolation('constraint.control.returned_greater_postal')
                    ->atPath('dateReturned')
                    ->addViolation();
            }
            if ($this->getDateSended() && !($this->getDateReturned() > $this->getDateSended())) {
                $context->buildViolation('constraint.control.returned_greater_sended')
                    ->atPath('dateReturned')
                    ->addViolation();
            }
            if ($this->getDateNotified() && !($this->getDateReturned() > $this->getDateNotified())) {
                $context->buildViolation('constraint.control.returned_greater_notified')
                    ->atPath('dateControl')
                    ->addViolation();
            }
        }
    }

    /********************************************************************* Manual Getters & Setters *********************************************************************/

    public function addEdition(ControlEdition $edition): self
    {
        $this->editions[] = $edition;
        $edition->setControl($this);

        return $this;
    }

    public function setMinute(Minute $minute): self
    {
        $this->minute = $minute;
        $minute->addControl($this);

        return $this;
    }

    /********************************************************************* Automatic Getters & Setters *********************************************************************/

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setDatePostal(?\DateTime $datePostal): self
    {
        $this->datePostal = $datePostal;

        return $this;
    }

    public function getDatePostal(): ?\DateTime
    {
        return $this->datePostal;
    }

    public function setDateSended(?\DateTime $dateSended): self
    {
        $this->dateSended = $dateSended;

        return $this;
    }

    public function getDateSended(): ?\DateTime
    {
        return $this->dateSended;
    }

    public function setDateNotified(?\DateTime $dateNotified): self
    {
        $this->dateNotified = $dateNotified;

        return $this;
    }

    public function getDateNotified(): ?\DateTime
    {
        return $this->dateNotified;
    }

    public function setDateReturned(?\DateTime $dateReturned): self
    {
        $this->dateReturned = $dateReturned;

        return $this;
    }

    public function getDateReturned(): ?\DateTime
    {
        return $this->dateReturned;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setDateContact(?\DateTime $dateContact): self
    {
        $this->dateContact = $dateContact;

        return $this;
    }

    public function getDateContact(): ?\DateTime
    {
        return $this->dateContact;
    }

    public function setAccepted(?string $accepted): self
    {
        $this->accepted = $accepted;

        return $this;
    }

    public function getAccepted(): ?string
    {
        return $this->accepted;
    }

    public function setDateControl(?\DateTime $dateControl): self
    {
        $this->dateControl = $dateControl;

        return $this;
    }

    public function getDateControl(): ?\DateTime
    {
        return $this->dateControl;
    }

    public function setHourControl(?\DateTime $hourControl): self
    {
        $this->hourControl = $hourControl;

        return $this;
    }

    public function getHourControl(): ?\DateTime
    {
        return $this->hourControl;
    }

    public function setStateControl(string $stateControl): self
    {
        $this->stateControl = $stateControl;

        return $this;
    }

    public function getStateControl(): string
    {
        return $this->stateControl;
    }

    public function setSummoned(?bool $summoned): self
    {
        $this->summoned = $summoned;

        return $this;
    }

    public function getSummoned(): ?bool
    {
        return $this->summoned;
    }

    public function setCourierDelivery(?string $courierDelivery): self
    {
        $this->courierDelivery = $courierDelivery;

        return $this;
    }

    public function getCourierDelivery(): ?string
    {
        return $this->courierDelivery;
    }

    public function setIsFenced(bool $isFenced): self
    {
        $this->isFenced = $isFenced;

        return $this;
    }

    public function getIsFenced(): bool
    {
        return $this->isFenced;
    }

    public function getMinute(): Minute
    {
        return $this->minute;
    }

    public function addHumansByMinute(Human $humansByMinute): self
    {
        $this->humansByMinute[] = $humansByMinute;

        return $this;
    }

    public function removeHumansByMinute(Human $humansByMinute): void
    {
        $this->humansByMinute->removeElement($humansByMinute);
    }

    public function getHumansByMinute(): Collection
    {
        return $this->humansByMinute;
    }

    public function addHumansByControl(Human $humansByControl): self
    {
        $this->humansByControl[] = $humansByControl;

        return $this;
    }

    public function removeHumansByControl(Human $humansByControl): void
    {
        $this->humansByControl->removeElement($humansByControl);
    }

    public function getHumansByControl(): Collection
    {
        return $this->humansByControl;
    }

    public function setAgent(Agent $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    public function getAgent(): Agent
    {
        return $this->agent;
    }

    public function addAgentAttendant(AgentAttendant $agentAttendant): self
    {
        $this->agentAttendants[] = $agentAttendant;

        return $this;
    }

    public function removeAgentAttendant(AgentAttendant $agentAttendant): void
    {
        $this->agentAttendants->removeElement($agentAttendant);
    }

    public function getAgentAttendants(): Collection
    {
        return $this->agentAttendants;
    }

    public function removeEdition(ControlEdition $edition): void
    {
        $this->editions->removeElement($edition);
    }

    public function getEditions(): Collection
    {
        return $this->editions;
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

    public function setFolder(?Folder $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    public function getFolder(): ?Folder
    {
        return $this->folder;
    }
}
