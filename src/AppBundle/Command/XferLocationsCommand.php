<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\LocationResource;
use Survos\Client\Resource\ObserveResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class XferLocationsCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('xfer:locations')
            ->setDescription('Transfer locations from source server')
            ->addOption(
                'source-user',
                null,
                InputOption::VALUE_REQUIRED,
                'Source user'
            )
            ->addOption(
                'target-user',
                null,
                InputOption::VALUE_REQUIRED,
                'Target user (defaults to source user)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $sourceUsername = $input->getOption('source-user');
        if (!$sourceUsername) {
            $output->writeln(
                "<error>--source-user is required</error>"
            );
            die();
        }
        $targetUsername = $input->getOption('target-user') ?: $sourceUsername;

        $fromLocationResource = new LocationResource($this->sourceClient);
        $toObserveResource = new ObserveResource($this->client);
        $fromUserResource = new UserResource($this->sourceClient);
        $toUserResource = new UserResource($this->client);

        $sourceUser = $fromUserResource->getOneBy(['username' => $sourceUsername]);
        if (!$sourceUser) {
            $output->writeln(
                "<error>Source user $sourceUsername not found</error>"
            );
            die();
        }
        $targetUser = $toUserResource->getOneBy(['username' => $targetUsername]);
        if (!$targetUser) {
            $output->writeln(
                "<error>Target user $targetUsername not found</error>"
            );
            die();
        }

        die('Not yet implemented'); // @todo

        $page = 0;
        $perPage = 10;
        $maxPages = 1;
        while ($page < $maxPages) {
            $result = $fromLocationResource->getList(
                ++$page,
                $perPage,
                ['user_id' => $sourceUser['id']],
                [],
                [],
                ['details' => true]
            );
            $maxPages = $result['pages'];
            // if no items, return
            if (!count($result['items']) || !$result['total']) {
                break;
            }

            $locationsToPost = [];
            foreach ($result['items'] as $key => $location) {
                if ($isVerbose) {
                    $no = ($page - 1) * $perPage + $key + 1;
                    $output->writeln("{$no} - Reading location #{$location['id']}");
                }
                $locationsToPost[] = [

                ];

            }
        }
    }
}
