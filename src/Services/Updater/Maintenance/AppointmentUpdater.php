<?php

namespace App\Services\Updater\Maintenance;

use App\Entity\Brand;
use App\Entity\City;
use App\Entity\Generation;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Model;
use App\Entity\Specification;
use App\Entity\TextBack\Appointment;
use Doctrine\ORM\EntityManagerInterface;

class AppointmentUpdater
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

    /**
     * @param Appointment $appointment
     * @param City $city
     * @param bool|null $flush
     *
     * @return Appointment
     */
    public function updateCity(
        Appointment $appointment,
        City $city,
        ?bool $flush = false
    ): Appointment {
        $appointment->setCity($city);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    /**
     * @param Appointment $appointment
     * @param Brand $brand
     * @param bool|null $flush
     *
     * @return Appointment
     */
    public function updateBrand(
        Appointment $appointment,
        Brand $brand,
        ?bool $flush = false
    ): Appointment {
        $appointment->setBrand($brand);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    /**
     * @param Appointment $appointment
     * @param Model $model
     * @param bool|null $flush
     *
     * @return Appointment
     */
    public function updateModel(
        Appointment $appointment,
        Model $model,
        ?bool $flush = false
    ): Appointment {
        $appointment->setModel($model);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    /**
     * @param Appointment $appointment
     * @param Generation $generation
     * @param bool|null $flush
     *
     * @return Appointment
     */
    public function updateGeneration(
        Appointment $appointment,
        Generation $generation,
        ?bool $flush = false
    ): Appointment {
        $appointment->setGeneration($generation);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    /**
     * @param Appointment $appointment
     * @param Specification $specification
     * @param bool|null $flush
     *
     * @return Appointment
     */
    public function updateSpecification(
        Appointment $appointment,
        Specification $specification,
        ?bool $flush = false
    ): Appointment {
        $appointment->setSpecification($specification);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    /**
     * @param Appointment $appointment
     * @param Maintenance $maintenance
     * @param bool|null $flush
     *
     * @return Appointment
     */
    public function updateMaintenance(
        Appointment $appointment,
        Maintenance $maintenance,
        ?bool $flush = false
    ): Appointment
    {
        $appointment->setMaintenance($maintenance);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    public function setFinal(Appointment $appointment, bool $flush = false): Appointment
    {
        $appointment->setIsFinal(true);

        if ($flush) {
            $this->em->flush();
        }

        return $appointment;
    }

    public function addHash(
        Appointment $appointment,
        string $hash,
        bool $flush = false
    ): Appointment
    {
        $appointment->addHash($hash);

        if ($flush) {
            $this->em->flush();
        }


        return $appointment;
    }
}
