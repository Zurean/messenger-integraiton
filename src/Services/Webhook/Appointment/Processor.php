<?php

namespace App\Services\Webhook\Appointment;

use App\DTO\Maintenance\AppointmentDTO;
use App\Entity\Brand;
use App\Entity\City;
use App\Entity\Generation;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Model;
use App\Entity\Specification;
use App\Entity\TextBack\Appointment;
use App\Repository\BrandRepository;
use App\Repository\CityRepository;
use App\Repository\GenerationRepository;
use App\Repository\Maintenance\MaintenanceRepository;
use App\Repository\ModelRepository;
use App\Repository\SpecificationRepository;
use App\Repository\TextBack\AppointmentRepository;
use App\Services\Cache\CacheManager;
use App\Services\Creator\Maintenance\AppointmentCreator;
use App\Services\Notification\TextBack\AppointmentSender;
use App\Services\Updater\Maintenance\AppointmentUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

class Processor
{
    /**
     * @var string
     */
    private const MAP_URL_SCHEME = '%s/to/map/?b=%d&m=%d&g=%d&s=%d&mt=%d';

    /**
     * @var AppointmentRepository
     */
    private AppointmentRepository $appointmentRepository;

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
     * @var AppointmentCreator
     */
    private AppointmentCreator $appointmentCreator;

    /**
     * @var AppointmentUpdater
     */
    private AppointmentUpdater $appointmentUpdater;

    /**
     * @var AppointmentSender
     */
    private AppointmentSender $messageSender;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var string
     */
    private string $siteUrl;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    /**
     * @param AppointmentRepository $appointmentRepository
     * @param CityRepository $cityRepository
     * @param BrandRepository $brandRepository
     * @param ModelRepository $modelRepository
     * @param GenerationRepository $generationRepository
     * @param SpecificationRepository $specificationRepository
     * @param MaintenanceRepository $maintenanceRepository
     * @param AppointmentCreator $appointmentCreator
     * @param AppointmentUpdater $appointmentUpdater
     * @param AppointmentSender $messageSender
     * @param ParameterBagInterface $parameterBag
     * @param LoggerInterface $textbackMaintenanceAppointmentLogger
     * @param CacheManager $cacheManager
     * @param EntityManagerInterface $em
     */
    public function __construct(
        AppointmentRepository $appointmentRepository,
        CityRepository $cityRepository,
        BrandRepository $brandRepository,
        ModelRepository $modelRepository,
        GenerationRepository $generationRepository,
        SpecificationRepository $specificationRepository,
        MaintenanceRepository $maintenanceRepository,
        AppointmentCreator $appointmentCreator,
        AppointmentUpdater $appointmentUpdater,
        AppointmentSender $messageSender,
        ParameterBagInterface $parameterBag,
        LoggerInterface $textbackMaintenanceAppointmentLogger,
        CacheManager $cacheManager,
        EntityManagerInterface $em
    ) {
        $this->appointmentRepository = $appointmentRepository;
        $this->cityRepository = $cityRepository;
        $this->brandRepository = $brandRepository;
        $this->modelRepository = $modelRepository;
        $this->generationRepository = $generationRepository;
        $this->specificationRepository = $specificationRepository;
        $this->maintenanceRepository = $maintenanceRepository;
        $this->appointmentCreator = $appointmentCreator;
        $this->appointmentUpdater = $appointmentUpdater;
        $this->messageSender = $messageSender;
        $this->siteUrl = $parameterBag->get('site_frontend_url');
        $this->logger = $textbackMaintenanceAppointmentLogger;
        $this->cacheManager = $cacheManager;
        $this->em = $em;
    }

