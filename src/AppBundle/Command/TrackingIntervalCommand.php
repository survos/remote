<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\LocationResource;
use Survos\Client\Resource\SurveyResource;
use Survos\Client\Resource\TaskResource;
use Survos\Client\Resource\WaveResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrackingIntervalCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('tracking:interval')
            ->setDescription('Print data for days')
            ->addOption(
                'user',
                null,
                InputOption::VALUE_REQUIRED,
                'user'
            )->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                'date (YYYY-MM-DD)',
                date('Y-m-d')
            )->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'format',
                'json'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $username = $input->getOption('user');
        $date = $input->getOption('date');
        $output->writeln("Getting locations for $date");

        $userResource = new UserResource($this->sourceClient);
        $criteria = [];
        if ($username) {
            $user = $userResource->getOneBy(['username' => $username]);
            if (!$user) {
                throw new \Exception("User $username not found");
            }
            $criteria['user_id'] = $user['id'];
        }
        $locationResource = new LocationResource($this->sourceClient);
        $result = $locationResource->getList(
            1,
            100,
            $criteria,
            null,
            null,
            ['start_date' => $date]
        );
    }
}
