<?php

namespace App\Services\Webhook;

use App\Entity\Notification\MaintenanceReminder;
use App\Entity\TextBackNotification;
use App\MessageSender\Tag\TagsMessageSender;
use App\Services\DataValidatorService;
use App\Services\TextBackNotificationsService;
use App\Services\Webhook\Appointment\Processor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JsonException;

class TextBack
{
    private const SUBSCRIPTION_TYPE_ORDER = 'order';
    private const SUBSCRIPTION_TYPE_REMINDER = 'reminder';
    private const SUBSCRIPTION_TYPE_APPOINTMENT = 'appointment';
    private const ACTION_TYPE_APPOINTMENT = 'appointment';

    /**
     * @var DataValidatorService
     */
    private DataValidatorService $dataValidatorService;

    /**
     * @var TextBackNotificationsService
     */
    private TextBackNotificationsService $textBackNotificationsService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var TagsMessageSender
     */
    private TagsMessageSender $tagsMessageSender;

    /**
     * @var Processor
     */
    private Processor $appointmentProcessor;

    /**
     * @param DataValidatorService $dataValidatorService
     * @param TextBackNotificationsService $textBackNotificationsService
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $textbackWebhookChannelLogger
     * @param TagsMessageSender $tagsMessageSender
     * @param Processor $appointmentProcessor
     */
    public function __construct(
        DataValidatorService $dataValidatorService,
        TextBackNotificationsService $textBackNotificationsService,
        EntityManagerInterface $entityManager,
        LoggerInterface $textbackWebhookChannelLogger,
        TagsMessageSender $tagsMessageSender,
        Processor $appointmentProcessor
    ) {
        $this->dataValidatorService = $dataValidatorService;
        $this->textBackNotificationsService = $textBackNotificationsService;
        $this->entityManager = $entityManager;
        $this->logger = $textbackWebhookChannelLogger;
        $this->tagsMessageSender = $tagsMessageSender;
        $this->appointmentProcessor = $appointmentProcessor;
    }

    /**
     * @param array $data
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ValidatorException|EntityNotFoundException|JsonException
     */
    public function messages(array $data): void
    {
        switch ($this->defineSubscriptionType($data)) {
            case self::SUBSCRIPTION_TYPE_ORDER:
                $this->processOrder($data);
                break;

            case self::SUBSCRIPTION_TYPE_REMINDER:
                $this->processReminder($data);
                break;

            case self::ACTION_TYPE_APPOINTMENT:
            case self::SUBSCRIPTION_TYPE_APPOINTMENT:
                $this->appointmentProcessor->process($data);
                break;
        }
    }

    /**
     * @param array $data
     *
     * @return string|null
     *
     * @throws ValidatorException
     * @throws JsonException
     */
    private function defineSubscriptionType(array $data): ?string
    {
        if ($this->dataValidatorService->get($data, ['command', 'subscription', 'insecureContext', 'orderId']) &&
            $this->dataValidatorService->get($data, ['command', 'subscription', 'insecureContext', 'type'])
        ) {
            return self::SUBSCRIPTION_TYPE_ORDER;
        }

        if ($this->dataValidatorService->get($data, ['command', 'subscription', 'insecureContext', 'reminderId'])) {
            return self::SUBSCRIPTION_TYPE_REMINDER;
        }

        if (
            $this->dataValidatorService->get($data, ['command', 'subscription', 'insecureContext', 'subscriptionType'])
            &&
            $this->dataValidatorService->get($data, ['command', 'subscription', 'insecureContext', 'subscriptionType'])
            === self::SUBSCRIPTION_TYPE_APPOINTMENT
        ) {
            return self::SUBSCRIPTION_TYPE_APPOINTMENT;
        }

        if ($this->dataValidatorService->get($data, ['command', 'payload'])) {
            // @todo пока считаем любой найденный payload в запросе от Textback
            // @todo признаком того, что была нажата кнопка при оформлении заявки на ТО,
            // @todo но в будущем, если добавятся другие кейсы нажатия кнопок,
            // @todo нужно будет добавить различие типов

            return self::ACTION_TYPE_APPOINTMENT;
        }

        return null;
    }

    /**
     * @param array $data
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ValidatorException
     */
    private function processOrder(array $data): void
    {
        $this->tagsMessageSender->send([
            'type' => $this->dataValidatorService->get($data, ['command', 'subscription', 'insecureContext', 'type']),
            'orderId' => $this->dataValidatorService->get(
                $data,
                ['command', 'subscription', 'insecureContext', 'orderId']
            ),
            'data' => [
                'chatId' => $this->dataValidatorService->get($data, ['command', 'chat', 'chatId']),
                'channelId' => $this->dataValidatorService->get($data, ['command', 'chat', 'channelId']),
                'channel' => $this->dataValidatorService->get($data, ['command', 'chat', 'channel'])
            ]
        ]);

        $this->textBackNotificationsService->sendNotification($data);

        $textBackNotification = new TextBackNotification();
        $textBackNotification->setData($data);

        $this->entityManager->persist($textBackNotification);
        $this->entityManager->flush();
    }

    /**
     * @param array $data
     *
     * @throws ValidatorException
     */
    public function processReminder(array $data): void
    {
        $reminderId = $this->dataValidatorService->get(
            $data, ['command', 'subscription', 'insecureContext', 'reminderId']
        );
        $channel = $this->dataValidatorService->get($data, ['command', 'chat', 'channel']);
        $channelId = $this->dataValidatorService->get($data, ['command', 'chat', 'channelId']);
        $chatId = $this->dataValidatorService->get($data, ['command', 'chat', 'chatId']);

        /** @var MaintenanceReminder|null $reminder */
        $reminder = $this->entityManager->getRepository(MaintenanceReminder::class)->find($reminderId);

        if (is_null($reminder)) {
            $this->logger->warning(sprintf('По переданному reminderId %d не найдено уведомление', $reminderId), [
                'data' => $data
            ]);
        } else {
            $reminder->setChannel($channel);
            $reminder->setChannelId($channelId);
            $reminder->setChatId($chatId);

            $this->entityManager->flush();
        }
    }
}
