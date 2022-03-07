<?php

namespace App\Services\Notification\TextBack;

use App\Entity\TextBack\Appointment;
use App\Exception\Integration\TextBack\TextBackGatewayException;
use App\Services\Notification\TextBack\Appointment\ButtonsBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use JsonException;

class AppointmentSender extends AbstractSender
{
    /**
     * @var EngineInterface
     */
    private EngineInterface $templating;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ButtonsBuilder
     */
    private ButtonsBuilder $buttonsBuilder;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

    /**
     * @param ParameterBagInterface $parameterBag
     * @param HttpClientInterface $httpClient
     * @param EngineInterface $templating
     * @param LoggerInterface $textbackMaintenanceAppointmentLogger
     * @param ButtonsBuilder $buttonsBuilder
     * @param EntityManagerInterface $em
     */
    public function __construct(
        ParameterBagInterface $parameterBag,
        HttpClientInterface $httpClient,
        EngineInterface $templating,
        LoggerInterface $textbackMaintenanceAppointmentLogger,
        ButtonsBuilder $buttonsBuilder,
        EntityManagerInterface $em
    ) {
        parent::__construct($parameterBag, $httpClient);

        $this->templating = $templating;
        $this->logger = $textbackMaintenanceAppointmentLogger;
        $this->buttonsBuilder = $buttonsBuilder;
        $this->em = $em;
    }

    /**
     * @param Appointment $appointment
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function sendMessage(Appointment $appointment): void
    {
        $buttons = $this->processButtons($appointment);

        $message = $this->buildMessage($appointment);

        $additionalParams = (!empty($buttons))
            ?
            [
                'inline' => true,
                'buttons' => $buttons
            ]
            : [];

        try {
            $response = $this->send(
                $appointment->getChannel(),
                $appointment->getChannelId(),
                $appointment->getChatId(),
                $message,
                $additionalParams
            );

            $this->logger->info('Ответ от TextBack', [
                'status' => $response->getStatusCode(),
                'message' => $response->getContent()
            ]);
        } catch (JsonException|TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface
        |ClientExceptionInterface|TextBackGatewayException $e) {
            $request = [
                'chatId' => $appointment->getChatId(),
                'channelId' => $appointment->getChannelId(),
                'channel' => $appointment->getChannel(),
                'text' => $message
            ];

            if (!empty($additionalParams)) {
                $request = array_merge($additionalParams, $request);
            }

            $this->logger->error('Ошибка при отправке сообщения о заявке на ТО', [
                'error' => $e->getMessage(),
                'data' => $request
            ]);
        }
    }

    /**
     * @param Appointment $appointment
     * @param string $url
     *
     * @return void
     *
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function sendUrl(Appointment $appointment, string $url): void
    {
        $this->send(
            $appointment->getChannel(),
            $appointment->getChannelId(),
            $appointment->getChatId(),
            $url
        );
    }

    /**
     * @param Appointment $appointment
     *
     * @return string
     */
    private function buildMessage(Appointment $appointment): string
    {
        if (is_null($appointment->getCity())) {
            return $this->renderMessage('start');
        }

        if (is_null($appointment->getBrand())) {
            return $this->renderMessage('brand');
        }

        if (is_null($appointment->getModel())) {
            return $this->renderMessage('model');
        }

        if (is_null($appointment->getGeneration())) {
            return $this->renderMessage('generation');
        }

        if (is_null($appointment->getSpecification())) {
            return $this->renderMessage('specification');
        }

        if (is_null($appointment->getMaintenance())) {
            return $this->renderMessage('maintenance');
        }

        return '';
    }

    private function renderMessage(string $type): string
    {
        return $this->templating->render(sprintf('notification/maintenance/appointment/%s.html.twig', $type));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function processButtons(Appointment $appointment): array
    {
        $buttons = $this->buttonsBuilder->build($appointment);

        // если на определенном шаге
        // на выбор юзера пришел пустой массив вариантов,
        // делаем повтор того же шага в сообщении
        // и пытаемся получить корректный ответ
        if (empty($buttons)) {
            $this->doBackStep($appointment);

            $this->processButtons($appointment);
        }

        return $buttons;
    }

    private function doBackStep(Appointment $appointment): void
    {
        if (is_null($appointment->getBrand())) {
            $appointment->setCity(null);
        }

        if (is_null($appointment->getModel())) {
            $appointment->setBrand(null);
        }

        if (is_null($appointment->getGeneration())) {
            $appointment->setModel(null);
        }

        if (is_null($appointment->getSpecification())) {
            $appointment->setGeneration(null);
        }

        if (is_null($appointment->getMaintenance())) {
            $appointment->setSpecification(null);
        }

        $this->em->flush();
    }
}
