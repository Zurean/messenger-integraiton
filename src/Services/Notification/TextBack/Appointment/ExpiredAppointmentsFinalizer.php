<?php

namespace App\Services\Notification\TextBack\Appointment;

use App\Entity\TextBack\Appointment;
use App\Repository\TextBack\AppointmentRepository;
use App\Services\Cache\CacheManager;
use App\Services\Updater\Maintenance\AppointmentUpdater;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use DateTimeImmutable;

class ExpiredAppointmentsFinalizer
{
    /**
     * @var AppointmentRepository
     */
    private AppointmentRepository $repository;

    /**
     * @var int
     */
    private int $lifetime;

    /**
     * @var AppointmentUpdater
     */
    private AppointmentUpdater $updater;

    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    /**
     * @param AppointmentRepository $repository
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(
        AppointmentRepository $repository,
        ParameterBagInterface $parameterBag,
        AppointmentUpdater $updater,
        CacheManager $cacheManager
    )
    {
        $this->repository = $repository;
        $this->lifetime = $parameterBag->get('text_back_maintenance_appointment_lifetime');
        $this->updater = $updater;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function process(): void
    {
        foreach ($this->repository->findAllActive() as $appointment) {
            if ($this->isExpired($appointment)) {
                foreach ($appointment->getHashes() as $hash) {
                    $this->cacheManager->remove($hash);
                }

                $this->updater->setFinal($appointment, true);
            }
        }
    }

    /**
     * @param Appointment $appointment
     *
     * @return bool
     */
    private function isExpired(Appointment $appointment): bool
    {
        $now = new DateTimeImmutable();
        /** @var DateTimeImmutable $createdAt */
        $createdAt = $appointment->getCreatedAt();

        return ($now->getTimestamp() >= $createdAt->modify(sprintf('+ %d seconds', $this->lifetime))->getTimestamp());
    }
}
