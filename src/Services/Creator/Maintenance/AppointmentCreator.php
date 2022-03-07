<?php

namespace App\Services\Creator\Maintenance;

use App\DTO\Maintenance\AppointmentDTO;
use App\Entity\TextBack\Appointment;
use Doctrine\ORM\EntityManagerInterface;

class AppointmentCreator
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

    /**
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function create(AppointmentDTO $dto, bool $flush = false): Appointment
    {
        $appointment = (new Appointment())
            ->setChannel($dto->channel)
            ->setChannelId($dto->channelId)
            ->setChatId($dto->chatId);

        $this->em->persist($appointment);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }
}
