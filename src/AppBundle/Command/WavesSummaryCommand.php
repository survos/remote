<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\SurveyResource;
use Survos\Client\Resource\WaveResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Helper\Table;

class WavesSummaryCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('waves:summary')
            ->setDescription('Show basic summary for waves')
            ->addOption(
                'project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Project code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectResource = new ProjectResource($this->sourceClient);
        $wavesResource = new WaveResource($this->sourceClient);

        $result = $wavesResource->getList();
        foreach ($result['items'] as $idx => $wave) {

            $data[] =
                [
                    'wave_id'         => $wave['id'],
                    'task_count'      => $wave['task_count'],
                    'survey'          => '',
                    'questions'       => '',
                    'payment'         => isset($wave['reward'])?$wave['reward']:'',
                    'max_assignments' => '',
                ];
        }
        $table = new Table($output);
        $table
            ->setHeaders(['wave_id', 'task_id', 'survey', 'questions', 'payment', 'max_assignments'])
            ->setRows($data);
        $table->render();
    }
}
