<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\FolderBundle\Entity;

use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Lucca\Bundle\CoreBundle\Entity\TimestampableTrait;
use Lucca\Bundle\FolderBundle\Repository\FolderRepository;
use Lucca\Bundle\LogBundle\Entity\LoggableInterface;
use Lucca\Bundle\MediaBundle\Entity\{Media, MediaAsyncInterface, MediaListAsyncInterface};
use Lucca\Bundle\MinuteBundle\Entity\{Control, Human, Minute};
use Lucca\Bundle\DepartmentBundle\Entity\Department;

#[ORM\Table(name: "lucca_minute_folder")]
#[ORM\Entity(repositoryClass: FolderRepository::class)]
#[ORM\UniqueConstraint(fields: ['num', 'department'])]
class Folder implements LoggableInterface, MediaAsyncInterface, MediaListAsyncInterface
{
    use TimestampableTrait;

    const TYPE_FOLDER = 'choice.type.folder';
    const TYPE_REFRESH = 'choice.type.refresh';
    const NATURE_HUT = 'choice.nature.hut';
    const NATURE_OTHER = 'choice.nature.other';
    const NATURE_OBSTACLE = 'choice.nature.obstacle';
    const NATURE_FORMAL_OFFENSE = 'choice.nature.formalOffense';
    const NATURE_SUBSTANTIVE_OFFENSE = 'choice.nature.substantiveOffense';
    const REASON_OBS_REFUSE_ACCESS_AFTER_LETTER = 'choice.reason_obs.refuseAccessAfterLetter';
    const REASON_OBS_REFUSE_BY_RECIPIENT = 'choice.reason_obs.refuseByRecipient';
    const REASON_OBS_UNCLAIMED_BY_RECIPIENT = 'choice.reason_obs.unclaimedByRecipient';
    const REASON_OBS_ACCESS_REFUSED = 'choice.reason_obs.accessRefused';
    const REASON_OBS_ABSENT_DURING_CONTROL = 'choice.reason_obs.absentDuringControl';

    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    #[Assert\Type(type: "string", message: "constraint.type")]
    private string $num;

    #[ORM\ManyToOne(targetEntity: Minute::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\Valid]
    private Minute $minute;

    #[ORM\OneToOne(targetEntity: Control::class, inversedBy: 'folder')]
    #[ORM\JoinColumn(name: 'control_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Control $control;

