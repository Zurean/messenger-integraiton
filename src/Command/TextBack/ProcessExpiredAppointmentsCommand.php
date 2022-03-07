<?php

namespace App\Command\TextBack;

use App\Services\Notification\TextBack\Appointment\ExpiredAppointmentsFinalizer;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessExpiredAppointmentsCommand extends Command
{
    private const SUCCESS_CODE = 0;

    /**
     * @var ExpiredAppointmentsFinalizer
     */
    private ExpiredAppointmentsFinalizer $finalizer;

    /**
     * @param ExpiredAppointmentsFinalizer $finalizer
     */
    public function __construct(ExpiredAppointmentsFinalizer $finalizer)
    {
        parent::__construct();

        $this->finalizer = $finalizer;
    }
    protected function configure(): void
    {
        $this
            ->setName('textback:process-expired-appointments')
            ->setDescription('Закрывает заявки на ТО с истекшим временем жизни');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->finalizer->process();

        return self::SUCCESS_CODE;
    }
}
