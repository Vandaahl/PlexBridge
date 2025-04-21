<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?float $rating = null;

    #[ORM\Column(length: 255)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(inversedBy: 'event')]
    private ?Movie $movie = null;

    #[ORM\ManyToOne(inversedBy: 'event')]
    private ?Episode $episode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statusTrakt = null;

    #[ORM\Column(length: 255)]
    private ?string $event = null;

    #[ORM\Column(length: 255)]
    private ?string $plexUser = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statusLetterboxd = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): static
    {
        $this->movie = $movie;

        return $this;
    }

    public function getEpisode(): ?Episode
    {
        return $this->episode;
    }

    public function setEpisode(?Episode $episode): static
    {
        $this->episode = $episode;

        return $this;
    }


    public function getStatusTrakt(): ?string
    {
        return $this->statusTrakt;
    }

    public function setStatusTrakt(?string $statusTrakt): static
    {
        $this->statusTrakt = $statusTrakt;

        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(string $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getPlexUser(): ?string
    {
        return $this->plexUser;
    }

    public function setPlexUser(string $plexUser): static
    {
        $this->plexUser = $plexUser;

        return $this;
    }

    public function getStatusLetterboxd(): ?string
    {
        return $this->statusLetterboxd;
    }

    public function setStatusLetterboxd(?string $statusLetterboxd): static
    {
        $this->statusLetterboxd = $statusLetterboxd;

        return $this;
    }
}
