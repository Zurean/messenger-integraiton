<?php

namespace App\Controller\WebHooks;

use App\Services\Webhook\TextBack;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use App\Services\DataValidatorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Throwable;

/**
 * @Route("/api/v1/textback", name="api_")
 */
class TextBackController extends AbstractFOSRestController
{
    /**
     * @var DataValidatorService
     */
    private $dataValidatorService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /** @var TextBack */
    private TextBack $messageService;

    /**
     * @param DataValidatorService $dataValidatorService
     * @param LoggerInterface $textbackWebhookChannelLogger
     * @param TextBack $messageService
     */
    public function __construct(
        DataValidatorService $dataValidatorService,
        LoggerInterface $textbackWebhookChannelLogger,
        TextBack $messageService
    )
    {
        $this->dataValidatorService = $dataValidatorService;
        $this->logger = $textbackWebhookChannelLogger;
        $this->messageService = $messageService;
    }

    /**
     * Метод приема сообщений от TextBack (WebHook).
     *
     * @Route("/webhook/", name="textback_new_messages", methods={"post"})
     *
     * @SWG\Response(response=200, description="Дефолтный статус код для ответа WebHook")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function messages(Request $request): Response
    {
        try {
            // @todo разобраться, почему вдруг вместо array стал отдаваться stdObject
            $data = json_decode(
                json_encode($this->dataValidatorService->getRequestData($request), JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->logger->info(json_encode($data, JSON_THROW_ON_ERROR));

            $this->messageService->messages($data);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'target' => sprintf('%s:%d', $e->getFile(), $e->getLine()),
                'trace' => $e->getTrace()
            ]);
        }

        return $this->handleView($this->view(null, Response::HTTP_OK));
    }
}
