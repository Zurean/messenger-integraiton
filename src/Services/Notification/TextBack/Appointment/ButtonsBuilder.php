<?php

namespace App\Services\Notification\TextBack\Appointment;

use App\Entity\Brand;
use App\Entity\City;
use App\Entity\Generation;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Model;
use App\Entity\Specification;
use App\Entity\TextBack\Appointment;
use App\Helper\Hasher\MD5Hasher;
use App\Repository\BrandRepository;
use App\Repository\CityRepository;
use App\Repository\GenerationRepository;
use App\Repository\Maintenance\MaintenanceRepository;
use App\Repository\ModelRepository;
use App\Repository\SpecificationRepository;
use App\Services\Cache\CacheManager;
use App\Services\Updater\Maintenance\AppointmentUpdater;
use Psr\Cache\InvalidArgumentException;

class ButtonsBuilder
{
    /**
     * @var CityRepository
     */
    private CityRepository $cityRepository;

    /**
     * @var BrandRepository
     */
    private BrandRepository $brandRepository;

    /**
     * @var ModelRepository
     */
    private ModelRepository $modelRepository;

    /**
     * @var GenerationRepository
     */
    private GenerationRepository $generationRepository;

    /**
     * @var SpecificationRepository
     */
    private SpecificationRepository $specificationRepository;

    /**
     * @var MaintenanceRepository
     */
    private MaintenanceRepository $maintenanceRepository;

    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    /**
     * @var MD5Hasher
     */
    private MD5Hasher $hasher;

    /**
     * @var AppointmentUpdater
     */
    private AppointmentUpdater $appointmentUpdater;

    /**
     * @param CityRepository $cityRepository
     * @param BrandRepository $brandRepository
     * @param ModelRepository $modelRepository
     * @param GenerationRepository $generationRepository
     * @param SpecificationRepository $specificationRepository
     * @param MaintenanceRepository $maintenanceRepository
     * @param CacheManager $cacheManager
     * @param MD5Hasher $hasher
     * @param AppointmentUpdater $appointmentUpdater
     */
    public function __construct(
        CityRepository $cityRepository,
        BrandRepository $brandRepository,
        ModelRepository $modelRepository,
        GenerationRepository $generationRepository,
        SpecificationRepository $specificationRepository,
        MaintenanceRepository $maintenanceRepository,
        CacheManager $cacheManager,
        MD5Hasher $hasher,
        AppointmentUpdater $appointmentUpdater
    ) {
        $this->cityRepository = $cityRepository;
        $this->brandRepository = $brandRepository;
        $this->modelRepository = $modelRepository;
        $this->generationRepository = $generationRepository;
        $this->specificationRepository = $specificationRepository;
        $this->maintenanceRepository = $maintenanceRepository;
        $this->cacheManager = $cacheManager;
        $this->hasher = $hasher;
        $this->appointmentUpdater = $appointmentUpdater;
    }

    /**
     * @param Appointment $appointment
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function build(Appointment $appointment): array
    {
        $result = [];

        if (is_null($appointment->getCity())) {
            $cities = $this->cityRepository->findBy(['active' => true]);

            return $this->buildCitiesButtons($appointment, $cities);
        }

        if (is_null($appointment->getBrand()) && $appointment->getCity()) {
            $brands = $this->brandRepository->findByCityId($appointment->getCity()->getId());

            return $this->buildBrandsButtons($appointment, $brands);
        }

        if (
            is_null($appointment->getModel())
            && $appointment->getCity()
            && $appointment->getBrand()
        ) {
            $models = $this->modelRepository->createQueryBuilder('m')
                ->andWhere('m.isActive = true')
                ->andWhere('m.brand = :b')
                ->andWhere('m.externalId IS NOT NULL')
                ->orderBy('m.name', 'asc')
                ->setParameter('b', $appointment->getBrand())
                ->getQuery()->execute();

            return $this->buildModelsButtons($appointment, $models);
        }

        if (
            is_null($appointment->getGeneration())
            && $appointment->getCity()
            && $appointment->getBrand()
            && $appointment->getModel()
        ) {
            $generations = $this->generationRepository->findBy(
                [
                    'isActive' => true,
                    'model' => $appointment->getModel()
                ],
                [
                    'yearOfIssue' => 'desc'
                ]
            );

            return $this->buildGenerationsButtons($appointment, $generations);
        }

        if (
            is_null($appointment->getSpecification())
            && $appointment->getCity()
            && $appointment->getBrand()
            && $appointment->getModel()
            && $appointment->getGeneration()
        ) {
            $specifications = $this->specificationRepository->findBy(
                [
                    'isActive' => true,
                    'generation' => $appointment->getGeneration()
                ],
                [
                    'engineCapacity' => 'asc',
                    'enginePower' => 'asc',
                ]
            );

            return $this->buildSpecificationsButtons($appointment, $specifications);
        }

        if (
            is_null($appointment->getMaintenance())
            && $appointment->getCity()
            && $appointment->getBrand()
            && $appointment->getModel()
            && $appointment->getGeneration()
            && $appointment->getSpecification()
        ) {
            $maintenances = $this->maintenanceRepository->findBySpecification($appointment->getSpecification());

            return $this->buildMaintenancesButtons($appointment, $maintenances);
        }

        return $result;
    }

    /**
     * @param Appointment $appointment
     * @param string $name
     * @param array $payload
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildAndSaveData(
        Appointment $appointment,
        string $name,
        array $payload
    ): array {
        $key = $this->hashPayload($payload);

        $this->cacheManager->save(
            $key,
            $payload
        );

        $this->appointmentUpdater->addHash($appointment, $key, true);

        return [
            'text' => $name,
            'type' => 'ActionButton',
            'payload' => $key
        ];
    }

    /**
     * @param Appointment $appointment
     * @param City[] $cities
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildCitiesButtons(Appointment $appointment, array $cities): array
    {
        return array_map(function (City $city) use ($appointment) {
            return $this->buildAndSaveData(
                $appointment,
                $city->getName(),
                [
                    'type' => 'appointment',
                    'id' => $appointment->getId(),
                    'city' => $city->getId()
                ]
            );
        }, $cities);
    }

    /**
     * @param Appointment $appointment
     * @param Brand[] $brands
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildBrandsButtons(Appointment $appointment, array $brands): array
    {
        return array_map(function (Brand $brand) use ($appointment) {
            /** @var City $city */
            $city = $appointment->getCity();