    /**
     * @param array $data
     */
    public function process(array $data): void
    {
        try {
            $dto = $this->mapData($data);

            if ($dto->appointmentId) {
                $appointment = $this->getAppointment($dto->appointmentId);

                // работаем только с незавершенными заявками
                if (!$appointment->getIsFinal()) {
                    if ($dto->maintenanceId) {
                        $maintenance = $this->getMaintenance($dto->maintenanceId);

                        $appointment = $this->appointmentUpdater->updateMaintenance($appointment, $maintenance, true);

                        $this->messageSender->sendUrl($appointment, $this->generateUrl($appointment));

                        $this->appointmentUpdater->setFinal($appointment, true);

                        $this->clearAppointmentCache($appointment);

                        return;
                    }

                    if ($dto->specificationId) {
                        $specification = $this->getSpecification($dto->specificationId);

                        $appointment = $this->appointmentUpdater->updateSpecification($appointment, $specification);
                        $appointment->setMaintenance(null);

                        $this->messageSender->sendMessage($appointment);

                        return;
                    }

                    if ($dto->generationId) {
                        $generation = $this->getGeneration($dto->generationId);

                        $appointment = $this->appointmentUpdater->updateGeneration($appointment, $generation);
                        $appointment
                            ->setMaintenance(null)
                            ->setSpecification(null);

                        $this->messageSender->sendMessage($appointment);

                        return;
                    }

                    if ($dto->modelId) {
                        $model = $this->getModel($dto->modelId);

                        $appointment = $this->appointmentUpdater->updateModel($appointment, $model);
                        $appointment
                            ->setMaintenance(null)
                            ->setSpecification(null)
                            ->setGeneration(null);

                        $this->messageSender->sendMessage($appointment);

                        return;
                    }

                    if ($dto->brandId) {
                        $brand = $this->getBrand($dto->brandId);

                        $appointment = $this->appointmentUpdater->updateBrand($appointment, $brand);

                        $appointment
                            ->setMaintenance(null)
                            ->setSpecification(null)
                            ->setGeneration(null)
                            ->setModel(null);

                        $this->messageSender->sendMessage($appointment);

                        return;
                    }

                    if ($dto->cityId) {
                        $city = $this->getCity($dto->cityId);

                        $appointment = $this->appointmentUpdater->updateCity($appointment, $city);

                        $appointment
                            ->setMaintenance(null)
                            ->setSpecification(null)
                            ->setGeneration(null)
                            ->setModel(null)
                            ->setBrand(null);

                        $this->messageSender->sendMessage($appointment);
                    }

                    $this->em->flush();
                }
            } else {
                $appointment = $this->appointmentCreator->create($dto, true);

                $this->messageSender->sendMessage($appointment);
            }
        } catch (
        EntityNotFoundException
        |InvalidArgumentException
        |ClientExceptionInterface
        |RedirectionExceptionInterface
        |TransportExceptionInterface
        |ServerExceptionInterface $e
        ) {
            $this->logger->error('Ошибка при обработке заявки на ТО через TextBack', [
                'message' => $e->getMessage(),
                'data' => $data,
                'target' => sprintf('%s:%d', $e->getFile(), $e->getLine()),
                'trace' => $e->getTrace()
            ]);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'target' => sprintf('%s:%d', $e->getFile(), $e->getLine()),
                'trace' => $e->getTrace()
            ]);
        }
    }

    /**
     * @param array $inputData
     *
     * @return AppointmentDTO
     *
     * @throws DomainException|InvalidArgumentException
     */
    private function mapData(array $inputData): AppointmentDTO
    {
        if (
            isset(
                $inputData['command']['channel'],
                $inputData['command']['channelId'],
                $inputData['command']['chatId']
            )
        ) {
            $result = new AppointmentDTO(
                $inputData['command']['channel'],
                (int)$inputData['command']['channelId'],
                $inputData['command']['chatId']
            );

            if (
                isset($inputData['command']['payload'])
                &&
                !empty($inputData['command']['payload'])
            ) {
                $payload = $this->cacheManager->getItem($inputData['command']['payload']);

                $result->appointmentId = $payload['id'] ?? null;
                $result->cityId = $payload['city'] ?? null;
                $result->brandId = $payload['brand'] ?? null;
                $result->modelId = $payload['model'] ?? null;
                $result->generationId = $payload['generation'] ?? null;
                $result->specificationId = $payload['specification'] ?? null;
                $result->maintenanceId = $payload['maintenance'] ?? null;
            }

            return $result;
        }

        throw new DomainException('Не передан один из обязательных параметров channel, channelId, chatId');
    }

    /**
     * @param int $appointmentId
     *
     * @return Appointment
     *
     * @throws EntityNotFoundException
     */
    private function getAppointment(int $appointmentId): Appointment
    {
        $appointment = $this->appointmentRepository->find($appointmentId);

        if (is_null($appointment)) {
            throw new EntityNotFoundException(sprintf('Заявка %d не найдена', $appointmentId));
        }

        return $appointment;
    }

    /**
     * @param int $cityId
     * @return City
     *
     * @throws EntityNotFoundException
     */
    private function getCity(int $cityId): City
    {
        $city = $this->cityRepository->find($cityId);

        if (is_null($city)) {
            throw new EntityNotFoundException(sprintf('Город %d не найден', $cityId));
        }

        return $city;
    }

    /**
     * @param int $brandId
     *
     * @return Brand
     *
     * @throws EntityNotFoundException
     */
    private function getBrand(int $brandId): Brand
    {
        $brand = $this->brandRepository->find($brandId);

        if (is_null($brand)) {
            throw new EntityNotFoundException(sprintf('Марка %d не найдена', $brandId));
        }

        return $brand;
    }

    /**
     * @param int $modelId
     *
     * @return Model
     *
     * @throws EntityNotFoundException
     */
    private function getModel(int $modelId): Model
    {
        $model = $this->modelRepository->find($modelId);

        if (is_null($model)) {
            throw new EntityNotFoundException(sprintf('Модель %d не найдена', $modelId));
        }

        return $model;
    }

    /**
     * @param int $generationId
     *
     * @return Generation
     *
     * @throws EntityNotFoundException
     */
    private function getGeneration(int $generationId): Generation
    {
        $generation = $this->generationRepository->find($generationId);

        if (is_null($generation)) {
            throw new EntityNotFoundException(sprintf('Поколение %d не найдено', $generationId));
        }

        return $generation;
    }

    /**
     * @param int $specificationId
     *
     * @return Specification
     *
     * @throws EntityNotFoundException
     */
    private function getSpecification(int $specificationId): Specification
    {
        $specification = $this->specificationRepository->find($specificationId);

        if (is_null($specification)) {
            throw new EntityNotFoundException(sprintf('Спецификация %d не найдена', $specificationId));
        }

        return $specification;
    }

    /**
     * @param int $id
     *
     * @return Maintenance
     *
     * @throws EntityNotFoundException
     */
    private function getMaintenance(int $id): Maintenance
    {
        $maintenance = $this->maintenanceRepository->find($id);

        if (is_null($maintenance)) {
            throw new EntityNotFoundException(sprintf('ТО %d не найдено', $id));
        }

        return $maintenance;
    }

    /**
     * @param Appointment $appointment
     *
     * @return string
     *
     * @throws DomainException
     */
    private function generateUrl(Appointment $appointment): string
    {
        if (
            $appointment->getBrand()
            &&
            $appointment->getModel()
            &&
            $appointment->getGeneration()
            &&
            $appointment->getSpecification()
            &&
            $appointment->getMaintenance()
        ) {
            return sprintf(
                self::MAP_URL_SCHEME,
                $this->siteUrl,
                $appointment->getBrand()->getId(),
                $appointment->getModel()->getId(),
                $appointment->getGeneration()->getId(),
                $appointment->getSpecification()->getId(),
                $appointment->getMaintenance()->getId()
            );
        }

        throw new DomainException('Переданы не все параметры, необходимые для генерации ссылки на карту');
    }

    /**
     * @param Appointment $appointment
     *
     * @throws InvalidArgumentException
     */
    private function clearAppointmentCache(Appointment $appointment): void
    {
        if ($appointment->getIsFinal()) {
            foreach($appointment->getHashes() as $hash) {
                $this->cacheManager->remove($hash);
            }
        }
    }
}