    #[ORM\ManyToMany(targetEntity: Natinf::class)]
    #[ORM\JoinTable(name: "lucca_minute_folder_linked_natinf",
        joinColumns: [new ORM\JoinColumn(name: "folder_id", referencedColumnName: "id", onDelete: "CASCADE")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "natinf_id", referencedColumnName: "id")]
    )]
    private Collection $natinfs;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: "lucca_minute_folder_linked_tag_nature",
        joinColumns: [new ORM\JoinColumn(name: "folder_id", referencedColumnName: "id", onDelete: "CASCADE")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "tag_id", referencedColumnName: "id")]
    )]
    private Collection $tagsNature;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: "lucca_minute_folder_linked_tag_town",
        joinColumns: [new ORM\JoinColumn(name: "folder_id", referencedColumnName: "id", onDelete: "CASCADE")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "tag_id", referencedColumnName: "id")]
    )]
    private Collection $tagsTown;

    #[ORM\ManyToMany(targetEntity: Human::class)]
    #[ORM\JoinTable(name: "lucca_minute_folder_linked_human_minute",
        joinColumns: [new ORM\JoinColumn(name: "folder_id", referencedColumnName: "id", onDelete: "CASCADE")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "human_id", referencedColumnName: "id")]
    )]
    private Collection $humansByMinute;

    #[ORM\ManyToMany(targetEntity: Human::class, cascade: ["persist"])]
    #[ORM\JoinTable(name: "lucca_minute_folder_linked_human_folder",
        joinColumns: [new ORM\JoinColumn(name: "folder_id", referencedColumnName: "id", onDelete: "CASCADE")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "human_id", referencedColumnName: "id")]
    )]
    private Collection $humansByFolder;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    /** TODO: set nullable for migration */
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\OneToOne(targetEntity: Courier::class, orphanRemoval: true)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Courier $courier = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Type(type: "string", message: "constraint.type")]
    private ?string $type = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Type(type: "string", message: "constraint.type")]
    private ?string $nature = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Type(type: "string", message: "constraint.type")]
    private ?string $reasonObstacle = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type("\DateTimeInterface")]
    private ?\DateTime $dateClosure = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ascertainment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $violation = null;

    #[ORM\OneToOne(targetEntity: FolderEdition::class, cascade: ["persist", "remove"])]
    private ?FolderEdition $edition = null;

    #[ORM\OneToMany(targetEntity: ElementChecked::class, mappedBy: "folder", cascade: ["persist", "remove"])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $elements;

    #[ORM\Column]
    #[Assert\Type(type: "bool", message: "constraint.type")]
    private bool $isReReaded = false;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ["persist"])]
    private ?Media $folderSigned = null;

    #[ORM\ManyToMany(targetEntity: Media::class, cascade: ["persist", "remove"])]
    #[ORM\JoinTable(name: "lucca_folder_linked_media",
        joinColumns: [new ORM\JoinColumn(name: "page_id", referencedColumnName: "id", onDelete: "cascade")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "media_id", referencedColumnName: "id")]
    )]
    private Collection $annexes;

    /************************************************************************ Custom functions ************************************************************************/

    /**
     * Folder constructor
     */
    public function __construct()
    {
        $this->natinfs = new ArrayCollection();
        $this->tagsNature = new ArrayCollection();
        $this->tagsTown = new ArrayCollection();
        $this->humansByFolder = new ArrayCollection();
        $this->humansByMinute = new ArrayCollection();
        $this->elements = new ArrayCollection();
        $this->annexes = new ArrayCollection();
    }

    /**
     * Has tag in tags collection
     */
    public function hasTag($string): bool
    {
        foreach ($this->tagsNature as $element) {
            if ($element->getName() === $string) {
                return true;
            }
        }

        foreach ($this->getTagsTown() as $element) {
            if ($element->getName() === $string) {
                return true;
            }
        }

        return false;
    }

    /**
     * Has natinf in natinfs collection
     * TODO Care string param and natinf num is an int
     */
    public function hasNatinf($string): bool
    {
        foreach ($this->getNatinfs() as $element) {
            if ($element->getNum() == $string) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set media by asynchronous method.
     */
    public function setAsyncMedia(?Media $media = null): self
    {
        $this->setFolderSigned($media);

        return $this;
    }

    /**
     * Get media by asynchronous method.
     */
    public function getAsyncMedia(): ?Media
    {
        return $this->getFolderSigned();
    }

    /**
     * Add media by asynchronous method.
     */
    public function addAsyncMedia(Media $media, ?string $vars = null): self
    {
        $this->addAnnex($media);

        return $this;
    }

    /**
     * Remove media by asynchronous method.
     */
    public function removeAsyncMedia(Media $media, string $vars = null): bool
    {
        return $this->removeAnnex($media);
    }

    /**
     * Get medias by asynchronous method.
     */
    public function getAsyncMedias(): Collection
    {
        return $this->getAnnexes();
    }

    /**
     * @inheritdoc
     */
    public function getLogName(): string
    {
        return 'Procès verbal';
    }

    /********************************************************************* Manual Getters & Setters *********************************************************************/

    public function addElement(ElementChecked $elementChecked): self
    {
        if (!$this->elements->contains($elementChecked)) {
            $this->elements[] = $elementChecked;
            $elementChecked->setFolder($this);
        }

        return $this;
    }

    public function setControl(Control $control): self
    {
        $this->control = $control;
        $control->setFolder($this);

        return $this;
    }

    /********************************************************************* Automatic Getters & Setters *********************************************************************/

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNum(): string
    {
        return $this->num;
    }

    public function setNum(string $num): self
    {
        $this->num = $num;

        return $this;
    }

    public function getMinute(): Minute
    {
        return $this->minute;
    }

    public function setMinute(Minute $minute): self
    {
        $this->minute = $minute;

        return $this;
    }

    public function getControl(): Control
    {
        return $this->control;
    }

    public function getNatinfs(): Collection
    {
        return $this->natinfs;
    }

    public function addNatinf(Natinf $natinf): self
    {
        if (!$this->natinfs->contains($natinf)) {
            $this->natinfs[] = $natinf;
        }

        return $this;
    }

    public function getTagsNature(): Collection
    {
        return $this->tagsNature;
    }

    public function setTagsNature(Collection $tagsNature): self
    {
        $this->tagsNature = $tagsNature;

        return $this;
    }

    public function getTagsTown(): Collection
    {
        return $this->tagsTown;
    }

    public function setTagsTown(Collection $tagsTown): self
    {
        $this->tagsTown = $tagsTown;

        return $this;
    }

    public function getHumansByMinute(): Collection
    {
        return $this->humansByMinute;
    }

    public function setHumansByMinute(Collection $humansByMinute): self
    {
        $this->humansByMinute = $humansByMinute;

        return $this;
    }

    public function getHumansByFolder(): Collection
    {
        return $this->humansByFolder;
    }

    public function setHumansByFolder(Collection $humansByFolder): self
    {
        $this->humansByFolder = $humansByFolder;

        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getCourier(): ?Courier
    {
        return $this->courier;
    }

    public function setCourier(?Courier $courier): self
    {
        $this->courier = $courier;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(?string $nature): self
    {
        $this->nature = $nature;

        return $this;
    }

    public function getReasonObstacle(): ?string
    {
        return $this->reasonObstacle;
    }

    public function setReasonObstacle(?string $reasonObstacle): self
    {
        $this->reasonObstacle = $reasonObstacle;

        return $this;
    }

    public function getDateClosure(): ?\DateTime
    {
        return $this->dateClosure;
    }

    public function setDateClosure(?\DateTime $dateClosure): self
    {
        $this->dateClosure = $dateClosure;

        return $this;
    }

    public function getAscertainment(): ?string
    {
        return $this->ascertainment;
    }

    public function setAscertainment(?string $ascertainment): self
    {
        $this->ascertainment = $ascertainment;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getViolation(): ?string
    {
        return $this->violation;
    }

    public function setViolation(?string $violation): self
    {
        $this->violation = $violation;

        return $this;
    }

    public function getEdition(): ?FolderEdition
    {
        return $this->edition;
    }

    public function setEdition(?FolderEdition $edition): self
    {
        $this->edition = $edition;

        return $this;
    }

    public function getElements(): Collection
    {
        return $this->elements;
    }

    public function setElements(Collection $elements): self
    {
        $this->elements = $elements;

        return $this;
    }

    public function getIsReReaded(): bool
    {
        return $this->isReReaded;
    }

    public function setIsReReaded(bool $isReReaded): self
    {
        $this->isReReaded = $isReReaded;

        return $this;
    }

    public function getFolderSigned(): ?Media
    {
        return $this->folderSigned;
    }

    public function setFolderSigned(?Media $folderSigned): self
    {
        $this->folderSigned = $folderSigned;

        return $this;
    }

    public function getAnnexes(): Collection
    {
        return $this->annexes;
    }

    public function addAnnex(Media $annex): self
    {
        if (!$this->annexes->contains($annex)) {
            $this->annexes[] = $annex;
        }

        return $this;
    }

    public function removeAnnex(Media $annex): bool
    {
        return $this->annexes->removeElement($annex);
    }
}