            return $this->buildAndSaveData(
                $appointment,
                $brand->getName(),
                [
                    'type' => 'appointment',
                    'id' => $appointment->getId(),
                    'city' => $city->getId(),
                    'brand' => $brand->getId()
                ]
            );
        }, $brands);
    }

    /**
     * @param Appointment $appointment
     * @param Model[] $models
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildModelsButtons(Appointment $appointment, array $models): array
    {
        return array_map(function (Model $model) use ($appointment) {
            /** @var City $city */
            $city = $appointment->getCity();
            /** @var Brand $brand */
            $brand = $appointment->getBrand();

            return $this->buildAndSaveData(
                $appointment,
                $model->getName(),
                [
                    'type' => 'appointment',
                    'id' => $appointment->getId(),
                    'city' => $city->getId(),
                    'brand' => $brand->getId(),
                    'model' => $model->getId()
                ]
            );
        }, $models);
    }

    /**
     * @param Appointment $appointment
     * @param Generation[] $generations
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildGenerationsButtons(Appointment $appointment, array $generations): array
    {
        return array_map(function (Generation $generation) use ($appointment) {
            /** @var City $city */
            $city = $appointment->getCity();
            /** @var Brand $brand */
            $brand = $appointment->getBrand();
            /** @var Model $model */
            $model = $appointment->getModel();

            return $this->buildAndSaveData(
                $appointment,
                $generation->getFullDisplayedLabel(),
                [
                    'type' => 'appointment',
                    'id' => $appointment->getId(),
                    'city' => $city->getId(),
                    'brand' => $brand->getId(),
                    'model' => $model->getId(),
                    'generation' => $generation->getId()
                ]
            );
        }, $generations);
    }

    /**
     * @param Appointment $appointment
     * @param Specification[] $specifications
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildSpecificationsButtons(Appointment $appointment, array $specifications): array
    {
        return array_map(function (Specification $specification) use ($appointment) {
            /** @var City $city */
            $city = $appointment->getCity();
            /** @var Brand $brand */
            $brand = $appointment->getBrand();
            /** @var Model $model */
            $model = $appointment->getModel();
            /** @var Generation $generation */
            $generation = $appointment->getGeneration();

            return $this->buildAndSaveData(
                $appointment,
                $specification->getFullName(),
                [
                    'type' => 'appointment',
                    'id' => $appointment->getId(),
                    'city' => $city->getId(),
                    'brand' => $brand->getId(),
                    'model' => $model->getId(),
                    'generation' => $generation->getId(),
                    'specification' => $specification->getId()
                ]
            );
        }, $specifications);
    }

    /**
     * @param Appointment $appointment
     * @param Maintenance[] $maintenances
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function buildMaintenancesButtons(Appointment $appointment, array $maintenances): array
    {
        return array_map(function (Maintenance $maintenance) use ($appointment) {
            /** @var City $city */
            $city = $appointment->getCity();
            /** @var Brand $brand */
            $brand = $appointment->getBrand();
            /** @var Model $model */
            $model = $appointment->getModel();
            /** @var Generation $generation */
            $generation = $appointment->getGeneration();
            /** @var Specification $specification */
            $specification = $appointment->getSpecification();

            return $this->buildAndSaveData(
                $appointment,
                $this->buildMaintenanceName($maintenance),
                [
                    'type' => 'appointment',
                    'id' => $appointment->getId(),
                    'city' => $city->getId(),
                    'brand' => $brand->getId(),
                    'model' => $model->getId(),
                    'generation' => $generation->getId(),
                    'specification' => $specification->getId(),
                    'maintenance' => $maintenance->getId()
                ]
            );
        }, $maintenances);
    }

    private function hashPayload(array $payload): string
    {
        return $this->hasher->hashArray($payload);
    }

    private function buildMaintenanceName(Maintenance $maintenance): string
    {
        $yearLabel = 'год';

        if ($maintenance->getPeriod() > 4) {
            $yearLabel = 'лет';
        } elseif($maintenance->getPeriod() > 1) {
            $yearLabel = 'года';
        }

        return sprintf(
            '%d (%d км или %d %s)',
            $maintenance->getNumber(),
            $maintenance->getDistance(),
            $maintenance->getPeriod(),
            $yearLabel
        );
    }
}
