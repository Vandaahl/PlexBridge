<?php

namespace App\Entity;

use App\Repository\EpisodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeRepository::class)]
class Episode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imdb = null;

    #[ORM\Column(length: 255)]
    private ?string $plexGuid = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'episode')]
    private Collection $event;

    public function __construct()
    {
        $this->event = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getImdb(): ?string
    {
        return $this->imdb;
    }

    public function setImdb(?string $imdb): static
    {
        $this->imdb = $imdb;

        return $this;
    }

    public function getPlexGuid(): ?string
    {
        return $this->plexGuid;
    }

    public function setPlexGuid(string $plexGuid): static
    {
        $this->plexGuid = $plexGuid;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvent(): Collection
    {
        return $this->event;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->event->contains($event)) {
            $this->event->add($event);
            $event->setEpisode($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->event->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getEpisode() === $this) {
                $event->setEpisode(null);
            }
        }

        return $this;
    }
}
