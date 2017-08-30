<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;

class ExampleCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('example:import')
            ->setDescription('Command example')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED,
                'path to the CSV file'
            )
            ->addOption(
                'other-option',
                null,
                InputOption::VALUE_REQUIRED,
                'Server code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // read arguments/options
        $filename = $input->getArgument('filename');
        $otherOption = $input->getOption('other-option');

        $projectResource = new ProjectResource($this->client);
        $userResource = new UserResource($this->client);
        $memberResource = new MemberResource($this->client);

        // write message to console
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln("add some verbose messages");
        }
    }
}
